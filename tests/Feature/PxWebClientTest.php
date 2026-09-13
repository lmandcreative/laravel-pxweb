<?php

declare(strict_types=1);

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use LmSomeco\PxWeb\Exceptions\PxWebException;
use LmSomeco\PxWeb\Exceptions\TableResolutionException;
use LmSomeco\PxWeb\PxWebClient;
use LmSomeco\PxWeb\PxWebQueryBuilder;
use LmSomeco\PxWeb\PxWebTable;

const PRIMARY = 'https://example.test/api/v1/en/Arcadia/forest/short.px';
const FALLBACK = 'https://example.test/api/v1/en/Arcadia/forest/arcadia_forest_pxt_long.px';

/**
 * Resolved only after Http::fake() has run: the factory copies its stub
 * callbacks onto each request it creates, so the binding must be resolved
 * against an already-faked factory.
 */
function client(): PxWebClient
{
    return app(PxWebClient::class);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('resolves from the first candidate URL when it succeeds', function () {
    Http::fake([
        PRIMARY => Http::response(arcadiaMetadataFixture()),
        FALLBACK => Http::response([], 404),
    ]);

    $table = client()->resolveTable([PRIMARY, FALLBACK]);

    expect($table)->toBeInstanceOf(PxWebTable::class)
        ->and($table->url)->toBe(PRIMARY)
        ->and($table->variables)->toBe(arcadiaMetadataFixture()['variables']);

    Http::assertSentCount(1);
});

it('falls through to the next candidate on a 404', function () {
    Http::fake([
        PRIMARY => Http::response([], 404),
        FALLBACK => Http::response(arcadiaMetadataFixture()),
    ]);

    $table = client()->resolveTable([PRIMARY, FALLBACK]);

    expect($table->url)->toBe(FALLBACK);
    Http::assertSentCount(2);
});

it('falls through to the next candidate on a 400', function () {
    // The non-obvious one: PxWeb answers a moved or renamed table with a
    // 400 Bad Request rather than a 404, so narrowing the fall-through to 404
    // would surface a rename as a hard failure.
    Http::fake([
        PRIMARY => Http::response('Bad Request', 400),
        FALLBACK => Http::response(arcadiaMetadataFixture()),
    ]);

    $table = client()->resolveTable([PRIMARY, FALLBACK]);

    expect($table->url)->toBe(FALLBACK);
    Http::assertSentCount(2);
});

it('falls through on any client error status', function (int $status) {
    Http::fake([
        PRIMARY => Http::response([], $status),
        FALLBACK => Http::response(arcadiaMetadataFixture()),
    ]);

    expect(client()->resolveTable([PRIMARY, FALLBACK])->url)->toBe(FALLBACK);
})->with([400, 401, 403, 404, 410, 422, 429]);

it('aborts immediately on a server error without trying the next candidate', function () {
    // A 5xx means the agency is broken, not that this URL is wrong. Trying the
    // next candidate would mask an outage as a resolution failure.
    Http::fake([
        PRIMARY => Http::response('Service Unavailable', 503),
        FALLBACK => Http::response(arcadiaMetadataFixture()),
    ]);

    expect(fn () => client()->resolveTable([PRIMARY, FALLBACK]))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => $request->url() === FALLBACK);
});

it('throws TableResolutionException naming every candidate when all fail', function () {
    Http::fake([
        PRIMARY => Http::response([], 404),
        FALLBACK => Http::response([], 404),
    ]);

    try {
        client()->resolveTable([PRIMARY, FALLBACK]);
        $this->fail('Expected a TableResolutionException.');
    } catch (TableResolutionException $e) {
        expect($e->candidateUrls)->toBe([PRIMARY, FALLBACK])
            ->and($e->getMessage())->toContain(PRIMARY)
            ->and($e->getMessage())->toContain(FALLBACK)
            ->and($e->getMessage())->toContain('2 candidate URL(s)');
    }

    Http::assertSentCount(2);
});

it('throws a resolution exception that existing broad catches still handle', function () {
    Http::fake([PRIMARY => Http::response([], 404)]);

    expect(fn () => client()->resolveTable(PRIMARY))
        ->toThrow(PxWebException::class)
        ->and(fn () => client()->resolveTable(PRIMARY))
        ->toThrow(RuntimeException::class);
});

