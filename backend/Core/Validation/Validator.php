<?php

declare(strict_types=1);

namespace SquareRouting\Core\Validation;

/**
 * A modern, extensible validation class for PHP 8.3+.
 *
 * Supports nested data and array validation using dot notation.
 */
final class Validator
{
    private array $errors;
    private array $validatedData;
    private array $expandedRules;

    /**
     * @param  array<string, mixed>  $data  The data to be validated.
     * @param  array<string, list<Rule>>  $rules  The validation rules.
     */
    public function __construct(
        private array $data,
        private array $rules
    ) {
        $this->errors = [];
        $this->validatedData = [];
        $this->expandedRules = $this->expandRules($this->rules, $this->data);
    }

    /**
     * Runs the validation process.
     *
     * @return bool True if validation passes, false otherwise.
     */
    public function validate(): bool
    {
        foreach ($this->expandedRules as $field => $fieldRules) {
            $value = $this->getNestedValue($this->data, $field);

            foreach ($fieldRules as $rule) {
                if (! $rule->validate($field, $value, $this->data)) {
                    $this->addError($field, $rule->message($field));

                    // Stop validating this field on the first failure
                    continue 2;
                }
            }

            // If all rules passed, add the value to the validated data set.
            $this->setNestedValue($this->validatedData, $field, $value);
        }

        return empty($this->errors);
    }

    /**
     * A convenient inverse of validate().
     */
    public function fails(): bool
    {
        // We run validate() if it hasn't been run yet.
        if (empty($this->errors) && empty($this->validatedData)) {
            $this->validate();
        }

        return ! empty($this->errors);
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the data that passed validation.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    private function addError(string $field, string $message): void
    {
        // Normalize field name for errors (e.g., items.0.name -> items.*.name)
        $originalField = $this->findOriginalRuleKey($field);
        $this->errors[$originalField][] = $message;
    }

    /**
     * Expands rules with wildcards (*) into specific rules for each array element.
     * Example: 'items.*.name' becomes 'items.0.name', 'items.1.name', etc.
     */
    private function expandRules(array $rules, array $data): array
    {
        $expandedRules = [];
        foreach ($rules as $fieldPattern => $fieldRules) {
            if (! str_contains($fieldPattern, '.*')) {
                $expandedRules[$fieldPattern] = $fieldRules;

                continue;
            }

            // Explode the pattern into parts before and after the first wildcard
            [$prefix, $suffix] = explode('.*.', $fieldPattern, 2);
            $arrayData = $this->getNestedValue($data, $prefix);

            if (! is_array($arrayData)) {
                // If the data at the prefix is not an array, we can't expand.
                // We'll add the original rule, which will likely fail on a type check.
                $expandedRules[$fieldPattern] = $fieldRules;

                continue;
            }

            foreach (array_keys($arrayData) as $index) {
                $newFieldPattern = "{$prefix}.{$index}.{$suffix}";
                // Recursively expand in case of multiple wildcards
                $newExpanded = $this->expandRules([$newFieldPattern => $fieldRules], $data);
                $expandedRules = array_merge($expandedRules, $newExpanded);
            }
        }

        return $expandedRules;
    }

    /**
     * Retrieves a value from a nested array using dot notation.
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $data;
        foreach ($keys as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Sets a value in a nested array using dot notation.
     */
    private function setNestedValue(array &$data, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $temp = &$data;
        foreach ($keys as $segment) {
            if (! isset($temp[$segment]) || ! is_array($temp[$segment])) {
                $temp[$segment] = [];
            }
            $temp = &$temp[$segment];
        }
        $temp = $value;
    }

    /**
     * Finds the original rule key for a generated field key.
     * e.g., for "items.1.name" it finds "items.*.name".
     */
    private function findOriginalRuleKey(string $generatedKey): string
    {
        foreach (array_keys($this->rules) as $ruleKey) {
            $pattern = '/^' . str_replace('\*', '[^\.]+', preg_quote($ruleKey, '/')) . '$/';
            if (preg_match($pattern, $generatedKey)) {
                return $ruleKey;
            }
        }

        return $generatedKey;
    }
}
