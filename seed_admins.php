<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

$dotenv = new Dotenv();
if (file_exists(__DIR__ . '/.env')) {
    $dotenv->load(__DIR__ . '/.env');
}
if (file_exists(__DIR__ . '/.env.local')) {
    $dotenv->load(__DIR__ . '/.env.local');
}

$dbUrl = $_ENV['DATABASE_URL'] ?? null;
if (!$dbUrl) {
    die("DATABASE_URL not found in .env or .env.local\n");
}

// Manual parse for DBAL 4 compatibility
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

// 1. Ensure companies exist
$companies = [
    ['name' => 'Unique Healthcare Solutions', 'slug' => 'unique-healthcare-solutions'],
    ['name' => 'Acme Inc', 'slug' => 'acme'],
    ['name' => 'Tesla Demo', 'slug' => 'tesla'],
];

$companyIds = [];

foreach ($companies as $comp) {
    try {
        $stmt = $conn->prepare("SELECT id FROM companies WHERE slug = ?");
        $result = $stmt->executeQuery([$comp['slug']])->fetchAssociative();

        if ($result) {
            $companyIds[$comp['slug']] = $result['id'];
            echo "Company exists: {$comp['name']} (ID: {$result['id']})\n";
        } else {
            // Create company
            $conn->insert('companies', [
                'name' => $comp['name'],
                'slug' => $comp['slug'],
                'settings_json' => json_encode([]),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $companyIds[$comp['slug']] = $conn->lastInsertId();
            echo "Created company: {$comp['name']} (ID: {$companyIds[$comp['slug']]})\n";
        }
    } catch (\Exception $e) {
        echo "Error processing company {$comp['slug']}: " . $e->getMessage() . "\n";
    }
}

// 2. Define users
$password = 'password123';
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$users = [
    [
        'email' => 'superadmin@gmail.com',
        'name' => 'Super Admin',
        'roles' => json_encode(['ROLE_SUPER_ADMIN']),
        'company_slug' => 'unique-healthcare-solutions'
    ],
    [
        'email' => 'admin-unique@gmail.com',
        'name' => 'Unique Admin',
        'roles' => json_encode(['ROLE_ADMIN']),
        'company_slug' => 'unique-healthcare-solutions'
    ],
    [
        'email' => 'admin-acme@gmail.com',
        'name' => 'Acme Admin',
        'roles' => json_encode(['ROLE_ADMIN']),
        'company_slug' => 'acme'
    ],
    [
        'email' => 'admin-tesla@gmail.com',
        'name' => 'Tesla Admin',
        'roles' => json_encode(['ROLE_ADMIN']),
        'company_slug' => 'tesla'
    ],
];

foreach ($users as $userData) {
    if (!isset($companyIds[$userData['company_slug']])) {
        echo "Skipping user {$userData['email']} because company {$userData['company_slug']} was not found/created.\n";
        continue;
    }

    $companyId = $companyIds[$userData['company_slug']];

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM user WHERE email = ?");
    $result = $stmt->executeQuery([$userData['email']])->fetchAssociative();

    if ($result) {
        // Update user
        $conn->update('user', [
            'roles' => $userData['roles'],
            'password' => $hashedPassword,
            'name' => $userData['name'],
            'company_id' => $companyId,
        ], ['id' => $result['id']]);
        echo "Updated user: {$userData['email']} (ID: {$result['id']})\n";
    } else {
        // Insert user
        $conn->insert('user', [
            'email' => $userData['email'],
            'roles' => $userData['roles'],
            'password' => $hashedPassword,
            'name' => $userData['name'],
            'company_id' => $companyId,
        ]);
        echo "Created user: {$userData['email']} (ID: " . $conn->lastInsertId() . ")\n";
    }
}

echo "\nSeeding complete!\n";
echo "Default password for all accounts: {$password}\n";
echo "Emails:\n";
foreach ($users as $u) {
    echo "- {$u['email']} ({$u['name']} for {$u['company_slug']})\n";
}
