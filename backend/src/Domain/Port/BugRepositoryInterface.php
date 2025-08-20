<?php

namespace App\Domain\Port;

use App\Domain\Entity\Bug;

interface BugRepositoryInterface
{
    public function create(Bug $bug): Bug;
}
