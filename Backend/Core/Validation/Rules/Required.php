<?php

namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;

final readonly class Required implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        if (is_array($value) && empty($value)) {
            return false;
        }
        return true;
    }

    public function message(string $field): string
    {
        return "The {$field} field is required.";
    }
}
