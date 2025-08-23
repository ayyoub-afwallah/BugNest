<?php

namespace App\Tests\App\Unit\Infrastructure;

use App\Infrastructure\Adapter\Http\Controller\API\HealthController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class HealthControllerTest extends TestCase
{
    public function testIndexReturnsOkResponse(): void
    {
        $controller = new HealthController();

        $response = $controller->index();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $this->assertSame(200, $response->getStatusCode());
    }
}
