<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\Invoice;

class DashboardService
{
    private Patient      $patientModel;
    private Appointment  $appointmentModel;
    private Prescription $prescriptionModel;
    private Invoice      $invoiceModel;

    public function __construct()
    {
        $this->patientModel      = new Patient();
        $this->appointmentModel  = new Appointment();
        $this->prescriptionModel = new Prescription();
        $this->invoiceModel      = new Invoice();
    }

    /**
     * Main dashboard summary.
     * If doctorId is passed → doctor-scoped view (own appointments/prescriptions only).
     * If doctorId is null  → admin view (full tenant data).
     */
    public function getSummary(int $tenantId, ?int $doctorId = null): array
    {
        $appointmentStats  = $this->appointmentModel->getStats($tenantId, $doctorId);
        $prescriptionStats = $this->prescriptionModel->getStats($tenantId, $doctorId);

        // Today's appointments
        $todaysAppts = $this->appointmentModel->getByDateRange(
            $tenantId,
            date('Y-m-d'),
            date('Y-m-d'),
            $doctorId
        );

        $data = [
            'appointments'        => $this->formatStats($appointmentStats),
            'prescriptions'       => $this->formatStats($prescriptionStats),
            'todays_appointments' => count($todaysAppts),
        ];

        // Admin sees full tenant data
        if ($doctorId === null) {
            $billingSummary    = $this->invoiceModel->getSummary($tenantId);
            $data = array_merge([
                'total_patients' => $this->patientModel->count($tenantId),
            ], $data, [
                'billing' => $this->formatBilling($billingSummary),
            ]);
        }

        return $data;
    }

    /**
     * Tenant-wise analytics for a date range — admin only.
     */
    public function getAnalytics(int $tenantId, string $startDate, string $endDate): array
    {
        return [
            'period' => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
            'patients' => [
                'total'     => $this->patientModel->count($tenantId),
                'new'       => $this->patientModel->countByDateRange($tenantId, $startDate, $endDate),
            ],
            'appointments' => [
                'total'     => $this->appointmentModel->countByDateRange($tenantId, $startDate, $endDate),
                'by_status' => $this->formatStats(
                    $this->appointmentModel->getStatsByDateRange($tenantId, $startDate, $endDate)
                ),
            ],
            'prescriptions' => [
                'total'     => $this->prescriptionModel->countByDateRange($tenantId, $startDate, $endDate),
                'by_status' => $this->formatStats(
                    $this->prescriptionModel->getStatsByDateRange($tenantId, $startDate, $endDate)
                ),
            ],
            'billing' => $this->formatBilling(
                $this->invoiceModel->getSummaryByDateRange($tenantId, $startDate, $endDate)
            ),
        ];
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function formatStats(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['count'];
        }
        return $result;
    }

    private function formatBilling(array $rows): array
    {
        if (empty($rows)) {
            return [
                'total_invoices' => 0,
                'total_revenue'  => 0,
                'by_status'      => [],
            ];
        }

        $byStatus     = [];
        $totalInvoices = 0;
        $totalRevenue  = 0;

        foreach ($rows as $row) {
            $byStatus[$row['status']] = [
                'count' => (int)   $row['count'],
                'total' => (float) $row['total'],
            ];
            $totalInvoices += (int)   $row['count'];
            $totalRevenue  += (float) $row['total'];
        }

        return [
            'total_invoices' => $totalInvoices,
            'total_revenue'  => $totalRevenue,
            'by_status'      => $byStatus,
        ];
    }
}