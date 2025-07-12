<?php

namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;

final readonly class AlphaNumeric implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return ctype_alnum($value);
    }

    public function message(string $field): string
    {
        return "The {$field} may only contain letters and numbers.";
    }
}