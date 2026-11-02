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
            'admin' => self::admin(),
            'dosen' => self::lecturer(),
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
                self::item('Kuota Dosen', 'admin.quotas.index', 'scale', 'admin.quotas.*'),
                self::item('Plotting Dosen', 'admin.plotting.index', 'arrows-right-left', 'admin.plotting.*'),
            ]],
            ['label' => 'Pemantauan', 'items' => [
                self::item('Progres Tim', 'admin.monitoring.progress', 'chart-bar'),
                self::item('Penilaian & Reminder', 'admin.monitoring.assessments', 'bell-alert'),
                self::item('Sinkronisasi Dokumen', 'admin.documents.index', 'cloud-arrow-up', 'admin.documents.*'),
                self::item('Notifikasi Otomatis', 'admin.reminders.index', 'paper-airplane', 'admin.reminders.*'),
                self::item('Rekap Nilai', 'admin.recap.index', 'table-cells', 'admin.recap.*'),
            ]],
            ['label' => 'Master Penilaian', 'items' => [
                self::item('Resume CLO', 'admin.clos.index', 'squares-2x2', 'admin.clos.*'),
                self::item('Indikator', 'admin.indicators.index', 'list-bullet', 'admin.indicators.*'),
                self::item('Rubrik Dokumen', 'admin.rubrics.index', 'document-text', 'admin.rubrics.*'),
                self::item('Rubrik Lisan', 'admin.oral.index', 'chat-bubble-left-right', 'admin.oral.*'),
                self::item('File Pendukung', 'admin.files.index', 'folder', 'admin.files.*'),
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

    private static function lecturer(): array
    {
        return [
            ['label' => null, 'items' => [
                self::item('Dashboard', 'dosen.dashboard', 'home', 'dosen.dashboard'),
                self::item('Topik Dosen', 'dosen.topics.index', 'light-bulb', 'dosen.topics.*'),
                self::item('Tim Bimbingan', 'dosen.teams.supervised', 'user-group', 'dosen.teams.supervised'),
                self::item('Tim Uji', 'dosen.teams.examined', 'academic-cap', 'dosen.teams.examined'),
                self::item('Approval Pengajuan Topik', 'dosen.applications.index', 'inbox-arrow-down', 'dosen.applications.*'),
                self::item('Approval Bimbingan', 'dosen.logbooks.index', 'clipboard-document-check', 'dosen.logbooks.*'),
                self::item('Penilaian', 'dosen.assessments.index', 'pencil-square', 'dosen.assessments.*'),
            ]],
            ['label' => 'Informasi', 'items' => [
                self::item('File Pendukung', 'files.index', 'folder', 'files.*'),
            ]],
        ];
    }
}
