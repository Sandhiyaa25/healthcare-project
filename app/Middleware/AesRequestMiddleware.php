<?php

namespace App\Middleware;

use Core\Request;

class AesRequestMiddleware
{
    public function handle(Request $request): void
    {
        // AES request/response encryption is reserved for future implementation.
        // Structure is in place. Encryption will be applied on INSERT/UPDATE
        // and decryption on SELECT once enabled.
        //
        // When enabled:
        // 1. Decrypt incoming AES-encrypted body
        // 2. Parse decrypted JSON
        // 3. Pass to controller
        // Output responses will be encrypted in ResponseEncryptionService.
    }
}
