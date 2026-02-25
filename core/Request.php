<?php

namespace Core;

class Request
{
    private array  $body    = [];
    private array  $query   = [];
    private array  $headers = [];
    private array  $params  = [];
    private string $method  = 'GET';
    private string $uri     = '/';

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri     = $this->parseUri();
        $this->query   = $_GET  ?? [];
        $this->headers = $this->parseHeaders();
        $this->body    = $this->parseBody();
    }

    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Strip query string
        $uri = parse_url($uri, PHP_URL_PATH);

        // ── Strip subfolder base path ──────────────────────────────────────
        // When running under WAMP in a subfolder like:
        //   http://localhost/healthcare-project/public/api/login
        // PHP sees REQUEST_URI = /healthcare-project/public/api/login
        // SCRIPT_NAME          = /healthcare-project/public/index.php
        //
        // We derive the base path from SCRIPT_NAME directory and strip it
        // so the router always works with clean paths like /api/login
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');

        // Normalize: remove trailing slash from base (but keep root '/')
        $scriptDir = rtrim($scriptDir, '/');

        // Strip the base directory prefix from the URI if present
        if ($scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }

        // Ensure leading slash, strip trailing slash (keep root as '/')
        $uri = '/' . ltrim($uri, '/');
        $uri = rtrim($uri, '/') ?: '/';

        return $uri;
    }

    private function parseHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }
        } else {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $name = strtolower(str_replace('_', '-', substr($key, 5)));
                    $headers[$name] = $value;
                }
            }
        }

        return $headers;
    }

    private function parseBody(): array
    {
        if (in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $this->header('content-type') ?? '';

            if (str_contains($contentType, 'application/json')) {
                $raw = file_get_contents('php://input');
                return json_decode($raw, true) ?? [];
            }

            return $_POST ?? [];
        }

        return [];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function setBody(array $body): void
    {
        $this->body = $body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');

        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }

    public function csrfToken(): ?string
    {
        return $this->header('x-csrf-token');
    }

    public function ip(): string
    {
        // Use only REMOTE_ADDR — the address of the directly connected client.
        // HTTP_X_FORWARDED_FOR and HTTP_CLIENT_IP are client-controlled headers
        // that can be trivially spoofed to bypass IP-based rate limiting.
        // If this API is later deployed behind a trusted reverse proxy, add
        // proxy IP validation before trusting any forwarded header.
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    // Allow middleware to set attributes
    private array $attributes = [];

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}