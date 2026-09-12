<?php

declare(strict_types=1);

namespace ContactWarden\FieldObfuscation;

/** Server-generated random field-name mapping — defeats bots that scrape static field names. */
final class FieldMap
{
    /**
     * @param string[] $realFieldNames
     * @return array<string,string> real name => randomized name
     */
    public static function generate(array $realFieldNames): array
    {
        $map = [];
        foreach ($realFieldNames as $real) {
            $map[$real] = 'f_' . bin2hex(random_bytes(6));
        }

        return $map;
    }

    /**
     * Reverses a submitted body keyed by randomized names back to real field names.
     *
     * @param array<string,string> $fieldMap real => randomized
     * @param array<string,mixed> $submittedBody
     * @return array<string,mixed>
     */
    public static function resolve(array $fieldMap, array $submittedBody): array
    {
        $resolved = [];
        foreach ($fieldMap as $real => $randomized) {
            if (array_key_exists($randomized, $submittedBody)) {
                $resolved[$real] = $submittedBody[$randomized];
            }
        }

        return $resolved;
    }
}
