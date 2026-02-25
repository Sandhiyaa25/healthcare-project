<?php

namespace App\Middleware;

use Core\Request;

/**
 * SanitizationMiddleware
 *
 * Trims whitespace and removes null bytes from all string input values.
 *
 * NOTE: HTML encoding (htmlspecialchars / strip_tags) is intentionally NOT
 * applied here because this is a JSON API whose responses are consumed by
 * clients, not rendered as HTML. Encoding medical text such as
 * "BP > 120/80" or "WBC < 4000" would corrupt stored clinical data.
 *
 * SQL injection protection is handled at the DB layer via PDO prepared
 * statements throughout all models.
 */
class SanitizationMiddleware
{
    public function handle(Request $request): void
    {
        $sanitized = $this->sanitizeArray($request->body());
        $request->setBody($sanitized);
    }

    private function sanitizeArray(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $clean[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // Remove null bytes (legitimate security concern) and trim whitespace.
                // Do NOT HTML-encode — this is a JSON API, not an HTML renderer.
                $clean[$key] = trim(str_replace("\0", '', $value));
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
