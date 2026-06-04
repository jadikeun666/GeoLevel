<?php

namespace App\Providers;

use App\Events\AdjustmentApplied;
use App\Events\ClosureChecked;
use App\Events\ExportGenerated;
use App\Events\SurveyRecalculated;
use App\Listeners\LogAdjustmentApplied;
use App\Listeners\LogClosureResult;
use App\Listeners\LogExportGenerated;
use App\Listeners\RunClosureCheck;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SurveyRecalculated::class => [
            RunClosureCheck::class,   // queued — triggers ClosureCheckerService
        ],

        ClosureChecked::class => [
            LogClosureResult::class,  // synchronous — fast DB write
        ],

        AdjustmentApplied::class => [
            LogAdjustmentApplied::class,
        ],

        ExportGenerated::class => [
            LogExportGenerated::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}