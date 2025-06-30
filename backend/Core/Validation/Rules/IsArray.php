<?php

namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;

final readonly class IsArray implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        return is_array($value);
    }

    public function message(string $field): string
    {
        return "The {$field} must be an array.";
    }
}
