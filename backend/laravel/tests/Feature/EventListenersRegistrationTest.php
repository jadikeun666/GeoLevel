<?php

namespace Tests\Feature;

use App\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Regression: with framework event discovery on, every listener declared in
 * EventServiceProvider::$listen was registered twice (X and X@handle), so each
 * handler ran twice per event.
 */
class EventListenersRegistrationTest extends TestCase
{
    public function test_each_declared_listener_is_registered_exactly_once(): void
    {
        $declared = (new EventServiceProvider($this->app))->listens();

        $this->assertNotEmpty($declared);

        foreach ($declared as $event => $listeners) {
            $this->assertCount(
                count($listeners),
                Event::getListeners($event),
                "{$event} has duplicate listener registrations"
            );
        }
    }
}
