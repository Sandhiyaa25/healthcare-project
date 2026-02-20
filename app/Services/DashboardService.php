<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\Invoice;

class DashboardService
{
    private Patient     $patientModel;
    private Appointment $appointmentModel;
    private Prescription $prescriptionModel;
    private Invoice     $invoiceModel;

    public function __construct()
    {
        $this->patientModel     = new Patient();
        $this->appointmentModel = new Appointment();
        $this->prescriptionModel = new Prescription();
        $this->invoiceModel     = new Invoice();
    }

    public function getSummary(int $tenantId): array
    {
        $totalPatients      = $this->patientModel->count($tenantId);
        $appointmentStats   = $this->appointmentModel->getStats($tenantId);
        $prescriptionStats  = $this->prescriptionModel->getStats($tenantId);
        $billingSummary     = $this->invoiceModel->getSummary($tenantId);

        // Today's appointments
        $todaysAppts = $this->appointmentModel->getByDateRange(
            $tenantId,
            date('Y-m-d'),
            date('Y-m-d')
        );

        return [
            'total_patients'     => $totalPatients,
            'appointments'       => $this->formatStats($appointmentStats),
            'prescriptions'      => $this->formatStats($prescriptionStats),
            'billing'            => $billingSummary,
            'todays_appointments'=> count($todaysAppts),
        ];
    }

    private function formatStats(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['count'];
        }
        return $result;
    }
}
