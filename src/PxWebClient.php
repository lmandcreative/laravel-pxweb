<?php

declare(strict_types=1);

namespace LmSomeco\PxWeb;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use LmSomeco\PxWeb\Exceptions\TableResolutionException;

class PxWebClient
{
    public function __construct(private readonly PendingRequest $http) {}

    /**
     * Fetches table metadata, trying each candidate URL in order.
     *
     * Falls through to the next candidate on ANY client error, not just 404 —
     * PxWeb has been observed answering an unknown or moved table name with a
     * 400 Bad Request rather than a 404, so narrowing this to 404 surfaces a
     * renamed table as a hard failure. Server errors abort immediately: a 5xx
     * means the agency is broken, not that this URL is wrong, and trying the
     * next candidate would mask an outage as a resolution failure.
     *
     * @param  string|array<int, string>  $candidateUrls
     *
     * @throws TableResolutionException when no candidate responds successfully
     * @throws RequestException on a server error
     */
    public function resolveTable(string|array $candidateUrls): PxWebTable
    {
        $urls = array_values((array) $candidateUrls);

        foreach ($urls as $url) {
            $response = $this->http->get($url);

            if ($response->successful()) {
                // Metadata comes back either wrapped in a "variables" key or as
                // a bare array, depending on the PxWeb deployment.
                $variables = $response->json('variables') ?? $response->json();

                return new PxWebTable($url, $variables ?? []);
            }

            if ($response->serverError()) {
                $response->throw();
            }
        }

        throw new TableResolutionException($urls);
    }

    /**
     * @param  array<int, array<string, mixed>>  $query
     * @return array<string, mixed> raw json-stat2 payload
     *
     * @throws RequestException
     */
    public function query(PxWebTable $table, array $query): array
    {
        $response = $this->http->post($table->url, [
            'query' => $query,
            'response' => ['format' => 'json-stat2'],
        ]);

        $response->throw();

        return $response->json();
    }
}
