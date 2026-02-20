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
        $filters  = ['patient_id' => $request->query('patient_id'), 'status' => $request->query('status')];
        $page     = (int) $request->query('page', 1);
        $perPage  = (int) $request->query('per_page', 20);

        $invoices = $this->billingService->getAll($tenantId, $filters, $page, $perPage);
        Response::success($invoices, 'Invoices retrieved');
    }

    public function show(Request $request): void
    {
        $tenantId  = (int) $request->getAttribute('auth_tenant_id');
        $invoiceId = (int) $request->param('id');

        $invoice = $this->billingService->getById($invoiceId, $tenantId);
        Response::success($invoice, 'Invoice retrieved');
    }

    public function store(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');

        $invoice = $this->billingService->create($request->all(), $tenantId, $userId, $request->ip(), $request->userAgent());
        Response::created($invoice, 'Invoice created');
    }

    public function recordPayment(Request $request): void
    {
        $tenantId  = (int) $request->getAttribute('auth_tenant_id');
        $userId    = (int) $request->getAttribute('auth_user_id');
        $invoiceId = (int) $request->param('id');

        $invoice = $this->billingService->recordPayment($invoiceId, $request->all(), $tenantId, $userId, $request->ip(), $request->userAgent());
        Response::success($invoice, 'Payment recorded');
    }

    public function summary(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $summary  = $this->billingService->getSummary($tenantId);
        Response::success($summary, 'Billing summary');
    }
}
