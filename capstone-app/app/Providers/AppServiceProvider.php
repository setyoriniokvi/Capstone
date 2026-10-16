<?php

namespace App\Providers;

use App\Services\Drive\DriveGateway;
use App\Services\Drive\GoogleDriveClient;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DriveGateway::class, fn () => new GoogleDriveClient(config('capstone.drive.credentials_path')));
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
        Paginator::defaultView('components.pagination');
    }
}
