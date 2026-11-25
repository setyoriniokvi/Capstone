<?php

namespace App\Http\Middleware;

use App\Models\DocumentVersion;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use App\Support\PeriodContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman admin selain pengelolaan periode berjalan di dalam "Dashboard Periode".
 *  - Admin fakultas yang belum membuka periode diarahkan ke dashboard awal (Kelola Periode).
 *  - Admin prodi selalu berada di Dashboard Periode miliknya: bila belum ada / bukan miliknya, dibuka periode
 *    miliknya (periode aktif lebih dulu). Akun tanpa periode dikeluarkan.
 */
class EnsureAdminPeriod
{
    /** Rute yang dikelola dari dashboard awal admin fakultas, tanpa periode terbuka. */
    // admin.drive.*: kembali dari halaman persetujuan Google tidak boleh terputus karena periode belum dibuka.
    private const OUTSIDE = ['admin.periods.*', 'admin.milestones.*', 'admin.workspace.*', 'admin.drive.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isProdiAdmin()) {
            $current = (int) session(Period::WORKSPACE_KEY);
            if (! $current || ! $user->canOpenPeriod($current)) {
                $period = self::defaultPeriodFor($user);
                if (! $period) {
                    Auth::logoutCurrentDevice();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->withErrors(['email' => 'Akun admin prodi ini belum ditautkan ke periode mana pun. Hubungi admin fakultas.']);
                }
                session([Period::WORKSPACE_KEY => $period->id]);
                PeriodContext::refresh();
            }
            // Data periode lain (mis. tim / topik / dokumen lewat id di URL) tidak dapat dibuka.
            foreach ($request->route()?->parameters() ?? [] as $param) {
                abort_unless(self::belongsToWorkspace($param, (int) session(Period::WORKSPACE_KEY)), 404);
            }

            return $next($request);
        }

        if (! $request->routeIs(...self::OUTSIDE) && ! Period::workspace()) {
            session()->forget(Period::WORKSPACE_KEY);

            return redirect()->route('admin.periods.index')->with('status', 'Pilih periode terlebih dahulu untuk membuka dashboard periode.');
        }

        return $next($request);
    }

    /** Parameter rute (model) milik periode yang sedang dibuka; Period sendiri divalidasi di controller (ganti periode). */
    private static function belongsToWorkspace(mixed $param, int $workspace): bool
    {
        if (! $param instanceof Model || $param instanceof Period) {
            return true;
        }
        if ($param instanceof User) {
            return ! in_array($param->role, ['dosen', 'mahasiswa'], true) || $param->periods()->whereKey($workspace)->exists();
        }
        $period = match (true) {
            $param instanceof Team => $param->period_id,
            $param instanceof DocumentVersion => $param->document?->team?->period_id,
            array_key_exists('period_id', $param->getAttributes()) => $param->period_id,
            array_key_exists('team_id', $param->getAttributes()) => Team::query()->whereKey($param->team_id)->value('period_id'),
            default => $workspace,
        };

        return $period === null || (int) $period === $workspace;
    }

    /** Periode yang dibuka saat admin prodi masuk: periode aktif miliknya, selain itu yang terbaru. */
    public static function defaultPeriodFor($user): ?Period
    {
        return $user->adminPeriods()->reorder()->orderByDesc('is_active')->latest('starts_on')->orderByDesc('id')->first();
    }
}
