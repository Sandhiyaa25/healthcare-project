<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\AiService;

/**
 * AiController — Gemini 2.5 Flash powered endpoints.
 *
 * All routes require standard tenant auth (MW_AUTH + MW_TENANT + MW_CSRF).
 * Every call is audit-logged inside AiService.
 */
class AiController
{
    private AiService $aiService;

    public function __construct()
    {
        $this->aiService = new AiService();
    }

    /**
     * POST /api/ai/patient-summary/{patient_id}
     * Roles: admin, doctor, nurse
     * Returns AI clinical summary. Patient name/DOB/contact NOT sent to Gemini.
     */
    public function patientSummary(Request $request): void
    {
        $role = $request->getAttribute('auth_role');
        if (!in_array($role, ['admin', 'doctor', 'nurse'], true)) {
            Response::forbidden('Only admin, doctors, and nurses can access patient summaries', 'FORBIDDEN');
        }

        $patientId = (int) $request->param('patient_id');
        $tenantId  = (int) $request->getAttribute('auth_tenant_id');
        $userId    = (int) $request->getAttribute('auth_user_id');

        $result = $this->aiService->patientSummary(
            $patientId, $tenantId, $userId, $request->ip(), $request->userAgent()
        );
        Response::success($result, 'Patient summary generated');
    }

    /**
     * POST /api/ai/prescription-suggest
     * Roles: doctor only
     * Body: { diagnosis, symptoms?, age?, gender?, allergies?, notes? }
     */
    public function prescriptionSuggest(Request $request): void
    {
        $role = $request->getAttribute('auth_role');
        if ($role !== 'doctor') {
            Response::forbidden('Only doctors can use prescription suggestions', 'FORBIDDEN');
        }

        $userId = (int) $request->getAttribute('auth_user_id');

        $result = $this->aiService->prescriptionSuggest(
            $request->all(), $userId, $request->ip(), $request->userAgent()
        );
        Response::success($result, 'Prescription suggestions generated');
    }

    /**
     * POST /api/ai/symptom-analyze
     * Roles: doctor, nurse
     * Body: { symptoms, vitals?, age?, gender?, duration?, history? }
     */
    public function symptomAnalyze(Request $request): void
    {
        $role = $request->getAttribute('auth_role');
        if (!in_array($role, ['doctor', 'nurse'], true)) {
            Response::forbidden('Only doctors and nurses can use symptom analysis', 'FORBIDDEN');
        }

        $userId = (int) $request->getAttribute('auth_user_id');

        $result = $this->aiService->symptomAnalyze(
            $request->all(), $userId, $request->ip(), $request->userAgent()
        );
        Response::success($result, 'Symptom analysis generated');
    }

    /**
     * POST /api/ai/explain-diagnosis
     * Roles: all authenticated users (patients included)
     * Body: { diagnosis, treatment? }
     */
    public function explainDiagnosis(Request $request): void
    {
        $userId = (int) $request->getAttribute('auth_user_id');

        $result = $this->aiService->explainDiagnosis(
            $request->all(), $userId, $request->ip(), $request->userAgent()
        );
        Response::success($result, 'Diagnosis explanation generated');
    }
}
