<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\Lecturer;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\QrResolveController;
use App\Http\Controllers\Student;
use App\Http\Controllers\SupportFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route(auth()->user()->homeRoute()) : redirect()->route('login'));

// F1 — login email + password
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // F48
    Route::get('/notifikasi/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // F20 / F45 — file pendukung (semua peran)
    Route::get('/file-pendukung', [SupportFileController::class, 'index'])->name('files.index');
    Route::get('/file-pendukung/{file}/unduh', [SupportFileController::class, 'download'])->name('files.download');

    // F36 — unduh dokumen dengan validasi hak akses di backend
    Route::get('/dokumen/versi/{version}/unduh', [DocumentFileController::class, 'download'])->name('documents.download');
    Route::get('/tim/{team}/lampiran/{member}/{kind}', [DocumentFileController::class, 'attachment'])
        ->whereIn('kind', ['khs', 'domisili'])->name('attachments.download');

    // F31 / F32 / F58 / F59 — QR penilaian
    Route::get('/q/{token}', QrResolveController::class)->name('qr.resolve');
});

/* ------------------------------------------------------------------ Admin */
Route::middleware(['auth', 'role:admin', 'admin.period'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Dashboard awal admin (Kelola Periode) ⇄ Dashboard Periode
    Route::post('periode/{period}/masuk', [Admin\PeriodController::class, 'enter'])->name('workspace.enter');
    Route::post('periode-keluar', [Admin\PeriodController::class, 'leave'])->name('workspace.leave');

    // F2
    Route::resource('periode', Admin\PeriodController::class)->parameters(['periode' => 'period'])->names('periods')->except('show');
    Route::post('periode/{period}/aktifkan', [Admin\PeriodController::class, 'activate'])->name('periods.activate');
    Route::post('periode/{period}/nonaktifkan', [Admin\PeriodController::class, 'deactivate'])->name('periods.deactivate');
    Route::post('periode/{period}/milestone', [Admin\MilestoneController::class, 'store'])->name('milestones.store');
    Route::put('milestone/{milestone}', [Admin\MilestoneController::class, 'update'])->name('milestones.update');
    Route::delete('milestone/{milestone}', [Admin\MilestoneController::class, 'destroy'])->name('milestones.destroy');

    // F3 / F4 + impor
    Route::resource('dosen', Admin\LecturerController::class)->parameters(['dosen' => 'lecturer'])->names('lecturers')->except('show', 'destroy');
    Route::post('dosen/{lecturer}/status', [Admin\LecturerController::class, 'toggle'])->name('lecturers.toggle');
    Route::get('mahasiswa', [Admin\StudentController::class, 'index'])->name('students.index');
    Route::get('mahasiswa/{student}/edit', [Admin\StudentController::class, 'edit'])->name('students.edit');
    Route::put('mahasiswa/{student}', [Admin\StudentController::class, 'update'])->name('students.update');
    Route::post('mahasiswa/{student}/status', [Admin\StudentController::class, 'toggle'])->name('students.toggle');
    Route::get('impor/{type}/template', [Admin\ImportController::class, 'template'])->whereIn('type', ['dosen', 'mahasiswa', 'tim'])->name('imports.template');
    Route::post('impor/{type}', [Admin\ImportController::class, 'preview'])->whereIn('type', ['dosen', 'mahasiswa', 'tim'])->name('imports.preview');
    Route::get('impor/{type}/pratinjau', [Admin\ImportController::class, 'show'])->whereIn('type', ['dosen', 'mahasiswa', 'tim'])->name('imports.show');
    Route::post('impor/{type}/simpan', [Admin\ImportController::class, 'commit'])->whereIn('type', ['dosen', 'mahasiswa', 'tim'])->name('imports.commit');

    // F4 / F6 — tim & approval registrasi
    Route::get('tim', [Admin\TeamController::class, 'index'])->name('teams.index');
    Route::get('tim/{team}', [Admin\TeamController::class, 'show'])->name('teams.show');
    Route::post('tim/{team}/keputusan', [Admin\TeamController::class, 'decide'])->name('teams.decide');
    Route::post('tim/{team}/screening', [Admin\TeamController::class, 'rescreen'])->name('teams.rescreen');
    Route::post('tim/{team}/anggota', [Admin\TeamController::class, 'addMember'])->name('teams.members.store');
    Route::delete('tim/{team}/anggota/{member}', [Admin\TeamController::class, 'removeMember'])->name('teams.members.destroy');
    Route::post('tim/{team}/qr', [Admin\TeamController::class, 'regenerateQr'])->name('teams.qr');
    Route::get('tim/{team}/qr.svg', [Admin\TeamController::class, 'qrSvg'])->name('teams.qr.svg');

    // F7 / F8
    Route::get('topik', [Admin\TopicController::class, 'index'])->name('topics.index');
    Route::post('topik/{topic}/keputusan', [Admin\TopicController::class, 'decide'])->name('topics.decide');
    Route::get('topik-final', [Admin\TopicController::class, 'recap'])->name('topics.recap');
    Route::post('kata-kunci', [Admin\TopicController::class, 'storeKeyword'])->name('keywords.store');
    Route::delete('kata-kunci/{keyword}', [Admin\TopicController::class, 'destroyKeyword'])->name('keywords.destroy');

    // F9 / F10
    Route::get('kuota-dosen', [Admin\QuotaController::class, 'index'])->name('quotas.index');
    Route::post('kuota-dosen', [Admin\QuotaController::class, 'store'])->name('quotas.store');
    Route::delete('kuota-dosen/{quota}', [Admin\QuotaController::class, 'destroy'])->name('quotas.destroy');
    Route::get('plotting', [Admin\PlottingController::class, 'index'])->name('plotting.index');
    Route::post('plotting/{team}', [Admin\PlottingController::class, 'store'])->name('plotting.store');
    Route::delete('plotting/{assignment}', [Admin\PlottingController::class, 'destroy'])->name('plotting.destroy');

    // F11 / F12
    Route::get('monitoring/progres', [Admin\MonitoringController::class, 'progress'])->name('monitoring.progress');
    Route::get('monitoring/penilaian', [Admin\MonitoringController::class, 'assessments'])->name('monitoring.assessments');
    Route::post('monitoring/penilaian/reminder', [Admin\MonitoringController::class, 'remind'])->name('monitoring.remind');

    // F13–F16 master penilaian
    Route::get('master/clo', [Admin\CloController::class, 'index'])->name('clos.index');
    Route::post('master/clo', [Admin\CloController::class, 'store'])->name('clos.store');
    Route::put('master/clo/{clo}', [Admin\CloController::class, 'update'])->name('clos.update');
    Route::delete('master/clo/{clo}', [Admin\CloController::class, 'destroy'])->name('clos.destroy');
    Route::resource('master/indikator', Admin\IndicatorController::class)->parameters(['indikator' => 'indicator'])->names('indicators')->except('show');
    Route::get('master/rubrik-dokumen', [Admin\RubricController::class, 'index'])->name('rubrics.index');
    Route::get('master/rubrik-dokumen/{indicator}', [Admin\RubricController::class, 'edit'])->name('rubrics.edit');
    Route::put('master/rubrik-dokumen/{indicator}', [Admin\RubricController::class, 'update'])->name('rubrics.update');
    Route::delete('master/rubrik-dokumen/{indicator}', [Admin\RubricController::class, 'destroy'])->name('rubrics.destroy');
    Route::get('master/rubrik-lisan', [Admin\OralRubricController::class, 'index'])->name('oral.index');
    Route::post('master/rubrik-lisan', [Admin\OralRubricController::class, 'store'])->name('oral.store');
    Route::put('master/rubrik-lisan/{level}', [Admin\OralRubricController::class, 'update'])->name('oral.update');
    Route::delete('master/rubrik-lisan/{level}', [Admin\OralRubricController::class, 'destroy'])->name('oral.destroy');

    // F17
    Route::get('file-pendukung', [Admin\SupportFileController::class, 'index'])->name('files.index');
    Route::post('file-pendukung', [Admin\SupportFileController::class, 'store'])->name('files.store');
    Route::put('file-pendukung/{file}', [Admin\SupportFileController::class, 'update'])->name('files.update');
    Route::delete('file-pendukung/{file}', [Admin\SupportFileController::class, 'destroy'])->name('files.destroy');

    // F18
    Route::get('rekap-nilai', [Admin\RecapController::class, 'index'])->name('recap.index');
    Route::get('rekap-nilai/ekspor', [Admin\RecapController::class, 'export'])->name('recap.export');

    // F49–F56
    Route::get('reminder', [Admin\ReminderController::class, 'index'])->name('reminders.index');
    Route::post('reminder/aturan', [Admin\ReminderController::class, 'store'])->name('reminders.store');
    Route::put('reminder/aturan/{rule}', [Admin\ReminderController::class, 'update'])->name('reminders.update');
    Route::delete('reminder/aturan/{rule}', [Admin\ReminderController::class, 'destroy'])->name('reminders.destroy');
    Route::post('reminder/log/{dispatch}/ulang', [Admin\ReminderController::class, 'retry'])->name('reminders.retry');

    // F33 / F40 — status sinkronisasi dokumen
    Route::get('dokumen', [Admin\DocumentController::class, 'index'])->name('documents.index');
    Route::post('dokumen/versi/{version}/ulang', [Admin\DocumentController::class, 'retry'])->name('documents.retry');
    Route::delete('dokumen/{document}', [Admin\DocumentController::class, 'destroy'])->name('documents.destroy');
});

