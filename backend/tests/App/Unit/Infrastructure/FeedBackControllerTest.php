<?php

namespace App\Tests\App\Unit\Infrastructure;

// 1. IMPORT THE DTO CLASS
use App\Application\DTO\BugResponse;
use App\Application\UseCase\CreateBugUseCase;
use App\Infrastructure\Adapter\Http\Controller\API\FeedBack\FeedBackController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class FeedBackControllerTest extends TestCase
{
    private MockObject|CreateBugUseCase $createBugUseCaseMock;
    private FeedBackController $controller;
    private ?string $tempImagePath;

    protected function setUp(): void
    {
        $this->createBugUseCaseMock = $this->createMock(CreateBugUseCase::class);
        $this->controller = new FeedBackController($this->createBugUseCaseMock);
        $this->tempImagePath = tempnam(sys_get_temp_dir(), 'test_upload_');
    }

    protected function tearDown(): void
    {
        if ($this->tempImagePath && file_exists($this->tempImagePath)) {
            unlink($this->tempImagePath);
        }
        $this->tempImagePath = null;
    }

    public function testIndexActionForwardsDataAndReturnsJsonResponse(): void
    {
        // --- ARRANGE ---

        $postData = ['message' => 'The login button is broken.', 'email' => 'user@example.com'];
        $uploadedFile = new UploadedFile($this->tempImagePath, 'bug_screenshot.png', 'image/png', null, true);
        $request = new Request([], $postData, [], [], ['image' => $uploadedFile]);

        // 2. CREATE AN INSTANCE OF THE BugResponse DTO INSTEAD OF AN ARRAY
        $expectedUseCaseResponse = new BugResponse(123, 'msg');

        // Configure the mock to return the DTO object.
        // PHPUnit will now see this is a valid return value.
        $this->createBugUseCaseMock
            ->expects($this->once())
            ->method('execute')
            ->with($postData, $uploadedFile)
            ->willReturn($expectedUseCaseResponse); // <--- This now returns the correct object type

        // --- ACT ---

        $response = $this->controller->index($request);

        // --- ASSERT ---

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // 3. THE ASSERTION FOR THE JSON CONTENT REMAINS THE SAME
        // Symfony's JsonResponse correctly serializes the DTO object into a JSON string,
        // so we can still compare it against the expected final JSON output.
        $expectedJson = json_encode(['code' => 123, 'message' => "msg"]);
        $this->assertJsonStringEqualsJsonString(
            $expectedJson,
            $response->getContent()
        );

        // Check that all required CORS headers are set correctly.
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('Content-Type, Authorization', $response->headers->get('Access-Control-Allow-Headers'));
    }
}
