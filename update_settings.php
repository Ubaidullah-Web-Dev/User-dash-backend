<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

$dotenv = new Dotenv();
if (file_exists(__DIR__ . '/.env')) {
    $dotenv->load(__DIR__ . '/.env');
}

$dbUrl = $_ENV['DATABASE_URL'] ?? null;
if (!$dbUrl) {
    die("DATABASE_URL not found in .env\n");
}

$parsed = parse_url($dbUrl);
$connectionParams = [
    'driver'   => 'pdo_mysql',
    'host'     => $parsed['host'] ?? '127.0.0.1',
    'port'     => $parsed['port'] ?? 3306,
    'user'     => $parsed['user'] ?? 'root',
    'password' => $parsed['pass'] ?? null,
    'dbname'   => isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'symfony',
];

try {
    $conn = DriverManager::getConnection($connectionParams);
    echo "Connected to database.\n";
} catch (\Exception $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

$slug = 'unique-healthcare-solutions';
$settings = [
    "resources" => [
        ["name" => "A1C EZ Hand Meter", "size" => "14 MB", "file" => "A1C-EZ-Hand-Meter-final.pdf"],
        ["name" => "A1c Chek Pro Analyzer", "size" => "5 MB", "file" => "A1c-chek-pro-glycohemoglobin-analyzer-Brouchure.pdf"],
        ["name" => "Brochure AC 310", "size" => "0.9 MB", "file" => "Brochure AC 310.pdf"],
        ["name" => "New GP Getien 1100", "size" => "37 MB", "file" => "New-GP-Getien-1100-Brochure.pdf"],
        ["name" => "RT-9700", "size" => "2.7 MB", "file" => "RT-9700.pdf"]
    ],
    "hero_images" => ["cbc-analyzer.jpg", "chemistry-Analyzer.jpg", "hba1c-analyzer1.jpg"],
    "logo" => "logo.png",
    "show_alphasoft_banner" => "1"
];

try {
    $conn->update('companies', [
        'settings_json' => json_encode($settings),
        'updated_at' => date('Y-m-d H:i:s'),
    ], ['slug' => $slug]);
    echo "Updated settings for company: $slug\n";
} catch (\Exception $e) {
    echo "Error updating company $slug: " . $e->getMessage() . "\n";
}
