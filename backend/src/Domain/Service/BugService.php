<?php

namespace App\Domain\Service;

use App\Application\DTO\BugResponse;
use App\Domain\Entity\Bug;
use App\Domain\Port\BugRepositoryInterface;

class BugService
{
    public function __construct(private BugRepositoryInterface $repository)
    {
    }

    public function createBug(array $data, mixed $file): Bug
    {
        $bug = new Bug();

        $data['image'] = $file->getClientOriginalName();

        $bug->setImage($data['image']);
        $bug->setDescription($data['description']);
        $bug->setTitle($data['title']);

        $this->repository->create($bug);

        return $bug;
    }
}
