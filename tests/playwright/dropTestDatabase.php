<?php

// Drops one test session database: php dropTestDatabase.php <database name>

use SilverStripe\Core\Environment;
use SilverStripe\Core\EnvironmentLoader;

require_once __DIR__ . '/../../../../autoload.php';

$name = $argv[1] ?? '';
if (!preg_match('/^[a-z0-9_]*tmpdb_\d+_\d+$/i', $name)) {
    fwrite(STDERR, "\"$name\" is not the name of a test session database\n");
    exit(1);
}

$webroot = dirname(__DIR__, 5);
(new EnvironmentLoader())->loadFile($webroot . '/.env');
$connection = new mysqli(
    Environment::getEnv('SS_DATABASE_SERVER'),
    Environment::getEnv('SS_DATABASE_USERNAME'),
    Environment::getEnv('SS_DATABASE_PASSWORD')
);
$connection->query('DROP DATABASE IF EXISTS `' . $name . '`');
