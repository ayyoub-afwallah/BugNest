<?php

namespace App\Infrastructure\Adapter\FileStorage;

use App\Application\Service\FileStoragePort;
use League\Flysystem\FilesystemOperator;

class FileStorageAdapter implements FileStoragePort
{

    public function __construct(private FilesystemOperator $bugFilesystem)
    {
    }

    public function upload(string $path, string $content): void
    {
        $this->bugFilesystem->write('bugs/'.$path, $content);
    }

    public function download(string $path): string
    {
        return $this->bugFilesystem->read($path);
    }
}
