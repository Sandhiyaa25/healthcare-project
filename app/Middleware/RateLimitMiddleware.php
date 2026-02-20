<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use Core\Database;

class RateLimitMiddleware
{
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct()
    {
        $config = require ROOT_PATH . '/config/app.php';
        $this->maxRequests   = $config['rate_limit_max'];
        $this->windowSeconds = $config['rate_limit_window'];
    }

    public function handle(Request $request): void
    {
        $ip  = $request->ip();
        $key = 'rate_limit_' . md5($ip);

        // Use APCu if available, else file-based
        if (function_exists('apcu_fetch')) {
            $this->checkWithApcu($key);
        } else {
            $this->checkWithFile($key, $ip);
        }
    }

    private function checkWithApcu(string $key): void
    {
        $data = apcu_fetch($key, $success);

        if (!$success) {
            apcu_store($key, ['count' => 1, 'start' => time()], $this->windowSeconds);
            return;
        }

        if (time() - $data['start'] > $this->windowSeconds) {
            apcu_store($key, ['count' => 1, 'start' => time()], $this->windowSeconds);
            return;
        }

        if ($data['count'] >= $this->maxRequests) {
            Response::tooManyRequests('Too many requests. Please slow down.');
        }

        $data['count']++;
        apcu_store($key, $data, $this->windowSeconds - (time() - $data['start']));
    }

    private function checkWithFile(string $key, string $ip): void
    {
        $dir  = sys_get_temp_dir() . '/healthcare_ratelimit';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . md5($ip) . '.json';
        $now  = time();

        $data = ['count' => 0, 'start' => $now];

        if (file_exists($file)) {
            $stored = json_decode(file_get_contents($file), true);
            if ($stored && ($now - $stored['start']) <= $this->windowSeconds) {
                $data = $stored;
            }
        }

        if ($data['count'] >= $this->maxRequests) {
            Response::tooManyRequests('Too many requests. Please slow down.');
        }

        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
