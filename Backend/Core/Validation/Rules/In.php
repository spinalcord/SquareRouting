<?php

namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;


final readonly class In implements RuleInterface
{
    /** @param list<string|int> $allowedValues */
    public function __construct(private array $allowedValues)
    {
    }

    public function validate(string $field, mixed $value, array $data): bool
    {
        if ($value === null) return true;
        return in_array($value, $this->allowedValues, true);
    }

    public function message(string $field): string
    {
        return "The selected {$field} is invalid.";
    }
}
