<?php

declare(strict_types=1);

// ─── Bootstrap ──────────────────────────────────────────────────────────────

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/core/Env.php';
require ROOT_PATH . '/core/Database.php';
require ROOT_PATH . '/core/Request.php';
require ROOT_PATH . '/core/Response.php';
require ROOT_PATH . '/core/Router.php';
require ROOT_PATH . '/core/MiddlewarePipeline.php';
require ROOT_PATH . '/core/ExceptionHandler.php';

// ─── Autoload ───────────────────────────────────────────────────────────────

spl_autoload_register(function (string $class): void {
    // Convert namespace separators to directory separators
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);

    // Map namespace prefixes to directories
    $prefixes = [
        'Core'    => ROOT_PATH . '/core',
        'App'     => ROOT_PATH . '/app',
        'Config'  => ROOT_PATH . '/config',
    ];

    foreach ($prefixes as $prefix => $dir) {
        $prefixPath = $prefix . DIRECTORY_SEPARATOR;
        if (str_starts_with($class, $prefixPath)) {
            $relative = substr($class, strlen($prefixPath));
            $file     = $dir . DIRECTORY_SEPARATOR . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

\Core\Env::load(ROOT_PATH . '/.env');

// ─── Register exception handler ──────────────────────────────────────────────

\Core\ExceptionHandler::register();

// ─── Middleware aliases ───────────────────────────────────────────────────────

// All available middleware classes (short names for route definitions)
const MW_CORS             = \App\Middleware\CorsMiddleware::class;
const MW_JSON             = \App\Middleware\JsonValidatorMiddleware::class;
const MW_SANITIZE         = \App\Middleware\SanitizationMiddleware::class;
const MW_RATE             = \App\Middleware\RateLimitMiddleware::class;
const MW_AUTH             = \App\Middleware\AuthMiddleware::class;
const MW_CSRF             = \App\Middleware\CsrfMiddleware::class;
const MW_TENANT           = \App\Middleware\TenantMiddleware::class;
const MW_PLATFORM_ADMIN   = \App\Middleware\PlatformAdminMiddleware::class;
const MW_PLATFORM_CSRF    = \App\Middleware\PlatformAdminCsrfMiddleware::class;

// ─── Router ──────────────────────────────────────────────────────────────────

$router  = new \Core\Router();
$request = new \Core\Request();

// ─── Global middleware applied to all routes ─────────────────────────────────
// (CORS + Rate limit applied per-route below for simplicity)

// ─── PUBLIC ROUTES (no auth required) ────────────────────────────────────────

// Health check — test routing works: GET http://localhost/healthcare-project/public/api/health
$router->get('/api/health', function() {
    \Core\Response::success([
        'app'     => 'Healthcare API',
        'status'  => 'running',
        'php'     => PHP_VERSION,
        'script'  => $_SERVER['SCRIPT_NAME'] ?? '',
        'uri_raw' => $_SERVER['REQUEST_URI'] ?? '',
    ], 'API is healthy');
}, []);

// Tenant registration
$router->post('/api/register', [\App\Controllers\RegisterController::class, 'register'], [
    MW_CORS, MW_RATE, MW_JSON, MW_SANITIZE,
]);

// Login
$router->post('/api/auth/login', [\App\Controllers\AuthController::class, 'login'], [
    MW_CORS, MW_RATE, MW_JSON, MW_SANITIZE,
]);

// Refresh token (no CSRF needed - uses HttpOnly cookie)
$router->post('/api/auth/refresh', [\App\Controllers\AuthController::class, 'refresh'], [
    MW_CORS, MW_RATE,
]);

// ─── PROTECTED ROUTES ─────────────────────────────────────────────────────────

// Common protected middleware stack
$protected = [MW_CORS, MW_RATE, MW_JSON, MW_SANITIZE, MW_AUTH, MW_TENANT, MW_CSRF];
$protectedGet = [MW_CORS, MW_RATE, MW_AUTH, MW_TENANT, MW_CSRF];

// ── Auth ──
$router->post('/api/auth/logout',           [\App\Controllers\AuthController::class, 'logout'],         [MW_CORS, MW_RATE, MW_AUTH, MW_TENANT]);
$router->post('/api/auth/csrf/regenerate',  [\App\Controllers\AuthController::class, 'regenerateCsrf'], [MW_CORS, MW_RATE, MW_AUTH, MW_TENANT]);

// ── Users (Admin only) ──
$router->get('/api/users',                    [\App\Controllers\UserController::class, 'index'],              $protectedGet);
$router->get('/api/users/me',                 [\App\Controllers\UserController::class, 'me'],                 $protectedGet); // Gap 1 — must be before /{id}
$router->get('/api/users/{id}',              [\App\Controllers\UserController::class, 'show'],               $protectedGet);
$router->post('/api/users',                  [\App\Controllers\UserController::class, 'store'],              $protected);
$router->put('/api/users/{id}',              [\App\Controllers\UserController::class, 'update'],             $protected);
$router->delete('/api/users/{id}',           [\App\Controllers\UserController::class, 'destroy'],            [MW_CORS, MW_RATE, MW_AUTH, MW_TENANT, MW_CSRF]);
$router->put('/api/users/me/password',       [\App\Controllers\UserController::class, 'changeMyPassword'],   $protected);
$router->put('/api/users/{id}/reset-password', [\App\Controllers\UserController::class, 'adminResetPassword'], $protected);

// ── Patients ──
// Patient self-profile routes (patient role only)
$router->get('/api/patients/me',  [\App\Controllers\PatientController::class, 'myProfile'],       $protectedGet);
$router->put('/api/patients/me',  [\App\Controllers\PatientController::class, 'updateMyProfile'],  $protected);
// Staff routes (admin, doctor, nurse, receptionist)
$router->get('/api/patients',           [\App\Controllers\PatientController::class, 'index'],   $protectedGet);
$router->get('/api/patients/{id}',      [\App\Controllers\PatientController::class, 'show'],    $protectedGet);
$router->post('/api/patients',          [\App\Controllers\PatientController::class, 'store'],   $protected);
$router->put('/api/patients/{id}',      [\App\Controllers\PatientController::class, 'update'],  $protected);
$router->delete('/api/patients/{id}',   [\App\Controllers\PatientController::class, 'destroy'], [MW_CORS, MW_RATE, MW_AUTH, MW_TENANT, MW_CSRF]);

// ── Appointments ──
// Note: /upcoming and /me-style routes MUST be declared BEFORE /{id} routes
$router->get('/api/appointments/upcoming',       [\App\Controllers\AppointmentController::class, 'upcoming'],     $protectedGet);
$router->get('/api/appointments',                [\App\Controllers\AppointmentController::class, 'index'],        $protectedGet);
$router->get('/api/appointments/{id}',           [\App\Controllers\AppointmentController::class, 'show'],         $protectedGet);
$router->post('/api/appointments',               [\App\Controllers\AppointmentController::class, 'store'],        $protected);
$router->put('/api/appointments/{id}',           [\App\Controllers\AppointmentController::class, 'update'],       $protected);
$router->patch('/api/appointments/{id}/cancel',  [\App\Controllers\AppointmentController::class, 'cancel'],       $protected);
$router->patch('/api/appointments/{id}/status',  [\App\Controllers\AppointmentController::class, 'updateStatus'], $protected);
$router->delete('/api/appointments/{id}',        [\App\Controllers\AppointmentController::class, 'destroy'],      [MW_CORS, MW_RATE, MW_AUTH, MW_TENANT, MW_CSRF]); // Gap 2

// ── Prescriptions ──
$router->get('/api/prescriptions',          [\App\Controllers\PrescriptionController::class, 'index'],  $protectedGet);
$router->get('/api/prescriptions/{id}',     [\App\Controllers\PrescriptionController::class, 'show'],   $protectedGet);
$router->post('/api/prescriptions',              [\App\Controllers\PrescriptionController::class, 'store'],   $protected);
$router->put('/api/prescriptions/{id}',          [\App\Controllers\PrescriptionController::class, 'update'],  $protected);                                          // Gap 3
$router->delete('/api/prescriptions/{id}',       [\App\Controllers\PrescriptionController::class, 'destroy'], [MW_CORS, MW_RATE, MW_AUTH, MW_TENANT, MW_CSRF]);      // Gap 4
$router->patch('/api/prescriptions/{id}/verify', [\App\Controllers\PrescriptionController::class, 'verify'],  $protected);

// ── Dashboard ──
$router->get('/api/dashboard',            [\App\Controllers\DashboardController::class, 'index'],     $protectedGet);
$router->get('/api/dashboard/analytics',  [\App\Controllers\DashboardController::class, 'analytics'], $protectedGet);

// ── Messages (Communication) ──
$router->get('/api/messages',                   [\App\Controllers\MessageController::class, 'inbox'],             $protectedGet);                                    // Gap 7 — must be before /{appointment_id}
$router->get('/api/messages/{appointment_id}',  [\App\Controllers\MessageController::class, 'getByAppointment'], $protectedGet);
$router->post('/api/messages',                  [\App\Controllers\MessageController::class, 'store'],            $protected);

// ── Billing ──
$router->get('/api/billing',              [\App\Controllers\BillingController::class, 'index'],         $protectedGet);
$router->get('/api/billing/summary',      [\App\Controllers\BillingController::class, 'summary'],       $protectedGet);
$router->get('/api/billing/{id}',         [\App\Controllers\BillingController::class, 'show'],          $protectedGet);
$router->post('/api/billing',             [\App\Controllers\BillingController::class, 'store'],         $protected);
$router->post('/api/billing/{id}/payment',[\App\Controllers\BillingController::class, 'recordPayment'], $protected);

// ── Staff ──
$router->get('/api/staff',        [\App\Controllers\StaffController::class, 'index'],   $protectedGet);
$router->get('/api/staff/me',     [\App\Controllers\StaffController::class, 'me'],      $protectedGet); // Gap 6 — must be before /{id}
$router->get('/api/staff/{id}',   [\App\Controllers\StaffController::class, 'show'],    $protectedGet);
$router->post('/api/staff',       [\App\Controllers\StaffController::class, 'store'],   $protected);
$router->put('/api/staff/{id}',   [\App\Controllers\StaffController::class, 'update'],  $protected);
$router->delete('/api/staff/{id}',[\App\Controllers\StaffController::class, 'destroy'], [MW_CORS, MW_RATE, MW_AUTH, MW_TENANT, MW_CSRF]);

// ── Calendar ──
// NOTE: static prefix (event/{id}) must be declared BEFORE the {date} wildcard
// so that /api/calendar/event/5 doesn't match {date}='event' first.
$router->get('/api/calendar',             [\App\Controllers\CalendarController::class, 'index'],       $protectedGet);
$router->get('/api/calendar/event/{id}',  [\App\Controllers\CalendarController::class, 'eventDetail'], $protectedGet);
$router->get('/api/calendar/{date}',      [\App\Controllers\CalendarController::class, 'byDate'],      $protectedGet);

// ── Medical Records ──
$router->get('/api/records/patient/{patient_id}', [\App\Controllers\RecordController::class, 'getByPatient'], $protectedGet);
$router->get('/api/records/{id}',                 [\App\Controllers\RecordController::class, 'show'],         $protectedGet);
$router->post('/api/records',                     [\App\Controllers\RecordController::class, 'store'],        $protected);
$router->put('/api/records/{id}',                 [\App\Controllers\RecordController::class, 'update'],       $protected);
$router->patch('/api/records/{id}/archive',       [\App\Controllers\RecordController::class, 'archive'],      $protected); // Gap 5

// ── AI (Gemini 2.5 Flash) ──
$router->post('/api/ai/patient-summary/{patient_id}', [\App\Controllers\AiController::class, 'patientSummary'],     $protected);
$router->post('/api/ai/prescription-suggest',          [\App\Controllers\AiController::class, 'prescriptionSuggest'], $protected);
$router->post('/api/ai/symptom-analyze',               [\App\Controllers\AiController::class, 'symptomAnalyze'],      $protected);
$router->post('/api/ai/explain-diagnosis',             [\App\Controllers\AiController::class, 'explainDiagnosis'],    $protected);

// ── Settings & Audit Logs ──
$router->get('/api/settings/audit-logs',         [\App\Controllers\SettingsController::class, 'auditLogs'],      $protectedGet);
$router->post('/api/settings/csrf/regenerate',   [\App\Controllers\SettingsController::class, 'regenerateCsrf'], [MW_CORS, MW_RATE, MW_AUTH, MW_TENANT]);

// ── Tenant Management (Platform Admin only) ──
// Platform admin middleware stack
$platformProtected    = [MW_CORS, MW_RATE, MW_PLATFORM_ADMIN, MW_PLATFORM_CSRF];
$platformProtectedGet = [MW_CORS, MW_RATE, MW_PLATFORM_ADMIN];

// Platform admin auth (no middleware — public login)
$router->post('/api/platform/login',            [\App\Controllers\PlatformAdminController::class, 'login'],          [MW_CORS, MW_RATE, MW_JSON, MW_SANITIZE]);
$router->post('/api/platform/logout',           [\App\Controllers\PlatformAdminController::class, 'logout'],         [MW_CORS, MW_RATE, MW_PLATFORM_ADMIN]);
$router->get('/api/platform/me',                [\App\Controllers\PlatformAdminController::class, 'me'],             $platformProtectedGet);
$router->post('/api/platform/csrf/regenerate',  [\App\Controllers\PlatformAdminController::class, 'regenerateCsrf'], [MW_CORS, MW_RATE, MW_PLATFORM_ADMIN]);

// Tenant management (platform admin only)
$router->get('/api/platform/tenants',                        [\App\Controllers\TenantController::class, 'index'],      $platformProtectedGet);
$router->get('/api/platform/tenants/{id}',                   [\App\Controllers\TenantController::class, 'show'],       $platformProtectedGet);
$router->patch('/api/platform/tenants/{id}/approve',         [\App\Controllers\TenantController::class, 'approve'],    $platformProtected);
$router->patch('/api/platform/tenants/{id}/suspend',         [\App\Controllers\TenantController::class, 'suspend'],    $platformProtected);
$router->patch('/api/platform/tenants/{id}/reactivate',           [\App\Controllers\TenantController::class, 'reactivate'],        $platformProtected);
$router->post('/api/platform/tenants/{id}/reset-admin-password',  [\App\Controllers\TenantController::class, 'resetAdminPassword'], $platformProtected); // Gap 8

// Tenant roles (used by tenant users, uses regular auth)
$router->get('/api/tenants/roles', [\App\Controllers\TenantController::class, 'getRoles'], $protectedGet);

// ─── Dispatch ────────────────────────────────────────────────────────────────


$router->dispatch($request);