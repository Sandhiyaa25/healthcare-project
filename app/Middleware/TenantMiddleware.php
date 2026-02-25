<?php

namespace App\Middleware;

use Core\Database;
use Core\Request;
use Core\Response;
use App\Models\Tenant;

class TenantMiddleware
{
    public function handle(Request $request): void
    {
        $tenantId = $request->getAttribute('auth_tenant_id');

        if (!$tenantId) {
            Response::forbidden('Tenant information missing', 'TENANT_MISSING');
        }

        // Verify tenant is active in master DB
        $tenantModel = new Tenant();
        $tenant      = $tenantModel->findActiveById((int) $tenantId);

        if (!$tenant) {
            Response::forbidden('Tenant not found or inactive', 'TENANT_INACTIVE');
        }

        // Switch the active DB connection to this tenant's isolated database
        Database::setCurrentTenant($tenant['db_name']);

        $request->setAttribute('tenant', $tenant);
    }
}
