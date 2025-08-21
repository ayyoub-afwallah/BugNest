<?php

namespace App\Unit\Application\UseCase;

use App\Application\DTO\BugResponse;
use App\Application\UseCase\CreateBugUseCase;
use App\Domain\Entity\Bug;
use App\Domain\Service\BugService;
use PHPUnit\Framework\TestCase;

class CreateBugUseCaseTest extends TestCase
{
    public function testExecuteReturnsBugResponse(): void
    {
        // Prepare input
        $data = ['title' => 'Test Bug'];
        $files = [];

        // Mock Bug entity
        $bugMock = $this->createMock(Bug::class);
        $bugMock->method('getId')->willReturn(123);

        // Mock BugService
        $bugServiceMock = $this->createMock(BugService::class);
        $bugServiceMock
            ->expects($this->once())
            ->method('createBug')
            ->with($data, $files)
            ->willReturn($bugMock);

        // Create use case with mocked service
        $useCase = new CreateBugUseCase($bugServiceMock);

        // Execute
        $response = $useCase->execute($data, $files);

        // Assertions
        $this->assertInstanceOf(BugResponse::class, $response);
        $this->assertSame(201, $response->code);
        $this->assertSame('id = 123', $response->message);
    }
}
