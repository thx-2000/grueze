<?php

declare(strict_types=1);

namespace App\Services;

final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            foreach ($ruleSet as $rule) {
                if ($rule === 'required' && trim((string) $value) === '') {
                    $errors[$field] = 'Bitte dieses Feld ausfüllen.';
                }

                if ($rule === 'email' && $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'Bitte eine gültige E-Mail-Adresse eingeben.';
                }
            }
        }

        return $errors;
    }
}

