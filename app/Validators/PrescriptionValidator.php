<?php

namespace App\Validators;

class PrescriptionValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['patient_id']) || !is_numeric($data['patient_id'])) {
            $errors['patient_id'] = 'Valid patient ID is required';
        }

        if (empty($data['appointment_id']) || !is_numeric($data['appointment_id'])) {
            $errors['appointment_id'] = 'Valid appointment ID is required';
        }

        if (empty($data['medicines']) || !is_array($data['medicines'])) {
            $errors['medicines'] = 'At least one medicine is required';
        } else {
            foreach ($data['medicines'] as $idx => $med) {
                if (empty($med['name'])) {
                    $errors["medicines.{$idx}.name"] = 'Medicine name is required';
                }
                if (empty($med['dosage'])) {
                    $errors["medicines.{$idx}.dosage"] = 'Dosage is required';
                }
            }
        }

        return $errors;
    }
}
