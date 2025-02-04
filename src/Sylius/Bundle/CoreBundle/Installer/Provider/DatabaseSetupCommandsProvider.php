<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Bundle\CoreBundle\Installer\Provider;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

final class DatabaseSetupCommandsProvider implements DatabaseSetupCommandsProviderInterface
{
    public function __construct(private Registry $doctrineRegistry)
    {
    }

    public function getCommands(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper): array
    {
        if (!$this->isDatabasePresent()) {
            return [
                'doctrine:database:create',
                'doctrine:migrations:migrate' => ['--no-interaction' => true],
            ];
        }

        return array_merge($this->setupDatabase($input, $output, $questionHelper), [
            'doctrine:migrations:version' => [
                '--add' => true,
                '--all' => true,
                '--no-interaction' => true,
            ],
        ]);
    }

    /**
     * @throws \Exception
     */
    private function isDatabasePresent(): bool
    {
        $databaseName = $this->getDatabaseName();

        try {
            $schemaManager = $this->getSchemaManager();

            return in_array($databaseName, $schemaManager->listDatabases());
        } catch (\Exception $exception) {
            $message = $exception->getMessage();

            $mysqlDatabaseError = str_contains($message, sprintf("Unknown database '%s'", $databaseName));
            $postgresDatabaseError = str_contains($message, sprintf('database "%s" does not exist', $databaseName));

            if ($mysqlDatabaseError || $postgresDatabaseError) {
                return false;
            }

            throw $exception;
        }
    }

    private function setupDatabase(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper): array
    {
        $outputStyle = new SymfonyStyle($input, $output);
        $outputStyle->writeln('It appears that your database already exists.');
        $outputStyle->writeln('<error>Warning! This action will erase your database.</error>');

        $question = new ConfirmationQuestion('Would you like to reset it? (y/N) ', false);
        if ($questionHelper->ask($input, $output, $question)) {
            return [
                'doctrine:database:drop' => ['--force' => true],
                'doctrine:database:create',
                'doctrine:migrations:migrate' => ['--no-interaction' => true],
            ];
        }

        if (!$this->isSchemaPresent()) {
            return ['doctrine:migrations:migrate' => ['--no-interaction' => true]];
        }

        $outputStyle->writeln('Seems like your database contains schema.');
        $outputStyle->writeln('<error>Warning! This action will erase your database.</error>');
        $question = new ConfirmationQuestion('Do you want to reset it? (y/N) ', false);
        if ($questionHelper->ask($input, $output, $question)) {
            return [
                'doctrine:schema:drop' => ['--force' => true],
                'doctrine:migrations:migrate' => ['--no-interaction' => true],
            ];
        }

        return [];
    }

    private function isSchemaPresent(): bool
    {
        return 0 !== count($this->getSchemaManager()->listTableNames());
    }

    private function getDatabaseName(): string
    {
        return $this->getEntityManager()->getConnection()->getDatabase();
    }

    private function getSchemaManager(): AbstractSchemaManager
    {
        $connection = $this->getEntityManager()->getConnection();

        if (method_exists($connection, 'createSchemaManager')) {
            return $connection->createSchemaManager();
        }

        if (method_exists($connection, 'getSchemaManager')) {
            /** @psalm-suppress DeprecatedMethod */
            return $connection->getSchemaManager();
        }

        throw new \RuntimeException('Unable to get schema manager.');
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $objectManager = $this->doctrineRegistry->getManager();
        Assert::isInstanceOf($objectManager, EntityManagerInterface::class);

        return $objectManager;
    }
}
