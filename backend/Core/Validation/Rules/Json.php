<?php

namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;

final readonly class Json implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return json_validate($value);
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid JSON string.";
    }
}
