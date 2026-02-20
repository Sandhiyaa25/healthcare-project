<?php

namespace App\Services;

use App\Models\AuditLog;

class SettingsService
{
    private AuditLog $auditLog;

    public function __construct()
    {
        $this->auditLog = new AuditLog();
    }

    public function getAuditLogs(int $tenantId, array $filters, int $page, int $perPage): array
    {
        return $this->auditLog->getByTenant($tenantId, $filters, $page, $perPage);
    }
}
