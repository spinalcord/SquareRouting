<?php

namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;

final readonly class Email implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid email address.";
    }
}
