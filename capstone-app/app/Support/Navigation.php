<?php

namespace App\Support;

use App\Models\Period;
use App\Models\User;

/** Menu samping per peran — mengikuti urutan menu pada flowchart setiap role. */
class Navigation
{
    /**
     * @return list<array{label: ?string, items: list<array{label: string, route: string, icon: string, match: string}>}>
     */
    public static function for(User $user): array
    {
        return match ($user->role) {
            'admin', 'admin_prodi' => self::admin(),
            'dosen' => self::lecturer($user),
            default => self::student(),
        };
    }

    private static function item(string $label, string $route, string $icon, string|array|null $match = null): array
    {
        return ['label' => $label, 'route' => $route, 'icon' => $icon, 'match' => $match ?? $route];
    }

    /** Admin: tanpa periode terbuka hanya "Kelola Periode"; di dalam Dashboard Periode tampil menu lengkap. */
    private static function admin(): array
    {
        if (! Period::workspace()) {
            return [
                ['label' => null, 'items' => [
                    self::item('Kelola Periode', 'admin.periods.index', 'calendar-days', 'admin.periods.*'),
                ]],
            ];
        }

        return [
            ['label' => null, 'items' => [
                self::item('Dashboard', 'admin.dashboard', 'home'),
            ]],
            ['label' => 'User', 'items' => [
                self::item('Dosen', 'admin.lecturers.index', 'academic-cap', 'admin.lecturers.*'),
                self::item('Mahasiswa', 'admin.students.index', 'users', 'admin.students.*'),
            ]],
            ['label' => 'Proses', 'items' => [
                self::item('Approval Registrasi Tim', 'admin.teams.index', 'user-group', 'admin.teams.*'),
                self::item('Approval Topik', 'admin.topics.index', 'light-bulb', 'admin.topics.index'),
                self::item('Bank Topik Dosen', 'admin.lecturer-topics.index', 'book-open', 'admin.lecturer-topics.*'),
                self::item('Kuota Dosen', 'admin.quotas.index', 'scale', 'admin.quotas.*'),
                self::item('Plotting Dosen', 'admin.plotting.index', 'arrows-right-left', 'admin.plotting.*'),
            ]],
            ['label' => 'Pemantauan', 'items' => [
                self::item('Tim Mahasiswa', 'admin.monitoring.progress', 'chart-bar'),
                self::item('Penilaian & Reminder', 'admin.monitoring.assessments', 'bell-alert'),
                self::item('Reminder Otomatis', 'admin.reminders.index', 'paper-airplane', 'admin.reminders.*'),
                self::item('Sinkronisasi Dokumen', 'admin.documents.index', 'cloud-arrow-up', 'admin.documents.*'),
                // Alur pengolahan nilai: raw data (per indikator / CLO) → rekap per bentuk penilaian × CLO iGadis (+ ekspor iGadis)
                self::item('Raw Data Penilaian', 'admin.raw-scores.index', 'clipboard-document-list', 'admin.raw-scores.*'),
                self::item('Rekap Penilaian', 'admin.recap.index', 'table-cells', 'admin.recap.*'),
            ]],
            ['label' => 'Master Penilaian', 'items' => [
                self::item('Resume CLO', 'admin.clos.index', 'squares-2x2', 'admin.clos.*'),
                self::item('Indikator', 'admin.indicators.index', 'list-bullet', 'admin.indicators.*'),
                self::item('Rubrik Dokumen', 'admin.rubrics.index', 'document-text', 'admin.rubrics.*'),
                self::item('Rubrik Lisan', 'admin.oral.index', 'chat-bubble-left-right', 'admin.oral.*'),
                self::item('Rubrik Peer Review', 'admin.peer-rubric.index', 'user-group', 'admin.peer-rubric.*'),
                self::item('File Pendukung', 'admin.files.index', 'folder', 'admin.files.*'),
            ]],
            // Paling bawah: log seluruh data mahasiswa / dosen (sebelum & sesudah setiap perubahan)
            ['label' => 'Log', 'items' => [
                self::item('Master Data', 'admin.master-data.index', 'circle-stack', 'admin.master-data.*'),
            ]],
        ];
    }

    private static function student(): array
    {
        return [
            ['label' => null, 'items' => [
                self::item('Dashboard Tim', 'mahasiswa.dashboard', 'home'),
                self::item('Tim Saya', 'mahasiswa.team.show', 'user-group', 'mahasiswa.team.*'),
                self::item('Topik', 'mahasiswa.topic.index', 'light-bulb', ['mahasiswa.topic.*', 'mahasiswa.bank.*']),
                self::item('Dokumen', 'mahasiswa.documents.index', 'document-arrow-up', 'mahasiswa.documents.*'),
                self::item('Log Bimbingan', 'mahasiswa.logbooks.index', 'clipboard-document-check', 'mahasiswa.logbooks.*'),
            ]],
            ['label' => 'Penilaian', 'items' => [
                self::item('Penilaian', 'mahasiswa.assessments.index', 'star', 'mahasiswa.assessments.*'),
                self::item('Peer Review', 'mahasiswa.peer.index', 'users', 'mahasiswa.peer.*'),
                self::item('QR Code Tim', 'mahasiswa.qr.show', 'qr-code', 'mahasiswa.qr.*'),
            ]],
            ['label' => 'Informasi', 'items' => [
                self::item('File Pendukung', 'files.index', 'folder', 'files.*'),
            ]],
        ];
    }

    /**
     * Dosen: menu mengikuti perannya di periode yang dibuka. Pembimbing saja → tanpa Tim Uji; penguji saja → tanpa
     * Tim Bimbingan & Approval Bimbingan; belum pembimbing/penguji → tanpa menu tim, approval bimbingan, dan penilaian.
     */
    private static function lecturer(User $user): array
    {
        $roles = $user->lecturerRoles();
        $supervises = in_array('pembimbing', $roles, true);
        $examines = in_array('penguji', $roles, true);

        return [
            ['label' => null, 'items' => array_values(array_filter([
                self::item('Dashboard', 'dosen.dashboard', 'home', 'dosen.dashboard'),
                self::item('Topik Dosen', 'dosen.topics.index', 'light-bulb', 'dosen.topics.*'),
                self::item('Approval Pengajuan Topik', 'dosen.applications.index', 'inbox-arrow-down', 'dosen.applications.*'),
                $supervises ? self::item('Tim Bimbingan', 'dosen.teams.supervised', 'user-group', 'dosen.teams.supervised') : null,
                $examines ? self::item('Tim Uji', 'dosen.teams.examined', 'academic-cap', 'dosen.teams.examined') : null,
                $supervises ? self::item('Approval Bimbingan', 'dosen.logbooks.index', 'clipboard-document-check', 'dosen.logbooks.*') : null,
                $roles ? self::item('Penilaian', 'dosen.assessments.index', 'pencil-square', ['dosen.assessments.*', 'dosen.adjustments.*']) : null,
                // Dosen kelas: rekap & ekspor iGadis per kelas mahasiswa (semua kelas dapat dipilih)
                $user->lecturerProfile?->is_class_lecturer ? self::item('Rekap Nilai Kelas', 'dosen.recap.index', 'table-cells', 'dosen.recap.*') : null,
            ]))],
            ['label' => 'Informasi', 'items' => [
                self::item('File Pendukung', 'files.index', 'folder', 'files.*'),
            ]],
        ];
    }
}
