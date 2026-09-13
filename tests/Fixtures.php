<?php

declare(strict_types=1);

/*
 * The suite's primary fixture describes a fictional agency's table.
 *
 * This is load-bearing, not decoration. The package must carry no domain
 * vocabulary, and a neutral fixture is the mechanism that keeps it that way:
 * when someone later adds "just one small helper" for a particular consuming
 * project, a table about Arcadian forestry makes that assumption fail visibly
 * instead of passing silently against convenient real-world data.
 *
 * Keep it fictional. Do not swap in a real agency's table.
 */

if (! function_exists('arcadiaMetadataFixture')) {
    /**
     * @return array{variables: array<int, array<string, mixed>>}
     */
    function arcadiaMetadataFixture(): array
    {
        return ['variables' => [
            ['code' => 'Region', 'values' => ['AR-N', 'AR-S'], 'valueTexts' => ['Northern Arcadia', 'Southern Arcadia']],
            ['code' => 'Year', 'time' => true, 'values' => ['2025'], 'valueTexts' => ['2025']],
            ['code' => 'Species', 'values' => ['total', 'pine'], 'valueTexts' => ['All species', 'Pine']],
            ['code' => 'ContentsCode', 'values' => ['avg_height', 'plots'], 'valueTexts' => ['Mean height', 'Sample plots']],
        ]];
    }
}

if (! function_exists('arcadiaDatasetFixture')) {
    /**
     * Dimension order is deliberately awkward: Region sits neither first nor
     * last, so any code that assumes a fixed dimension position produces wrong
     * indices rather than accidentally-correct ones.
     *
     * @return array<string, mixed>
     */
    function arcadiaDatasetFixture(): array
    {
        return [
            'id' => ['Species', 'Region', 'ContentsCode', 'Year'],
            'size' => [2, 2, 2, 1],
            'dimension' => [
                'Species' => ['category' => ['index' => ['total' => 0, 'pine' => 1]]],
                'Region' => ['category' => ['index' => ['AR-N' => 0, 'AR-S' => 1]]],
                'ContentsCode' => ['category' => ['index' => ['avg_height' => 0, 'plots' => 1]]],
                'Year' => ['category' => ['index' => ['2025' => 0]]],
            ],
            'value' => [24.5, 120, null, 5, 18.0, 60, 20.0, 45], // index 2 suppressed
        ];
    }
}
