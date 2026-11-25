<?php

namespace App\Providers;

use App\Models\Period;
use App\Observers\PeriodObserver;
use App\Services\Drive\DriveGateway;
use App\Services\Drive\DriveOAuth;
use App\Services\Drive\GoogleDriveClient;
use App\Services\MasterData\MasterDataLogger;
use App\Support\PeriodContext;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // GOOGLE_DRIVE_AUTH=oauth → akun Google yang dihubungkan admin; selain itu service account.
        $this->app->bind(DriveGateway::class, fn ($app) => new GoogleDriveClient(
            config('capstone.drive.credentials_path'),
            config('capstone.drive.auth') === 'oauth' ? $app->make(DriveOAuth::class) : null,
        ));
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
        Paginator::defaultView('components.pagination');

        // Efek samping model didaftarkan di sini agar model tidak bergantung pada service.
        Period::observe(PeriodObserver::class);
        MasterDataLogger::register();

        // Config bentuk penilaian & rubrik peer review untuk periode aktif; disesuaikan lagi per request (SyncPeriodContext).
        PeriodContext::syncConfig();
    }
}
