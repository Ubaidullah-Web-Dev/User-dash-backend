<?php

namespace App\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/api/admin/database')]
class DatabaseBackupController extends AbstractController
{
    #[Route('/backup', name: 'admin_database_backup', methods: ['GET'])]
    public function backup(Connection $connection): Response
    {
        set_time_limit(0);

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $databaseUrl = $_ENV['DATABASE_URL'] ?? null;
        if (!$databaseUrl) {
            return new JsonResponse(['message' => 'DATABASE_URL not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $parsed = parse_url($databaseUrl);
        if (!$parsed || !isset($parsed['path'])) {
            return new JsonResponse(['message' => 'Invalid DATABASE_URL'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $dbUser = $parsed['user'] ?? '';
        $dbPass = $parsed['pass'] ?? '';
        $dbHost = $parsed['host'] ?? '127.0.0.1';
        $dbPort = $parsed['port'] ?? 3306;
        $dbName = ltrim($parsed['path'], '/');

        if (!$dbName) {
            return new JsonResponse(['message' => 'Database name not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $fileName = 'backup_' . $dbName . '_' . date('Y-m-d_H-i-s') . '.sql';

        // Shared hosting (e.g. InfinityFree) disables proc_open and has no mysqldump binary: dump with PHP instead.
        if (!self::canRunProcesses()) {
            $response = new StreamedResponse(fn () => self::streamPhpDump($connection));
            $response->headers->set('Content-Type', 'application/sql');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

            return $response;
        }

        $response = new StreamedResponse(function () use ($dbUser, $dbPass, $dbHost, $dbPort, $dbName) {
            $command = sprintf(
                'mysqldump --no-tablespaces --single-transaction -h %s -P %s -u %s -p%s %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName)
            );

            $descriptorspec = [
                0 => ["pipe", "r"],  
                1 => ["pipe", "w"],  
                2 => ["pipe", "w"]   
            ];

            $process = proc_open($command, $descriptorspec, $pipes);

            if (is_resource($process)) {
                fclose($pipes[0]);

                while (!feof($pipes[1])) {
                    echo fread($pipes[1], 1024 * 8);
                    flush();
                }
                
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
            }
        });

        $response->headers->set('Content-Type', 'application/sql');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }

    private static function canRunProcesses(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    }

    /** Writes a mysqldump-compatible SQL dump (structure + data of every table) to the output. */
    private static function streamPhpDump(Connection $connection): void
    {
        $quote = static fn (string $name): string => '`' . str_replace('`', '``', $name) . '`';

        echo "-- Database backup generated " . date('Y-m-d H:i:s') . "\n\n";
        echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $connection->fetchFirstColumn("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        foreach ($tables as $table) {
            $name = $quote($table);
            $create = $connection->fetchAssociative('SHOW CREATE TABLE ' . $name);
            echo "DROP TABLE IF EXISTS {$name};\n" . $create['Create Table'] . ";\n\n";

            $result = $connection->executeQuery('SELECT * FROM ' . $name);
            $rows = [];
            while (($row = $result->fetchAssociative()) !== false) {
                $rows[] = '(' . implode(',', array_map(
                    static fn ($value) => $value === null ? 'NULL' : $connection->quote((string) $value),
                    $row
                )) . ')';
                if (count($rows) === 100) {
                    echo "INSERT INTO {$name} VALUES\n" . implode(",\n", $rows) . ";\n";
                    $rows = [];
                    flush();
                }
            }
            if ($rows) {
                echo "INSERT INTO {$name} VALUES\n" . implode(",\n", $rows) . ";\n";
            }
            echo "\n";
            flush();
        }

        echo "SET FOREIGN_KEY_CHECKS=1;\n";
    }
}
