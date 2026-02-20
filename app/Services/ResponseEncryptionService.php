<?php

namespace App\Services;

class ResponseEncryptionService
{
    private EncryptionService $encryption;

    public function __construct()
    {
        $this->encryption = new EncryptionService();
    }

    /**
     * Future: Encrypt outgoing response payload.
     * Currently passes through unchanged.
     */
    public function encryptResponse(array $data): array
    {
        // Reserved for future AES response encryption.
        // When enabled, encrypt sensitive fields before JSON output.
        return $data;
    }

    /**
     * Future: Decrypt fields in a response array.
     */
    public function decryptFields(array $data, array $fields): array
    {
        // Reserved for future decryption on SELECT.
        return $data;
    }
}
