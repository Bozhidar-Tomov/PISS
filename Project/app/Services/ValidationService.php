<?php

namespace App\Services;

class ValidationService
{
    public static function validateRegistration(array $data): array
    {
        $requiredFields = ['username', 'password', 'gender', 'role'];
        $errors = self::checkRequiredFields($data, $requiredFields);

        // Username validation
        if (!empty($data['username']) && strlen($data['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters long.';
        }

        if (!empty($data['password'])) {
            $passwordErrors = [];

            if (strlen($data['password']) < 6) {
                $errors[] = 'Password must be at least 6 characters long.';
            }

            if (!preg_match('/[A-Za-z]/', $data['password'])) {
                $passwordErrors[] = 'one letter';
            }

            if (!preg_match('/\d/', $data['password'])) {
                $passwordErrors[] = 'one number';
            }

            if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $data['password'])) {
                $passwordErrors[] = 'one special character';
            }

            if (!empty($passwordErrors)) {
                $errors[] = 'Password must contain at least ' . implode(', ', $passwordErrors) . '.';
            }
        }

        return $errors;
    }

    public static function validateLogin(array $data): array
    {
        $requiredFields = ['username', 'password'];
        $errors = self::checkRequiredFields($data, $requiredFields);

        if (!empty($data['username']) && strlen($data['username']) < 3) {
            $errors[] = 'Username too short.';
        }
        if (!empty($data['password']) && strlen($data['password']) < 6) {
            $errors[] = 'Password too short.';
        }

        return $errors;
    }

    private static function checkRequiredFields(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst($field) . ' is required.';
            }
        }
        return $errors;
    }
}
