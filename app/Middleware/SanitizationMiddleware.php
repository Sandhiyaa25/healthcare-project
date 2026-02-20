<?php

namespace App\Middleware;

use Core\Request;

class SanitizationMiddleware
{
    public function handle(Request $request): void
    {
        $body = $request->body();
        $sanitized = $this->sanitizeArray($body);

        // Rebuild body with sanitized values
        foreach ($sanitized as $key => $value) {
            // Inject back - we do it by reflective approach on the body array
        }

        // Set sanitized body back via attribute
        $request->setAttribute('sanitized_body', $sanitized);
    }

    private function sanitizeArray(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $key = htmlspecialchars(strip_tags((string) $key), ENT_QUOTES, 'UTF-8');

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $clean[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
