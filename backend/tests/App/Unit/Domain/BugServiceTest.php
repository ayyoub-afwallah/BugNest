<?php

namespace App\Tests\App\Unit\Domain;


use App\Application\Service\FileStoragePort;
use App\Domain\Entity\Bug;
use App\Domain\Port\BugRepositoryInterface;
use App\Domain\Service\BugService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BugServiceTest extends TestCase
{
    private MockObject|BugRepositoryInterface $mockRepository;
    private MockObject|FileStoragePort $mockFileStoragePort;
    private BugService $bugService;

    /**
     * This method is run before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock objects for the dependencies
        $this->mockRepository = $this->createMock(BugRepositoryInterface::class);
        $this->mockFileStoragePort = $this->createMock(FileStoragePort::class);

        // Instantiate the service with the mock dependencies
        $this->bugService = new BugService($this->mockRepository, $this->mockFileStoragePort);
    }

    /**
     * @test
     */
    public function itShouldCreateBugAndUploadFileSuccessfully(): void
    {
        // 1. Arrange: Set up the test data and mock expectations.

        // Input data for the service
        $data = [
            'title' => 'Critical Login Failure',
            'description' => 'Users cannot log in with valid credentials.',
        ];

        // Details for the mock file
        $fileName = 'screenshot.png';
        $fileContent = 'this is dummy image content';

        // To test `file_get_contents`, we need a real temporary file.
        // `tmpfile()` creates a temporary file that is automatically deleted when closed.
        $tempFileHandle = tmpfile();
        $tempFilePath = stream_get_meta_data($tempFileHandle)['uri'];
        fwrite($tempFileHandle, $fileContent);

        // Create a mock of UploadedFile that uses our temporary file.
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('getClientOriginalName')->willReturn($fileName);
        $mockFile->method('getPathname')->willReturn($tempFilePath);

        // Expect the file storage port's `upload` method to be called exactly once
        // with the correct filename and content.
        $this->mockFileStoragePort->expects($this->once())
            ->method('upload')
            ->with($fileName, $fileContent);

        // Expect the repository's `create` method to be called exactly once.
        // We use a callback to inspect the Bug object that is passed to it.
        $this->mockRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (Bug $bug) use ($data, $fileName) {
                // Assert that the Bug object has the correct data before being "saved"
                $this->assertSame($data['title'], $bug->getTitle());
                $this->assertSame($data['description'], $bug->getDescription());
                $this->assertSame($fileName, $bug->getImage());
                return true; // The callback must return true for the test to pass
            }));


        // 2. Act: Execute the method we are testing.
        $resultBug = $this->bugService->createBug($data, $mockFile);


        // 3. Assert: Verify the outcome.

        // Check that the returned object is a Bug instance
        $this->assertInstanceOf(Bug::class, $resultBug);
        // Check that the returned Bug has the correct data
        $this->assertSame($data['title'], $resultBug->getTitle());
        $this->assertSame($data['description'], $resultBug->getDescription());
        $this->assertSame($fileName, $resultBug->getImage());

        // Clean up the temporary file handle
        fclose($tempFileHandle);
    }
}
