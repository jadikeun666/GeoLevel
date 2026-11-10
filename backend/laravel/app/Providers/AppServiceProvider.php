<?php

namespace App\Providers;

use App\Models\Reading;
use App\Models\Track;
use App\Models\Waypoint;
use App\Observers\ReadingObserver;
use App\Policies\OutdoorItemPolicy;
use App\Services\ClosureCheckerService;
use App\Services\LevelingCalculationService;
use Illuminate\Support\Facades\Gate;
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

        Gate::policy(Waypoint::class, OutdoorItemPolicy::class);
        Gate::policy(Track::class, OutdoorItemPolicy::class);
    }
}
