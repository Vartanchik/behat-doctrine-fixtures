<?php

declare(strict_types=1);

namespace BehatDoctrineFixtures\Tests\Unit\Database\DatabaseManager;

use BehatDoctrineFixtures\Database\Manager\ConsoleManager\PostgreConsoleManager;
use BehatDoctrineFixtures\Database\Manager\PostgreSQLDatabaseManager;
use BehatDoctrineFixtures\Tests\Unit\Database\AbstractDatabaseManagerTest;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PostgreSQLDatabaseManagerTest extends AbstractDatabaseManagerTest
{
    public function testSaveBackupSuccess()
    {
        $cacheDir = 'some/path';
        $databaseName = 'test_database';
        $dumpFilename = sprintf('%s_40cd750bba9870f18aada2478b24840a.sql', $databaseName);
        $password = 'password';
        $user = 'user';
        $host = 'host';
        $port = 5432;
        $excludedTables = ['migration_versions'];
        $additionalParams = "--no-comments --disable-triggers --data-only -T migration_versions";

        $consoleManager = self::createMock(PostgreConsoleManager::class);
        $consoleManager->expects($this->once())
            ->method('createDump')
            ->with(
                sprintf('%s/%s', $cacheDir, $dumpFilename),
                $user,
                $host,
                $port,
                $databaseName,
                $password,
                $additionalParams
            );

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                sprintf('Database backup saved to file %s/%s for default connection', $cacheDir, $dumpFilename),
                ['fixtures' => []]
            );

        $connectionName = 'default';
        $connection = $this->createConnectionMockWithPlatformAndParams(
            PostgreSQL100Platform::class,
            [
                'password' => $password,
                'user' => $user,
                'host' => $host,
                'port' => $port,
                'dbname' => $databaseName
            ]
        );

        $executor = self::createMock(ORMExecutor::class);

        $databaseManager = new PostgreSQLDatabaseManager(
            $consoleManager,
            $executor,
            $connection,
            $logger,
            $excludedTables,
            $cacheDir,
            $connectionName
        );
        $databaseManager->saveBackup([]);
    }

    public function testLoadBackupSuccess()
    {
        $cacheDir = 'some/path';
        $databaseName = 'test_database';
        $dumpFilename = sprintf('%s_25931488cd5177868a29c6e0328e5fc4.sql', $databaseName);
        $password = 'password';
        $user = 'user';
        $host = 'host';
        $port = 5432;

        $consoleManager = self::createMock(PostgreConsoleManager::class);
        $consoleManager->expects($this->once())
            ->method('loadDump')
            ->with(
                sprintf('%s/%s', $cacheDir, $dumpFilename),
                $user,
                $host,
                $port,
                $databaseName,
                $password
            );

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::exactly(4))
            ->method('info')
            ->withConsecutive(
                ['Database created for default connection'],
                ['Migrations ran for default connection'],
                ['Schema created for default connection'],
                [
                    'Database backup loaded for default connection',
                    ['fixtures' => ['TestFixture']]
                ]
            );

        $executor = self::createMock(ORMExecutor::class);
        $executor->expects($this->once())
            ->method('purge');

        $connectionName = 'default';
        $connection = $this->createConnectionMockWithPlatformAndParams(
            PostgreSQL100Platform::class,
            [
                'password' => $password,
                'user' => $user,
                'host' => $host,
                'port' => $port,
                'dbname' => $databaseName
            ]
        );

        $queryResult = self::createMock(Result::class);
        $queryResult
            ->method('fetchAllAssociative')
            ->willReturn([]);
        $connection
            ->method('executeQuery')
            ->willReturn($queryResult);

        $databaseManager = new PostgreSQLDatabaseManager(
            $consoleManager,
            $executor,
            $connection,
            $logger,
            [],
            $cacheDir,
            $connectionName
        );
        $databaseManager->loadBackup(['TestFixture']);
    }

    public function testPrepareSchemaWithNotCreatedSchema(): void
    {
        $cacheDir = 'some/path';
        $databaseName = 'test_database';
        $dumpFilename = sprintf('%s_40cd750bba9870f18aada2478b24840a.sql', $databaseName);
        $password = 'password';
        $user = 'user';
        $host = 'host';
        $port = 5432;
        $additionalParams = "--no-comments --disable-triggers --data-only";

        $consoleManager = self::createMock(PostgreConsoleManager::class);
        $consoleManager->expects(self::once())
            ->method('createDatabase');
        $consoleManager->expects(self::once())
            ->method('runMigrations');
        $consoleManager->expects($this->once())
            ->method('createDump')
            ->with(
                sprintf('%s/%s', $cacheDir, $dumpFilename),
                $user,
                $host,
                $port,
                $databaseName,
                $password,
                $additionalParams
            );

        $connectionName = 'default';
        $connection = $this->createConnectionMockWithPlatformAndParams(
            PostgreSQL100Platform::class,
            [
                'password' => $password,
                'user' => $user,
                'host' => $host,
                'port' => $port,
                'dbname' => $databaseName
            ]
        );

        $executor = self::createMock(ORMExecutor::class);

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::exactly(4))
            ->method('info')
            ->withConsecutive(
                ['Database created for default connection'],
                ['Migrations ran for default connection'],
                ['Schema created for default connection'],
                [
                    sprintf('Database backup saved to file %s/%s for default connection', $cacheDir, $dumpFilename),
                    ['fixtures' => []]
                ]
            );

        $databaseManager = new PostgreSQLDatabaseManager(
            $consoleManager,
            $executor,
            $connection,
            $logger,
            [],
            $cacheDir,
            $connectionName
        );
        $databaseManager->prepareSchema();
    }

    public function testPrepareSchemaWithCreatedSchema(): void
    {
        $cacheDir = 'some/path';
        $databaseName = 'test_database';
        $connectionName = 'default';
        $dumpFilename = sprintf('%s_40cd750bba9870f18aada2478b24840a.sql', $databaseName);
        $password = 'password';
        $user = 'user';
        $host = 'host';
        $port = 5432;
        $additionalParams = "--no-comments --disable-triggers --data-only";

        $consoleManager = self::createMock(PostgreConsoleManager::class);
        $consoleManager->expects(self::once())
            ->method('createDatabase');
        $consoleManager->expects(self::once())
            ->method('runMigrations');
        $consoleManager->expects($this->once())
            ->method('createDump')
            ->with(
                sprintf('%s/%s', $cacheDir, $dumpFilename),
                $user,
                $host,
                $port,
                $databaseName,
                $password,
                $additionalParams
            );

        $connection = $this->createConnectionMockWithPlatformAndParams(
            PostgreSQL100Platform::class,
            [
                'password' => $password,
                'user' => $user,
                'host' => $host,
                'port' => $port,
                'dbname' => $databaseName
            ]
        );

        $executor = self::createMock(ORMExecutor::class);
        $executor->expects($this->exactly(1))
            ->method('purge');

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::exactly(5))
            ->method('info')
            ->withConsecutive(
                ['Database created for default connection'],
                ['Migrations ran for default connection'],
                ['Schema created for default connection'],
                [
                    sprintf('Database backup saved to file %s/%s for default connection', $cacheDir, $dumpFilename),
                    ['fixtures' => []]
                ],
                [
                    'Database backup loaded for default connection',
                    ['fixtures' => []]
                ]
            );

        $databaseManager = new PostgreSQLDatabaseManager(
            $consoleManager,
            $executor,
            $connection,
            $logger,
            [],
            $cacheDir,
            $connectionName
        );
        $databaseManager->prepareSchema();
        $databaseManager->prepareSchema();
    }
}