/* -------------------------------------------------------------- Mahasiswa */
Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/', [Student\DashboardController::class, 'index'])->name('dashboard');

    // F21 / F24
    Route::get('tim', [Student\TeamController::class, 'show'])->name('team.show');
    Route::post('tim', [Student\TeamController::class, 'store'])->name('team.store');
    Route::put('tim', [Student\TeamController::class, 'update'])->name('team.update');

    // F23 / F25 / F22
    Route::get('topik', [Student\TopicController::class, 'index'])->name('topic.index');
    Route::post('topik', [Student\TopicController::class, 'store'])->name('topic.store');
    Route::get('bank-topik', [Student\TopicBankController::class, 'index'])->name('bank.index');
    Route::post('bank-topik/{topic}/lamar', [Student\TopicBankController::class, 'apply'])->name('bank.apply');
    Route::delete('lamaran/{application}', [Student\TopicBankController::class, 'withdraw'])->name('bank.withdraw');

    // F26 / F33–F41
    Route::get('dokumen', [Student\DocumentController::class, 'index'])->name('documents.index');
    Route::post('dokumen/{milestone}', [Student\DocumentController::class, 'store'])->name('documents.store');
    Route::delete('dokumen/{document}', [Student\DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('dokumen/versi/{version}/ulang', [Student\DocumentController::class, 'retry'])->name('documents.retry');

    // F27
    Route::get('bimbingan', [Student\LogbookController::class, 'index'])->name('logbooks.index');
    Route::post('bimbingan', [Student\LogbookController::class, 'store'])->name('logbooks.store');
    Route::put('bimbingan/{logbook}', [Student\LogbookController::class, 'update'])->name('logbooks.update');
    Route::delete('bimbingan/{logbook}', [Student\LogbookController::class, 'destroy'])->name('logbooks.destroy');

    // F28 / F29 / F30
    Route::get('peer-review', [Student\PeerReviewController::class, 'index'])->name('peer.index');
    Route::post('peer-review', [Student\PeerReviewController::class, 'store'])->name('peer.store');
    Route::get('nilai', [Student\ResultController::class, 'index'])->name('results.index');
    Route::get('qr', [Student\QrController::class, 'show'])->name('qr.show');
    Route::post('qr/baru', [Student\QrController::class, 'regenerate'])->middleware('throttle:5,1')->name('qr.regenerate');
    Route::get('qr.png', [Student\QrController::class, 'download'])->name('qr.download');
});

