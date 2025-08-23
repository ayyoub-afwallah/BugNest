<?php

namespace App\Tests\App\Unit\Infrastructure;

use App\Domain\Entity\Bug;
use App\Infrastructure\Adapter\Persistence\Doctrine\Repository\BugRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class BugRepositoryTest extends TestCase
{
    public function testCreatePersistsAndFlushesEntity(): void
    {
        $bug = $this->createMock(Bug::class);

        // Mock ClassMetadata and initialize typed property
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->name = Bug::class;

        // Mock EntityManager
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($bug);
        $entityManager->expects($this->once())->method('flush');
        $entityManager->expects($this->any())
            ->method('getClassMetadata')
            ->with(Bug::class)
            ->willReturn($metadata);

        // Mock ManagerRegistry
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(Bug::class)
            ->willReturn($entityManager);

        // Instantiate repository
        $repository = new BugRepository($registry);

        // Act
        $result = $repository->create($bug);

        // Assert
        $this->assertSame($bug, $result);
    }

}
