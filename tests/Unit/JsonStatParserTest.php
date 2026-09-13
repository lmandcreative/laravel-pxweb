<?php

declare(strict_types=1);

use LmSomeco\PxWeb\JsonStatParser;

/**
 * @return array<int, array{indices: array<string, string|null>, value: float|int|null}>
 */
function parseRows(array $dataset): array
{
    return iterator_to_array((new JsonStatParser)->rows($dataset), preserve_keys: false);
}

it('computes indices from the id/size order rather than assuming dimension position', function () {
    $rows = parseRows(arcadiaDatasetFixture());

    // size [2, 2, 2, 1] over id [Species, Region, ContentsCode, Year] gives
    // strides [4, 2, 1, 1]. Flat index 5 therefore resolves to the SECOND
    // Species, the FIRST Region and the SECOND ContentsCode — an order no
    // fixed-position assumption reproduces.
    expect($rows[5]['indices'])->toBe([
        'Species' => 'pine',
        'Region' => 'AR-N',
        'ContentsCode' => 'plots',
        'Year' => '2025',
    ])->and($rows[5]['value'])->toBe(60);

    // The full cross product, in flat order, pinned end to end.
    expect(array_map(fn (array $row): string => implode('/', $row['indices']), $rows))->toBe([
        'total/AR-N/avg_height/2025',
        'total/AR-N/plots/2025',
        'total/AR-S/avg_height/2025',
        'total/AR-S/plots/2025',
        'pine/AR-N/avg_height/2025',
        'pine/AR-N/plots/2025',
        'pine/AR-S/avg_height/2025',
        'pine/AR-S/plots/2025',
    ]);
});

it('yields category codes as strings even when they are numeric', function () {
    // PHP coerces a numeric array key to int on json_decode, so a time
    // dimension indexed by "2025" would otherwise leak an int and break a
    // caller's strict comparison.
    $rows = parseRows(arcadiaDatasetFixture());

    expect($rows[0]['indices']['Year'])->toBe('2025')
        ->and($rows[0]['indices']['Year'])->toBeString();
});

it('yields string codes from a numeric list-form index too', function () {
    $dataset = arcadiaDatasetFixture();
    $dataset['dimension']['Region']['category']['index'] = [2024, 2025];

    expect(parseRows($dataset)[0]['indices']['Region'])->toBe('2024');
});

it('yields one row per cell of the cross product', function () {
    expect(parseRows(arcadiaDatasetFixture()))->toHaveCount(8);
});

it('normalises null cells to null and never to zero', function () {
    $rows = parseRows(arcadiaDatasetFixture());

    expect($rows[2]['value'])->toBeNull()
        ->and($rows[2]['value'])->not->toBe(0)
        ->and($rows[2]['indices']['Region'])->toBe('AR-S');
});

it('normalises non-numeric placeholder strings to null', function (string $placeholder) {
    $dataset = arcadiaDatasetFixture();
    $dataset['value'][0] = $placeholder;

    expect(parseRows($dataset)[0]['value'])->toBeNull();
})->with(['..', '.', ':', '...', '-', 'x']);

it('keeps numeric strings as numbers', function () {
    $dataset = arcadiaDatasetFixture();
    $dataset['value'][0] = '24.5';
    $dataset['value'][1] = '120';

    $rows = parseRows($dataset);

    expect($rows[0]['value'])->toBe(24.5)
        ->and($rows[1]['value'])->toBe(120);
});

it('treats a list-form category index as already position-ordered', function () {
    $dataset = arcadiaDatasetFixture();
    $dataset['dimension']['Region']['category']['index'] = ['AR-N', 'AR-S'];

    $rows = parseRows($dataset);

    expect($rows[0]['indices']['Region'])->toBe('AR-N')
        ->and($rows[2]['indices']['Region'])->toBe('AR-S');
});

it('orders a map-form category index by its positions, not by insertion order', function () {
    $dataset = arcadiaDatasetFixture();
    // Same mapping, declared back to front.
    $dataset['dimension']['Region']['category']['index'] = ['AR-S' => 1, 'AR-N' => 0];

    $rows = parseRows($dataset);

    expect($rows[0]['indices']['Region'])->toBe('AR-N')
        ->and($rows[2]['indices']['Region'])->toBe('AR-S');
});

it('returns nothing when id and size are absent', function () {
    expect(parseRows([]))->toBe([]);
});

it('returns nothing when id and size lengths disagree', function () {
    $dataset = arcadiaDatasetFixture();
    array_pop($dataset['size']);

    expect(parseRows($dataset))->toBe([]);
});

it('yields a null code for a dimension whose category index is missing', function () {
    $dataset = arcadiaDatasetFixture();
    unset($dataset['dimension']['Region']);

    expect(parseRows($dataset)[0]['indices']['Region'])->toBeNull();
});

it('yields a null value for cells beyond the end of the value array', function () {
    $dataset = arcadiaDatasetFixture();
    $dataset['value'] = [24.5];

    $rows = parseRows($dataset);

    expect($rows)->toHaveCount(8)
        ->and($rows[0]['value'])->toBe(24.5)
        ->and($rows[7]['value'])->toBeNull();
});
