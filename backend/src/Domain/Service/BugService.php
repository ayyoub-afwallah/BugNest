<?php

namespace App\Domain\Service;

use App\Application\DTO\BugResponse;
use App\Application\Service\FileStoragePort;
use App\Domain\Entity\Bug;
use App\Domain\Port\BugRepositoryInterface;

class BugService
{
    public function __construct(private BugRepositoryInterface $repository, private FileStoragePort $fileStoragePort)
    {
    }

    public function createBug(array $data, mixed $file): Bug
    {
        $bug = new Bug();

        $data['image'] = $file->getClientOriginalName();

        if($file)
        {
            $content = file_get_contents($file->getPathname());

            $this->fileStoragePort->upload($data['image'], $content);
        }
        $bug->setImage($data['image']);
        $bug->setDescription($data['description']);
        $bug->setTitle($data['title']);

        $this->repository->create($bug);

        return $bug;
    }
}
