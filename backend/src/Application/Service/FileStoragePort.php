<?php

namespace App\Application\Service;

interface FileStoragePort
{
    public function upload(string $path, string $content): void;
    public function download(string $path): string;
}
