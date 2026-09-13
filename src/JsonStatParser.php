<?php

declare(strict_types=1);

namespace LmSomeco\PxWeb;

class JsonStatParser
{
    /**
     * Walks a json-stat2 dataset cell by cell, yielding the category code of
     * every dimension alongside the cell's value.
     *
     * Strides are computed from the dataset's own id/size arrays rather than
     * assumed: dimension order is not fixed across PxWeb tables, and hardcoding
     * a position is a real bug this avoids.
     *
     * @param  array<string, mixed>  $dataset
     * @return iterable<int, array{indices: array<string, string|null>, value: float|int|null}>
     */
    public function rows(array $dataset): iterable
    {
        $ids = array_values($dataset['id'] ?? []);
        $sizes = array_values($dataset['size'] ?? []);
        $dimensionCount = count($ids);

        if ($dimensionCount === 0 || $dimensionCount !== count($sizes)) {
            return;
        }

        $strides = array_fill(0, $dimensionCount, 1);
        for ($k = $dimensionCount - 2; $k >= 0; $k--) {
            $strides[$k] = $strides[$k + 1] * $sizes[$k + 1];
        }

        $codesByDimension = [];
        foreach ($ids as $dimIndex => $dimCode) {
            $index = $dataset['dimension'][$dimCode]['category']['index'] ?? [];
            $codesByDimension[$dimIndex] = $this->orderedCodes($index);
        }

        $values = array_values($dataset['value'] ?? []);
        $total = array_product($sizes);

        for ($flat = 0; $flat < $total; $flat++) {
            $remainder = $flat;
            $indices = [];

            foreach ($ids as $dimIndex => $dimCode) {
                $position = intdiv($remainder, $strides[$dimIndex]);
                $remainder %= $strides[$dimIndex];
                $indices[$dimCode] = $codesByDimension[$dimIndex][$position] ?? null;
            }

            yield ['indices' => $indices, 'value' => $this->normalizeValue($values[$flat] ?? null)];
        }
    }

    private function normalizeValue(mixed $value): float|int|null
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && ! is_numeric($value)) {
            return null; // Suppressed/missing cells ("..", ".", ":") are never real values.
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * Category codes are cast back to string on the way out. json-stat2 carries
     * them as JSON object keys, which are strings by specification, but PHP
     * silently coerces a numeric key like "2025" to int on decode — so a caller
     * comparing $row['indices']['Year'] === '2025' would otherwise fail against
     * a perfectly ordinary time dimension.
     *
     * @param  array<string, int>|array<int, string>  $index
     * @return array<int, string>
     */
    private function orderedCodes(array $index): array
    {
        if (array_is_list($index)) {
            return array_map(strval(...), $index);
        }

        $ordered = [];
        foreach ($index as $code => $position) {
            $ordered[$position] = (string) $code;
        }
        ksort($ordered);

        return array_values($ordered);
    }
}
