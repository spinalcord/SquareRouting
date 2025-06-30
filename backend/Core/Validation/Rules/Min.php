<?php

namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;

final readonly class Min implements RuleInterface
{
    public function __construct(private int $length) {}

    public function validate(string $field, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        } // Let 'required' handle empty values

        return match (true) {
            is_string($value) => mb_strlen($value) >= $this->length,
            is_numeric($value) => $value >= $this->length,
            is_array($value) => count($value) >= $this->length,
            default => false,
        };
    }

    public function message(string $field): string
    {
        return "The {$field} must be at least {$this->length} characters/items/value.";
    }
}
