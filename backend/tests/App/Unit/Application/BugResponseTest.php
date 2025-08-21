<?php

namespace App\Unit\Application;

use App\Application\DTO\BugResponse;
use PHPUnit\Framework\TestCase;

class BugResponseTest extends TestCase
{
    public function testCanCreateBugResponseWithCodeOnly(): void
    {
        $response = new BugResponse(200);

        $this->assertSame(200, $response->code);
        $this->assertNull($response->message);
    }

    public function testCanCreateBugResponseWithCodeAndMessage(): void
    {
        $response = new BugResponse(404, 'Not Found');

        $this->assertSame(404, $response->code);
        $this->assertSame('Not Found', $response->message);
    }

    public function testCanUpdateMessageProperty(): void
    {
        $response = new BugResponse(500);
        $response->message = 'Server Error';

        $this->assertSame('Server Error', $response->message);
    }
}