/* ------------------------------------------------------------------ Dosen */
Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('/', [Lecturer\DashboardController::class, 'index'])->name('dashboard');

    // F42 / F43
    Route::resource('topik', Lecturer\TopicController::class)->parameters(['topik' => 'topic'])->names('topics')->except('show');
    Route::get('pelamar', [Lecturer\ApplicationController::class, 'index'])->name('applications.index');
    Route::post('pelamar/{application}', [Lecturer\ApplicationController::class, 'decide'])->name('applications.decide');

    // F44
    Route::get('tim', [Lecturer\TeamController::class, 'index'])->name('teams.index');
    Route::get('tim/{team}', [Lecturer\TeamController::class, 'show'])->name('teams.show');
    Route::post('dokumen/{document}/feedback', [Lecturer\TeamController::class, 'feedback'])->name('documents.feedback');

    // F46
    Route::get('bimbingan', [Lecturer\LogbookController::class, 'index'])->name('logbooks.index');
    Route::post('bimbingan/{logbook}', [Lecturer\LogbookController::class, 'review'])->name('logbooks.review');

    // F47 / F60
    Route::get('penilaian', [Lecturer\AssessmentController::class, 'index'])->name('assessments.index');
    Route::get('penilaian/{team}/{component}', [Lecturer\AssessmentController::class, 'edit'])->name('assessments.edit');
    Route::put('penilaian/{team}/{component}', [Lecturer\AssessmentController::class, 'update'])->name('assessments.update');

    // F18
    Route::get('rekap-nilai', [Lecturer\RecapController::class, 'index'])->name('recap.index');
    Route::get('rekap-nilai/ekspor', [Lecturer\RecapController::class, 'export'])->name('recap.export');
});
