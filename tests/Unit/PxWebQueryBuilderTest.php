<?php

declare(strict_types=1);

use LmSomeco\PxWeb\PxWebQueryBuilder;

it('selects every value of every variable', function () {
    $query = PxWebQueryBuilder::selectAll(arcadiaMetadataFixture()['variables']);

    expect($query)->toBe([
        ['code' => 'Region', 'selection' => ['filter' => 'all', 'values' => ['*']]],
        ['code' => 'Year', 'selection' => ['filter' => 'all', 'values' => ['*']]],
        ['code' => 'Species', 'selection' => ['filter' => 'all', 'values' => ['*']]],
        ['code' => 'ContentsCode', 'selection' => ['filter' => 'all', 'values' => ['*']]],
    ]);
});

it('does not narrow the content variable', function () {
    $codes = array_column(PxWebQueryBuilder::selectAll(arcadiaMetadataFixture()['variables']), 'code');

    // Callers commonly need more than one measure from a table, so every
    // content value is requested rather than just the first.
    expect($codes)->toContain('ContentsCode');
});

it('returns a list even when the variables arrive keyed', function () {
    $query = PxWebQueryBuilder::selectAll([3 => ['code' => 'Region'], 7 => ['code' => 'Year']]);

    expect(array_keys($query))->toBe([0, 1]);
});

it('returns an empty query for an empty variable list', function () {
    expect(PxWebQueryBuilder::selectAll([]))->toBe([]);
});
