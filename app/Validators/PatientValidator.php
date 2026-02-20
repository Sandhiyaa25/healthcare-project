<?php

namespace App\Validators;

class PatientValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name is required';
        }

        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required';
        }

        if (empty($data['date_of_birth'])) {
            $errors['date_of_birth'] = 'Date of birth is required';
        } elseif (!strtotime($data['date_of_birth'])) {
            $errors['date_of_birth'] = 'Invalid date format';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        if (empty($data['gender']) || !in_array($data['gender'], ['male', 'female', 'other'])) {
            $errors['gender'] = 'Gender must be male, female, or other';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        if (isset($data['date_of_birth']) && !strtotime($data['date_of_birth'])) {
            $errors['date_of_birth'] = 'Invalid date format';
        }

        if (isset($data['gender']) && !in_array($data['gender'], ['male', 'female', 'other'])) {
            $errors['gender'] = 'Gender must be male, female, or other';
        }

        return $errors;
    }
}
