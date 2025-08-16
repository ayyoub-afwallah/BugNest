<?php

namespace App\Infrastructure\Adapter\Http\Controller\API;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'app_health')]
    public function index(): JsonResponse
    {
        return new JsonResponse('OK');
    }
}
