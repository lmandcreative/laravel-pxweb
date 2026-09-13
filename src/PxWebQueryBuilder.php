<?php

declare(strict_types=1);

namespace LmSomeco\PxWeb;

final class PxWebQueryBuilder
{
    /**
     * Selects every value of every variable, content (measure) variables
     * included — callers commonly need more than one measure from a table,
     * so the content variable is not narrowed here.
     *
     * @param  array<int, array<string, mixed>>  $variables
     * @return array<int, array<string, mixed>>
     */
    public static function selectAll(array $variables): array
    {
        return array_values(array_map(fn (array $variable): array => [
            'code' => $variable['code'],
            'selection' => ['filter' => 'all', 'values' => ['*']],
        ], $variables));
    }
}