it('accepts metadata wrapped in a variables key', function () {
    Http::fake([PRIMARY => Http::response(arcadiaMetadataFixture())]);

    expect(client()->resolveTable(PRIMARY)->variables)
        ->toBe(arcadiaMetadataFixture()['variables']);
});

it('accepts metadata as a bare array', function () {
    $bare = arcadiaMetadataFixture()['variables'];

    Http::fake([PRIMARY => Http::response($bare)]);

    expect(client()->resolveTable(PRIMARY)->variables)->toBe($bare);
});

it('yields an empty variable list when the body has no usable metadata', function () {
    Http::fake([PRIMARY => Http::response(null, 200)]);

    expect(client()->resolveTable(PRIMARY)->variables)->toBe([]);
});

it('accepts a single string URL as well as an array', function () {
    Http::fake([PRIMARY => Http::response(arcadiaMetadataFixture())]);

    $table = client()->resolveTable(PRIMARY);

    expect($table->url)->toBe(PRIMARY);
    Http::assertSentCount(1);
});

it('names the single candidate when a string URL fails to resolve', function () {
    Http::fake([PRIMARY => Http::response([], 404)]);

    expect(fn () => client()->resolveTable(PRIMARY))
        ->toThrow(TableResolutionException::class, '1 candidate URL(s)');
});

it('posts the query with a json-stat2 response format and returns the decoded payload', function () {
    Http::fake([
        PRIMARY => Http::sequence()
            ->push(arcadiaMetadataFixture())
            ->push(arcadiaDatasetFixture()),
    ]);

    $client = client();
    $table = $client->resolveTable(PRIMARY);
    $query = PxWebQueryBuilder::selectAll($table->variables);

    $dataset = $client->query($table, $query);

    // Compared against the JSON round trip rather than the raw fixture: a whole
    // float such as 18.0 comes back off the wire as an int, which is the
    // transport's doing and not this package's.
    expect($dataset)->toBe(json_decode(json_encode(arcadiaDatasetFixture()), true));

    Http::assertSent(function ($request) use ($query) {
        return $request->method() === 'POST'
            && $request->url() === PRIMARY
            && $request->data() === ['query' => $query, 'response' => ['format' => 'json-stat2']];
    });
});

it('sends the query as a JSON body', function () {
    Http::fake([PRIMARY => Http::response(arcadiaDatasetFixture())]);

    client()->query(new PxWebTable(PRIMARY, []), []);

    Http::assertSent(fn ($request) => str_contains($request->header('Content-Type')[0] ?? '', 'application/json')
        && json_decode($request->body(), true) === ['query' => [], 'response' => ['format' => 'json-stat2']]);
});

it('throws on a failed query rather than returning a partial payload', function (int $status) {
    Http::fake([PRIMARY => Http::response('nope', $status)]);

    expect(fn () => client()->query(new PxWebTable(PRIMARY, []), []))
        ->toThrow(RequestException::class);
})->with([400, 404, 500, 503]);

it('binds a client out of the box that Http::fake() can intercept', function () {
    // Guards the service provider's use of Factory::createPendingRequest():
    // a bare `new PendingRequest($factory)` carries no stub callbacks, so this
    // request would escape to the network instead of hitting the fake.
    Http::fake([PRIMARY => Http::response(arcadiaMetadataFixture())]);

    expect(app(PxWebClient::class))->toBeInstanceOf(PxWebClient::class)
        ->and(app(PxWebClient::class))->toBe(app(PxWebClient::class));

    app(PxWebClient::class)->resolveTable(PRIMARY);

    Http::assertSentCount(1);
});

it('uses the consumer-supplied pending request, headers and all', function () {
    Http::fake([PRIMARY => Http::response(arcadiaMetadataFixture())]);

    $client = new PxWebClient(
        Http::withHeaders(['User-Agent' => 'arcadia-forestry/1.0'])->timeout(60)
    );

    $client->resolveTable(PRIMARY);

    Http::assertSent(fn ($request) => $request->header('User-Agent')[0] === 'arcadia-forestry/1.0');
});
