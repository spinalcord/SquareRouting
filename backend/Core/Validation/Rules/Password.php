<?php
namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;

final readonly class Password implements RuleInterface
{
    public function __construct(
        private int $minLength = 8,
        private int $maxLength = 128,
        private bool $requireUppercase = true,
        private bool $requireLowercase = true,
        private bool $requireNumbers = true,
        private bool $requireSpecialChars = true
    ) {}

    public function validate(string $field, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        $length = strlen($value);

        // Check length requirements
        if ($length < $this->minLength || $length > $this->maxLength) {
            return false;
        }

        // Check uppercase requirement
        if ($this->requireUppercase && !preg_match('/[A-Z]/', $value)) {
            return false;
        }

        // Check lowercase requirement
        if ($this->requireLowercase && !preg_match('/[a-z]/', $value)) {
            return false;
        }

        // Check numbers requirement
        if ($this->requireNumbers && !preg_match('/[0-9]/', $value)) {
            return false;
        }

        // Check special characters requirement
        if ($this->requireSpecialChars && !preg_match('/[^A-Za-z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    public function message(string $field): string
    {
        $requirements = [];
        
        $requirements[] = "at least {$this->minLength} characters";
        
        if ($this->maxLength < 128) {
            $requirements[] = "at most {$this->maxLength} characters";
        }
        
        if ($this->requireUppercase) {
            $requirements[] = "at least one uppercase letter";
        }
        
        if ($this->requireLowercase) {
            $requirements[] = "at least one lowercase letter";
        }
        
        if ($this->requireNumbers) {
            $requirements[] = "at least one number";
        }
        
        if ($this->requireSpecialChars) {
            $requirements[] = "at least one special character";
        }

        $lastRequirement = array_pop($requirements);
        $requirementText = empty($requirements) 
            ? $lastRequirement 
            : implode(', ', $requirements) . ' and ' . $lastRequirement;

        return "The {$field} must contain {$requirementText}.";
    }
}
