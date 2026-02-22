<?php

namespace App\Validators;

class PatientValidator
{
    private const ALLOWED_BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

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
        } elseif (strtotime($data['date_of_birth']) > time()) {
            // Bug 23: date of birth cannot be in the future
            $errors['date_of_birth'] = 'Date of birth cannot be in the future';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        if (empty($data['gender']) || !in_array($data['gender'], ['male', 'female', 'other'])) {
            $errors['gender'] = 'Gender must be male, female, or other';
        }

        // Bug 23: phone format validation
        if (!empty($data['phone']) && !preg_match('/^\+?[0-9\s\-\(\)]{7,15}$/', $data['phone'])) {
            $errors['phone'] = 'Invalid phone number format';
        }

        // Bug 23: blood_group allowed values
        if (!empty($data['blood_group']) && !in_array($data['blood_group'], self::ALLOWED_BLOOD_GROUPS)) {
            $errors['blood_group'] = 'Invalid blood group. Allowed: ' . implode(', ', self::ALLOWED_BLOOD_GROUPS);
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        if (isset($data['date_of_birth'])) {
            if (!strtotime($data['date_of_birth'])) {
                $errors['date_of_birth'] = 'Invalid date format';
            } elseif (strtotime($data['date_of_birth']) > time()) {
                // Bug 23: date of birth cannot be in the future
                $errors['date_of_birth'] = 'Date of birth cannot be in the future';
            }
        }

        if (isset($data['gender']) && !in_array($data['gender'], ['male', 'female', 'other'])) {
            $errors['gender'] = 'Gender must be male, female, or other';
        }

        // Bug 23: phone format validation
        if (!empty($data['phone']) && !preg_match('/^\+?[0-9\s\-\(\)]{7,15}$/', $data['phone'])) {
            $errors['phone'] = 'Invalid phone number format';
        }

        // Bug 23: blood_group allowed values
        if (!empty($data['blood_group']) && !in_array($data['blood_group'], self::ALLOWED_BLOOD_GROUPS)) {
            $errors['blood_group'] = 'Invalid blood group. Allowed: ' . implode(', ', self::ALLOWED_BLOOD_GROUPS);
        }

        return $errors;
    }
}
