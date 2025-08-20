<?php

namespace App\Application\useCase;

use App\Application\DTO\BugResponse;
use App\Domain\Service\BugService;

class CreateBugUseCase
{
    public function __construct(private BugService $bugService)
    {
    }

    public function execute(array $data, mixed $files): BugResponse
    {
        $bug = $this->bugService->createBug($data, $files);

        return new BugResponse(201, "id = ".$bug->getId());
    }
}
