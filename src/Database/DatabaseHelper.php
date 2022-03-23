<?php

declare(strict_types=1);

namespace BehatDoctrineFixtures\Database;

use BehatDoctrineFixtures\Database\Exception\FixtureFileNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\Bridge\Doctrine\Persister\ObjectManagerPersister;
use Fidry\AliceDataFixtures\Loader\PersisterLoader;
use InvalidArgumentException;
use BehatDoctrineFixtures\Database\Manager\DatabaseManager;

class DatabaseHelper
{
    private EntityManagerInterface $entityManager;
    private PersisterLoader $fixturesLoader;
    private DatabaseManagerFactory $databaseManagerFactory;
    private array $databaseFixturePaths;
    private array $excludedTables;
    private string $runMigrationsCommand;
    private string $connectionName;
    private ?DatabaseManager $databaseManager = null;

    public function __construct(
        DatabaseManagerFactory $databaseManagerFactory,
        EntityManagerInterface $entityManager,
        PersisterLoader $fixturesLoader,
        array $databaseFixturePaths,
        array $excludedTables,
        string $runMigrationsCommand,
        string $connectionName
    ) {
        $this->databaseManagerFactory = $databaseManagerFactory;
        $this->entityManager = $entityManager;
        $this->fixturesLoader = $fixturesLoader->withPersister(new ObjectManagerPersister($entityManager));
        $this->databaseFixturePaths = $databaseFixturePaths;
        $this->excludedTables = $excludedTables;
        $this->runMigrationsCommand = $runMigrationsCommand;
        $this->connectionName = $connectionName;
    }

    /**
     * @param array<string> $fixtureAliases
     */
    public function loadFixtures(array $fixtureAliases = []): void
    {
        $fixtures = [];
        foreach ($fixtureAliases as $fixtureAlias) {
            $fixtures[] = $this->resolveFixtureAlias($fixtureAlias);
        }

        $databaseManager = $this->getDatabaseManager();

        if ($databaseManager->backupExists($fixtures)) {
            $databaseManager->loadBackup($fixtures);

            return;
        }

        $databaseManager->prepareSchema();

        if (!empty($fixtures)) {
            $fixturesObjects = $this->fixturesLoader->load($fixtures);

            if (count($fixturesObjects) === 0) {
                throw new InvalidArgumentException(sprintf('Fixtures were not loaded: %s', implode(', ', $fixtures)));
            }

            $databaseManager->saveBackup($fixtures);
        }

        $this->entityManager->clear();
    }

    private function getDatabaseManager(): DatabaseManager
    {
        if ($this->databaseManager === null) {
            $this->databaseManager = $this->databaseManagerFactory->createDatabaseManager(
                $this->entityManager,
                $this->excludedTables,
                $this->runMigrationsCommand,
                $this->connectionName
            );
        }

        return $this->databaseManager;
    }

    private function resolveFixtureAlias(string $fixtureAlias): string
    {
        foreach ($this->databaseFixturePaths as $databaseFixturePath) {
            $fixture = sprintf('%s/%s.yml', $databaseFixturePath, $fixtureAlias);

            if (is_file($fixture)) {
                return $fixture;
            }
        }

        throw new FixtureFileNotFound($fixtureAlias);
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }
}
