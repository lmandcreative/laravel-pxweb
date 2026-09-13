<?php

declare(strict_types=1);

namespace LmSomeco\PxWeb;

final class PxWebTable
{
    /**
     * @param  array<int, array<string, mixed>>  $variables
     */
    public function __construct(
        public readonly string $url,
        public readonly array $variables,
    ) {}
}
