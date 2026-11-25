<?php

namespace App\Support;

use App\Models\AssessmentComponent;
use App\Models\PeerAspect;
use App\Models\Period;
use App\Models\ScoreRule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Periode yang menjadi konteks data master (CLO, indikator, rubrik, bentuk penilaian, file pendukung, reminder,
 * instruksi & kata kunci topik, log master data, daftar dosen/mahasiswa). Setiap periode punya data master sendiri:
 *   - admin (fakultas / prodi): periode yang sedang dibuka (Dashboard Periode); di halaman pengaturan satu periode
 *     (rute dengan parameter {period}) dipakai periode tersebut;
 *   - dosen, mahasiswa, tamu, dan proses terjadwal: periode aktif;
 *   - using(): konteks eksplisit (mis. menyalin data master atau memproses data periode tertentu).
 * Nilai diingat selama satu request (container) dan dihitung ulang di awal setiap request (SyncPeriodContext).
 */
final class PeriodContext
{
    private const KEY = 'period.context';

    private const ACTIVE = 'period.active';

    private const SYNCED = 'period.synced';

    /** Kunci sesi periode pilihan dosen / mahasiswa. */
    public const USER_KEY = 'user_period_id';

    /** @var list<?int> */
    private static array $stack = [];

    public static function id(): ?int
    {
        if (self::$stack !== []) {
            return end(self::$stack);
        }
        if (app()->bound(self::KEY)) {
            return app(self::KEY);
        }

        [$id, $remember] = self::resolve();
        if ($remember) {
            app()->instance(self::KEY, $id);
        }

        return $id;
    }

    public static function period(): ?Period
    {
        $id = self::id();

        return $id ? Period::query()->find($id) : null;
    }

    /**
     * Periode tempat dosen / mahasiswa bekerja (Period::active()). Beberapa periode boleh aktif bersamaan: dipakai
     * periode yang dipilih lewat menu ganti periode (disimpan per sesi, jadi tiap perangkat bisa berbeda), selain itu
     * periode aktif pertama tempat pengguna terdaftar, lalu periode aktif terbaru. Diingat selama request berjalan.
     */
    public static function activePeriod(): ?Period
    {
        if (! self::sessionStarted()) {
            return self::latestActive();
        }
        if (! app()->bound(self::ACTIVE)) {
            app()->instance(self::ACTIVE, self::userPeriod() ?? false);
        }

        return app(self::ACTIVE) ?: null;
    }

    /** Periode aktif tempat pengguna (dosen / mahasiswa) terdaftar dan berstatus aktif — pilihan menu ganti periode. */
    public static function periodsFor(User $user): Collection
    {
        $key = "period.options.{$user->id}";
        if (self::sessionStarted() && app()->bound($key)) {
            return app($key);
        }
        try {
            $periods = Period::query()->where('is_active', true)
                ->whereHas('members', fn ($q) => $q->where('users.id', $user->id)->where('period_users.is_active', true))
                ->latest('starts_on')->orderByDesc('id')->get();
        } catch (QueryException) {
            return collect();
        }
        if (self::sessionStarted()) {
            app()->instance($key, $periods);
        }

        return $periods;
    }

    /** Pilih periode kerja dosen / mahasiswa untuk sesi ini. */
    public static function choose(Period $period): void
    {
        session([self::USER_KEY => $period->id]);
        self::refresh(periodsChanged: true);
    }

    private static function userPeriod(): ?Period
    {
        $user = auth()->user();
        if (! $user || $user->isAdmin()) {
            return self::latestActive();
        }
        $options = self::periodsFor($user);
        $chosen = (int) session(self::USER_KEY);

        return $options->firstWhere('id', $chosen) ?? $options->first() ?? self::latestActive();
    }

    private static function latestActive(): ?Period
    {
        try {
            return Period::query()->where('is_active', true)->latest('starts_on')->orderByDesc('id')->first();
        } catch (QueryException) {
            return null; // tabel belum dimigrasikan (aplikasi boot sebelum migrasi)
        }
    }

    /** Jalankan $callback dengan konteks periode tertentu (null = data master bawaan / template). */
    public static function using(Period|int|null $period, callable $callback): mixed
    {
        self::$stack[] = $period instanceof Period ? $period->id : $period;
        self::syncConfig();
        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
            self::syncConfig();
        }
    }

    /**
     * Hitung ulang konteks (awal request, periode dibuka); config dimuat ulang hanya bila periodenya berbeda dari
     * yang terakhir dimuat. $periodsChanged: data periode berubah (mis. periode aktif diganti).
     */
    public static function refresh(bool $periodsChanged = false): void
    {
        app()->forgetInstance(self::KEY);
        // Profil dosen mengikuti periode konteks: relasi yang sudah dimuat untuk periode lain tidak dipakai lagi.
        if (self::sessionStarted()) {
            auth()->user()?->unsetRelation('lecturerProfile');
        }
        if ($periodsChanged) {
            app()->forgetInstance(self::ACTIVE);
        }
        if ($periodsChanged || ! app()->bound(self::SYNCED) || app(self::SYNCED) !== self::id()) {
            self::syncConfig();
        }
    }

    /** Awal request: periode aktif & konteks dihitung ulang (config dimuat ulang hanya bila periodenya berubah). */
    public static function beginRequest(): void
    {
        app()->forgetInstance(self::ACTIVE);
        if ($id = auth()->id()) {
            app()->forgetInstance("period.options.{$id}");
        }
        self::refresh();
    }

    /** Ada sesi HTTP: tanpa periode berarti tidak ada data master yang boleh tampil (bukan semua periode). */
    public static function strict(): bool
    {
        return self::$stack === [] && self::sessionStarted();
    }

    /** Bentuk penilaian & rubrik peer review dicerminkan ke config untuk periode konteks. */
    public static function syncConfig(): void
    {
        AssessmentComponent::syncConfig();
        PeerAspect::syncConfig();
        ScoreRule::flush();
        app()->instance(self::SYNCED, self::id());
    }

    /** @return array{0: ?int, 1: bool} [id, boleh diingat selama request] */
    private static function resolve(): array
    {
        if (! self::sessionStarted()) {
            return [self::activePeriod()?->id, false];
        }

        $user = auth()->user();
        if ($user?->isAdmin()) {
            // Halaman pengaturan satu periode (Kelola Periode, khusus admin fakultas).
            $param = request()->route()?->parameter('period');
            if ($user->isFacultyAdmin() && $param) {
                return [$param instanceof Period ? $param->id : (int) $param, false];
            }
            $id = session(Period::WORKSPACE_KEY);

            return [$id ? (int) $id : null, false];
        }

        return [self::activePeriod()?->id, true];
    }

    private static function sessionStarted(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }
        $request = request();

        return $request->hasSession() && $request->session()->isStarted();
    }
}
