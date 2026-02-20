<?php

namespace App\Validators;

class AuthValidator
{
    public function validateLogin(array $data): array
    {
        $errors = [];

        if (empty($data['tenant_id']) || !is_numeric($data['tenant_id'])) {
            $errors['tenant_id'] = 'Tenant ID is required and must be numeric';
        }

        if (empty($data['username'])) {
            $errors['username'] = 'Username is required';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        }

        return $errors;
    }

    public function validateRegistration(array $data): array
    {
        $errors = [];

        if (empty($data['hospital_name'])) {
            $errors['hospital_name'] = 'Hospital name is required';
        }

        if (empty($data['subdomain'])) {
            $errors['subdomain'] = 'Subdomain is required';
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $data['subdomain'])) {
            $errors['subdomain'] = 'Subdomain must contain only lowercase letters, numbers, and hyphens';
        }

        if (empty($data['contact_email']) || !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'Valid contact email is required';
        }

        if (empty($data['admin_username'])) {
            $errors['admin_username'] = 'Admin username is required';
        }

        if (empty($data['admin_password'])) {
            $errors['admin_password'] = 'Admin password is required';
        } elseif (strlen($data['admin_password']) < 8) {
            $errors['admin_password'] = 'Admin password must be at least 8 characters';
        }

        return $errors;
    }
}
