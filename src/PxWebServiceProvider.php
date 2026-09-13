<?php

declare(strict_types=1);

namespace LmSomeco\PxWeb;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class PxWebServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PxWebClient::class, fn ($app) => new PxWebClient(
            // createPendingRequest() rather than `new PendingRequest($factory)`:
            // the factory copies its stub callbacks and stray-request guard onto
            // each request it creates, so a bare construction is invisible to
            // Http::fake() and would let real network calls escape in a
            // consumer's test suite.
            $app->make(Factory::class)->createPendingRequest()
        ));
    }
}
