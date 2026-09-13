# laravel-pxweb

A PxWeb API client for Laravel: table metadata discovery, candidate-URL resolution,
query building, and json-stat2 parsing.

PxWeb is the API served by a number of Nordic statistics agencies. This package is
the protocol-level client only — it targets **PxWeb API v1** with **json-stat2**
responses, and it contains no knowledge of any particular agency, table, or
subject area. Base URLs and table paths are always supplied by you.

## Requirements

- PHP 8.4+
- Laravel 13

## Installation

```bash
composer require lmsomeco/laravel-pxweb
```

The service provider is auto-discovered.

## Usage

```php
use LmSomeco\PxWeb\JsonStatParser;
use LmSomeco\PxWeb\PxWebClient;
use LmSomeco\PxWeb\PxWebQueryBuilder;

$client = app(PxWebClient::class);

// Try each candidate URL in order; the first that answers wins.
$table = $client->resolveTable([
    'https://example.org/PxWeb/api/v1/en/Agency/topic/short.px',
    'https://example.org/PxWeb/api/v1/en/Agency/topic/agency_topic_pxt_long.px',
]);

$dataset = $client->query($table, PxWebQueryBuilder::selectAll($table->variables));

foreach ((new JsonStatParser)->rows($dataset) as $row) {
    $row['indices']['SomeDimensionCode']; // category code, as a string
    $row['value'];                        // float|int|null
}
```

A single URL works too: `$client->resolveTable('https://…/table.px')`.

### Configuring HTTP — the primary usage pattern

**The package owns no HTTP configuration.** `PxWebClient` takes a
`PendingRequest`, so timeouts, retries, backoff, user-agent, and headers are all
configured through Laravel's own HTTP client, which you already know. That is why
there is no config file, no `user_agent` key, and no retry logic here.

Override the binding in your own service provider:

```php
use Illuminate\Support\Facades\Http;
use LmSomeco\PxWeb\PxWebClient;

$this->app->singleton(PxWebClient::class, fn () => new PxWebClient(
    Http::withHeaders(['User-Agent' => config('myapp.user_agent')])
        ->timeout(60)
        ->retry(3, 500)
));
```

Out of the box the package binds a bare, unconfigured request so
`app(PxWebClient::class)` resolves without any setup. It is built with
`Factory::createPendingRequest()` rather than `new PendingRequest($factory)`,
because only the former copies the factory's stub callbacks onto the request —
a bare construction is invisible to `Http::fake()` and would let real network
calls escape your test suite.

Note that the default binding is a singleton resolved lazily, and a request
captures the factory's fakes at the moment it is created. In tests, call
`Http::fake()` **before** resolving the client.

#### Assumption: JSON request bodies

`query()` relies on Laravel's default JSON body format. A `PendingRequest`
switched to `asForm()` will break it — PxWeb expects a JSON query document.

### Metadata shapes

PxWeb deployments return table metadata either wrapped in a `variables` key or as
a bare array. `resolveTable()` accepts both and exposes the variable list as
`$table->variables`.

### Variable roles

`PxWebVariableRoles` covers the two roles that are genuine PxWeb protocol
conventions, and deliberately nothing else:

```php
use LmSomeco\PxWeb\PxWebVariableRoles;

PxWebVariableRoles::contentVar($table->variables); // the ContentsCode variable, or null
PxWebVariableRoles::timeVar($table->variables);    // the time variable, or null
```

`contentVar()` matches `ContentsCode` case-insensitively. `timeVar()` prefers a
`time: true` flag and falls back to a `/^timeperiod/i` code.

Anything beyond these two — a geographic dimension, a classification dimension —
is a property of one particular table rather than of the protocol, and belongs at
your call site as a few lines of `array_filter`.

## Error handling

- `TableResolutionException` (extends `PxWebException` extends `RuntimeException`)
  when no candidate URL resolves. It carries `$e->candidateUrls`.
- Server errors and transport failures surface as Laravel's own
  `Illuminate\Http\Client\RequestException`. They are deliberately not wrapped.

`resolveTable()` falls through to the next candidate on **any** 4xx, not just a
404: PxWeb has been observed answering an unknown or moved table with a
400 Bad Request, so narrowing the fall-through to 404 turns a rename into a hard
failure. A 5xx aborts immediately — the agency is broken, and trying the next
candidate would mask an outage as a resolution failure.

## Parser behaviour worth knowing

`JsonStatParser` has two non-obvious behaviours, both deliberate:

1. **Strides are computed from the dataset's own `id`/`size` arrays.** Dimension
   order is not fixed across tables, and assuming a fixed position for any
   dimension is a real bug this avoids.
2. **Suppressed and missing cells normalise to `null`, never `0`.** `"."`, `".."`,
   `":"`, and any other non-numeric string become `null`. Coercing them to zero
   would silently corrupt every average computed downstream.

Category codes are returned as strings, including numeric ones such as `"2025"` —
json-stat2 carries them as JSON object keys, which are strings by specification,
but PHP coerces numeric keys to int on decode.

## Scope

### `query()` returns the raw json-stat2 array — a 1.0 decision

This is settled, not deferred. A consumer can wrap a raw array and cannot unwrap a
wrapper, so raw is the right default. Introducing a `JsonStatDataset` return type
later would be a **breaking change**, so please do not treat it as an easy
addition.

### Deliberately out of scope

| Excluded | Why |
| --- | --- |
| Caching | The only cacheable thing is table metadata — one `Cache::remember()` at your call site. |
| Retry / backoff / rate limiting | `Http::retry()` on the injected `PendingRequest` already does this. |
| Any config file | Nothing is left to configure once HTTP belongs to the consumer. |
| A facade | The client is trivially injectable. |
| A dataset wrapper | See above. |
| Domain-specific variable helpers | See *Variable roles*. A few lines of `array_filter` at the call site. |
| Partial selection, `top(N)`, value filters | No consumer needs them yet; adding later beats deprecating. |
| Multi-agency abstractions | Caller-supplied base URL and table path are the abstraction. |

## Contributing

### Fixtures must stay domain-neutral

The test suite's primary fixture describes a **fictional agency's table**
(Arcadian forestry, in `tests/Fixtures.php`). This is load-bearing, not
decoration.

The package must carry zero domain vocabulary — no postal codes, no prices, no
country. A neutral fixture is the mechanism that keeps it that way: when someone
later wants "just one small helper" for a specific consuming project, a table
about Arcadian forestry makes that assumption fail visibly instead of passing
silently against convenient real-world data. An earlier iteration of this code
carried a `postalVar()` method that detected "the geographic dimension" by
regex-matching five-digit postal codes — a generic-sounding name wrapping
entirely domain-specific behaviour. That is the failure mode this guards against.

Please keep the fixture fictional, and do not introduce a real agency's table.

The fixture's dimensions are also ordered so that the interesting one sits
neither first nor last, which keeps position assumptions from passing by luck.

```bash
composer install
vendor/bin/pest
vendor/bin/pint
```

## License

MIT.
