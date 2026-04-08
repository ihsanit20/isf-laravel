<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;
use PHPUnit\Framework\SkippedTestError;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $this->ensureMySqlTestingDatabaseExists($app);

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! in_array($feature, config('fortify.features', []), true)) {
            throw new SkippedTestError($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function ensureMySqlTestingDatabaseExists($app): void
    {
        if (($app['config']->get('database.default')) !== 'mysql') {
            return;
        }

        $connection = $app['config']->get('database.connections.mysql');

        if (! is_array($connection) || empty($connection['database'])) {
            return;
        }

        $dsn = $connection['unix_socket'] !== ''
            ? sprintf('mysql:unix_socket=%s;charset=%s', $connection['unix_socket'], $connection['charset'] ?? 'utf8mb4')
            : sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $connection['host'] ?? '127.0.0.1',
                $connection['port'] ?? '3306',
                $connection['charset'] ?? 'utf8mb4',
            );

        $pdo = new PDO(
            $dsn,
            $connection['username'] ?? '',
            $connection['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $database = str_replace('`', '``', (string) $connection['database']);
        $charset = $connection['charset'] ?? 'utf8mb4';
        $collation = $connection['collation'] ?? 'utf8mb4_unicode_ci';

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$collation}");
    }
}
