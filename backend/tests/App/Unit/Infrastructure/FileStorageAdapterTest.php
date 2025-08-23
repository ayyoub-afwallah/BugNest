<?php

namespace App\Tests\App\Unit\Infrastructure;

use App\Infrastructure\Adapter\FileStorage\FileStorageAdapter;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;

class FileStorageAdapterTest extends TestCase
{
    public function testUploadWritesFileToFilesystem(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())
            ->method('write')
            ->with('bugs/test.txt', 'file content');

        $adapter = new FileStorageAdapter($filesystem);
        $adapter->upload('test.txt', 'file content');
    }

    public function testDownloadReadsFileFromFilesystem(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())
            ->method('read')
            ->with('bugs/test.txt')
            ->willReturn('downloaded content');

        $adapter = new FileStorageAdapter($filesystem);
        $result = $adapter->download('bugs/test.txt');

        $this->assertSame('downloaded content', $result);
    }
}
