<?php

declare(strict_types=1);

namespace BehatDoctrineFixtures\Tests\Unit\Database\DatabaseManager;

use BehatDoctrineFixtures\Database\Manager\ConsoleManager\SqliteConsoleManager;
use BehatDoctrineFixtures\Database\Manager\SqliteDatabaseManager;
use BehatDoctrineFixtures\Tests\Unit\Database\AbstractDatabaseManagerTest;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class SqliteDatabaseManagerTest extends AbstractDatabaseManagerTest
{
    public function testSaveBackupSuccess()
    {
        $cacheDir = 'some/path';
        $databasePath = 'test_database';
        $dumpFilename = sprintf('%s_40cd750bba9870f18aada2478b24840a.sql', $databasePath);

        $entityManager = self::createMock(EntityManagerInterface::class);

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Database backup saved', ['fixtures' => []]);

        $connection = $this->createConnectionMockWithPlatformAndParams(
            PostgreSQL100Platform::class,
            [
                'path' => $databasePath
            ]
        );

        $consoleManager = self::createMock(SqliteConsoleManager::class);
        $consoleManager->expects(self::once())
            ->method('copy')
            ->with($databasePath, $dumpFilename);

        $databaseManager = new SqliteDatabaseManager($consoleManager, $entityManager, $connection, $logger, $cacheDir);
        $databaseManager->saveBackup([]);
    }

    public function testLoadBackupSuccess(): void
    {
        $cacheDir = 'some/path';
        $databasePath = 'test_database';
        $dumpFilename = sprintf('%s_40cd750bba9870f18aada2478b24840a.sql', $databasePath);

        $entityManager = self::createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('clear');

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Database backup loaded');

        $connection = $this->createConnectionMockWithPlatformAndParams(
            PostgreSQL100Platform::class,
            [
                'path' => $databasePath
            ]
        );
        $connection->expects(self::once())
            ->method('close');

        $consoleManager = self::createMock(SqliteConsoleManager::class);
        $consoleManager->expects(self::once())
            ->method('copy')
            ->with($dumpFilename, $databasePath);
        $consoleManager->expects(self::once())
            ->method('changeMode')
            ->with($databasePath, 0666);

        $databaseManager = new SqliteDatabaseManager($consoleManager, $entityManager, $connection, $logger, $cacheDir);
        $databaseManager->loadBackup([]);
    }
}
