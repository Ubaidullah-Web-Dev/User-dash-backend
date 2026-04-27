<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/api/admin/database')]
class DatabaseBackupController extends AbstractController
{
    #[Route('/backup', name: 'admin_database_backup', methods: ['GET'])]
    public function backup(): Response
    {
        // Prevent PHP from timing out for large databases
        set_time_limit(0);

        // Enforce that only authorized admins can access this endpoint
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

        $response = new StreamedResponse(function () use ($dbUser, $dbPass, $dbHost, $dbPort, $dbName) {
            // --no-tablespaces: Avoids issues on systems where the user lacks PROCESS privileges
            // --single-transaction: Ensures consistent backup for InnoDB without locking tables
            $command = sprintf(
                'mysqldump --no-tablespaces --single-transaction -h %s -P %s -u %s -p%s %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName)
            );

            $descriptorspec = [
                0 => ["pipe", "r"],  // stdin
                1 => ["pipe", "w"],  // stdout
                2 => ["pipe", "w"]   // stderr
            ];

            $process = proc_open($command, $descriptorspec, $pipes);

            if (is_resource($process)) {
                fclose($pipes[0]);

                // Stream the output directly to the response
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
}
