<?php

declare(strict_types=1);

namespace SquareRouting\Core\Validation\Rules;

use SquareRouting\Core\Interfaces\RuleInterface;

final readonly class PathRouteRule implements RuleInterface
{
    public function validate(string $field, mixed $value, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Erlaube alphanumerische Zeichen, Slashes, Bindestriche, Punkte, Unterstriche und Tilden
        // Entspricht dem alten 'path' Pattern: [a-zA-Z0-9\-\._~/]+
        return preg_match('/^[a-zA-Z0-9\-\._~\/]+$/', $value) === 1;
    }

    public function message(string $field): string
    {
        return "The {$field} may contain letters, numbers, slashes, hyphens, dots, underscores and tildes.";
    }
}