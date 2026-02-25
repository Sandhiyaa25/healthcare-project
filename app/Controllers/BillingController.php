<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\BillingService;

class BillingController
{
    private BillingService $billingService;

    public function __construct()
    {
        $this->billingService = new BillingService();
    }

    public function index(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $role     = $request->getAttribute('auth_role');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $page     = (int) $request->query('page', 1);
        $perPage  = min(max((int) $request->query('per_page', 20), 1), 100);

        // Patient: only see their own invoices — filtered inside service
        if ($role === 'patient') {
            $invoices = $this->billingService->getAllForPatient($tenantId, $userId, $page, $perPage);
            Response::success($invoices, 'Your invoices retrieved');
            return;
        }

        $filters = ['patient_id' => $request->query('patient_id'), 'status' => $request->query('status')];
        $invoices = $this->billingService->getAll($tenantId, $filters, $page, $perPage);
        Response::success($invoices, 'Invoices retrieved');
    }

    public function show(Request $request): void
    {
        $tenantId  = (int) $request->getAttribute('auth_tenant_id');
        $role      = $request->getAttribute('auth_role');
        $userId    = (int) $request->getAttribute('auth_user_id');
        $invoiceId = (int) $request->param('id');

        $invoice = $this->billingService->getById($invoiceId, $tenantId);

        // Patient: verify this invoice belongs to their own patient record
        if ($role === 'patient') {
            $this->billingService->assertPatientOwnsInvoice($invoice, $userId, $tenantId);
        }

        Response::success($invoice, 'Invoice retrieved');
    }

    public function store(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $role     = $request->getAttribute('auth_role');

        if ($role === 'patient') {
            Response::forbidden('Patients cannot create invoices', 'FORBIDDEN');
        }

        $invoice = $this->billingService->create($request->all(), $tenantId, $userId, $request->ip(), $request->userAgent());
        Response::created($invoice, 'Invoice created');
    }

    public function recordPayment(Request $request): void
    {
        $tenantId  = (int) $request->getAttribute('auth_tenant_id');
        $userId    = (int) $request->getAttribute('auth_user_id');
        $role      = $request->getAttribute('auth_role');
        $invoiceId = (int) $request->param('id');

        if (in_array($role, ['patient', 'pharmacist'])) {
            Response::forbidden('Only admin or receptionist can record payments', 'FORBIDDEN');
        }

        $invoice = $this->billingService->recordPayment($invoiceId, $request->all(), $tenantId, $userId, $request->ip(), $request->userAgent());
        Response::success($invoice, 'Payment recorded');
    }

    public function summary(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $role     = $request->getAttribute('auth_role');

        if ($role === 'patient') {
            Response::forbidden('Access denied', 'FORBIDDEN');
        }

        $summary = $this->billingService->getSummary($tenantId);
        Response::success($summary, 'Billing summary');
    }
}
