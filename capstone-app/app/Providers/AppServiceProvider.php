<?php

namespace App\Providers;

use App\Models\AssessmentComponent;
use App\Services\Drive\DriveGateway;
use App\Services\Drive\DriveOAuth;
use App\Services\Drive\GoogleDriveClient;
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
        AssessmentComponent::syncConfig();
    }
}
