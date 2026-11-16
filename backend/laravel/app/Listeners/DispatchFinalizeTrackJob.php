<?php

namespace App\Listeners;

use App\Events\TrackFinished;
use App\Jobs\FinalizeTrackJob;

class DispatchFinalizeTrackJob
{
    public function handle(TrackFinished $event): void
    {
        FinalizeTrackJob::dispatch($event->trackId);
    }
}
