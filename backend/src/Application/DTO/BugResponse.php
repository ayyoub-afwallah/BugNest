<?php

namespace App\Application\DTO;

class BugResponse
{
    public function __construct(public int $code, public ?string $message = null)
    {
    }
}
