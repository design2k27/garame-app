<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

trait ApiResponseTrait
{
    private function errorResponse(
        string $code,
        string $message,
        int $status,
        ?array $details = null
    ): JsonResponse {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        return $this->json($payload, $status);
    }
}
