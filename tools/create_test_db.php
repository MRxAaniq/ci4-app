<?php

declare(strict_types=1);

// One-time helper to create the MySQL test database used by phpunit.xml.dist.
// Adjust credentials if needed.

$host = getenv('IMS_TEST_DB_HOST') ?: '127.0.0.1';
$user = getenv('IMS_TEST_DB_USER') ?: 'root';
$pass = getenv('IMS_TEST_DB_PASS') ?: '';
$db   = getenv('IMS_TEST_DB_NAME') ?: 'ims_test';

$mysqli = @new mysqli($host, $user, $pass);
if ($mysqli->connect_error) {
    fwrite(STDERR, "Connect error: {$mysqli->connect_error}\n");
    exit(1);
}

if (!$mysqli->query('CREATE DATABASE IF NOT EXISTS `' . $mysqli->real_escape_string($db) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci')) {
    fwrite(STDERR, "Create DB error: {$mysqli->error}\n");
    exit(1);
}

echo "OK: database '{$db}' exists\n";
