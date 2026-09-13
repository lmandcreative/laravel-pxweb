<?php

declare(strict_types=1);

namespace LmSomeco\PxWeb;

/**
 * The two variable roles that are genuine PxWeb protocol conventions.
 *
 * ContentsCode and the time flag are part of how every PxWeb agency describes
 * a table. Anything beyond those two — a "geographic" or "classification"
 * dimension, say — is a property of one particular table, not of the protocol,
 * and belongs at the call site.
 */
final class PxWebVariableRoles
{
    /**
     * @param  array<int, array<string, mixed>>  $variables
     * @return array<string, mixed>|null
     */
    public static function contentVar(array $variables): ?array
    {
        foreach ($variables as $variable) {
            if (strtolower((string) ($variable['code'] ?? '')) === 'contentscode') {
                return $variable;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $variables
     * @return array<string, mixed>|null
     */
    public static function timeVar(array $variables): ?array
    {
        foreach ($variables as $variable) {
            if (($variable['time'] ?? false) === true) {
                return $variable;
            }
        }

        foreach ($variables as $variable) {
            if (preg_match('/^timeperiod/i', (string) ($variable['code'] ?? ''))) {
                return $variable;
            }
        }

        return null;
    }
}
