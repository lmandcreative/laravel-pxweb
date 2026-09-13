<?php

declare(strict_types=1);

namespace LmSomeco\PxWeb\Exceptions;

class TableResolutionException extends PxWebException
{
    /**
     * @param  array<int, string>  $candidateUrls
     */
    public function __construct(public readonly array $candidateUrls)
    {
        parent::__construct(sprintf(
            'Unable to resolve PxWeb table: none of %d candidate URL(s) responded successfully (%s).',
            count($candidateUrls),
            implode(', ', $candidateUrls),
        ));
    }
}
