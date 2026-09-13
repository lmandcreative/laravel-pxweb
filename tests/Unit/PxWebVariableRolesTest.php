<?php

declare(strict_types=1);

use LmSomeco\PxWeb\PxWebVariableRoles;

it('matches ContentsCode case-insensitively', function (string $code) {
    $variables = [
        ['code' => 'Region'],
        ['code' => $code, 'values' => ['avg_height']],
    ];

    expect(PxWebVariableRoles::contentVar($variables))->toBe($variables[1]);
})->with(['ContentsCode', 'contentscode', 'CONTENTSCODE', 'ContentsCODE']);

it('finds the content variable in the neutral fixture', function () {
    $content = PxWebVariableRoles::contentVar(arcadiaMetadataFixture()['variables']);

    expect($content['code'])->toBe('ContentsCode')
        ->and($content['values'])->toBe(['avg_height', 'plots']);
});

it('returns null for contentVar when no content variable exists', function () {
    expect(PxWebVariableRoles::contentVar([['code' => 'Region'], ['code' => 'Species']]))->toBeNull();
});

it('returns null for contentVar on an empty variable list', function () {
    expect(PxWebVariableRoles::contentVar([]))->toBeNull();
});

it('prefers the time flag when locating the time variable', function () {
    $variables = [
        ['code' => 'Timeperiod', 'values' => ['2024']],
        ['code' => 'Year', 'time' => true, 'values' => ['2025']],
    ];

    // Both candidates match a rule; the flag wins even though the code-prefix
    // fallback would have matched the earlier entry.
    expect(PxWebVariableRoles::timeVar($variables))->toBe($variables[1]);
});

it('finds the time variable in the neutral fixture', function () {
    expect(PxWebVariableRoles::timeVar(arcadiaMetadataFixture()['variables'])['code'])->toBe('Year');
});

it('falls back to a timeperiod-prefixed code when the flag is absent', function (string $code) {
    $variables = [
        ['code' => 'Region'],
        ['code' => $code, 'values' => ['2025']],
    ];

    expect(PxWebVariableRoles::timeVar($variables))->toBe($variables[1]);
})->with(['Timeperiod', 'timeperiod', 'TIMEPERIOD', 'TimeperiodYear']);

it('ignores a non-true time flag', function (mixed $flag) {
    expect(PxWebVariableRoles::timeVar([['code' => 'Year', 'time' => $flag]]))->toBeNull();
})->with([[false], ['true'], [1], [0], [null]]);

it('returns null for timeVar when no candidate exists', function () {
    expect(PxWebVariableRoles::timeVar([['code' => 'Region'], ['code' => 'Species']]))->toBeNull();
});

it('returns null for timeVar on an empty variable list', function () {
    expect(PxWebVariableRoles::timeVar([]))->toBeNull();
});

it('tolerates variables with no code key at all', function () {
    expect(PxWebVariableRoles::contentVar([['values' => ['x']]]))->toBeNull()
        ->and(PxWebVariableRoles::timeVar([['values' => ['x']]]))->toBeNull();
});
