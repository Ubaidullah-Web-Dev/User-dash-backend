<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$connectionParams = [
    'url' => $_ENV['DATABASE_URL'],
];

$conn = DriverManager::getConnection($connectionParams);

$companies = [
    ['name' => 'Unique Healthcare Solutions', 'slug' => 'unique-healthcare-solutions'],
    ['name' => 'Acme Inc', 'slug' => 'acme'],
    ['name' => 'Tesla Demo', 'slug' => 'tesla'],
    ['name' => 'Demo Corp', 'slug' => 'demo'],
];

foreach ($companies as $company) {
    try {
        $conn->insert('companies', [
            'name' => $company['name'],
            'slug' => $company['slug'],
            'settings_json' => json_encode([]),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        echo "Inserted: {$company['name']}\n";
    } catch (\Exception $e) {
        echo "Failed to insert {$company['name']}: " . $e->getMessage() . "\n";
    }
}
