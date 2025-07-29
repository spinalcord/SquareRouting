<?php

namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;

final readonly class IsNumber implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        return is_numeric($value);
    }

    public function message(string $field): string
    {
        return "The {$field} must be a number.";
    }
}