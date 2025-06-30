<?php

declare(strict_types=1);

namespace SquareRouting\Core\Interfaces;

/**
 * Interface that all validation rules must implement.
 */
interface RuleInterface
{
    /**
     * Validate the given value.
     *
     * @param  string  $field  The name of the field being validated.
     * @param  mixed  $value  The value of the field.
     * @param  array<string, mixed>  $data  All the data from the validator.
     * @return bool True if validation passes, false otherwise.
     */
    public function validate(string $field, mixed $value, array $data): bool;

    /**
     * Get the error message for this rule.
     *
     * @param  string  $field  The name of the field that failed validation.
     */
    public function message(string $field): string;
}
