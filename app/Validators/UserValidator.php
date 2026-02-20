<?php

namespace App\Validators;

class UserValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['username'])) {
            $errors['username'] = 'Username is required';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = 'Username may only contain letters, numbers and underscores';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (empty($data['role_id']) || !is_numeric($data['role_id'])) {
            $errors['role_id'] = 'Valid role ID is required';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['password'])) {
            $errors['password'] = 'Password cannot be updated via this endpoint';
        }

        if (empty($data['role_id']) || !is_numeric($data['role_id'])) {
            $errors['role_id'] = 'Valid role ID is required';
        }

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }

        return $errors;
    }
}
