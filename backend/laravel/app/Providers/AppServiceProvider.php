<?php

namespace App\Providers;

use App\Models\Reading;
use App\Observers\ReadingObserver;
use App\Services\ClosureCheckerService;
use App\Services\LevelingCalculationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LevelingCalculationService::class);
        $this->app->singleton(ClosureCheckerService::class);
    }

    public function boot(): void
    {
        Reading::observe(ReadingObserver::class);
    }
}
