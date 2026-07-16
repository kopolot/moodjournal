<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Keep the test suite self-contained on local machines:
// use a file-backed SQLite DB and in-memory mailer regardless of local Docker env overrides.
$testDbPath = dirname(__DIR__).'/var/test.db';
$testDatabaseUrl = 'sqlite:///' . $testDbPath;

putenv('DATABASE_URL='.$testDatabaseUrl);
$_ENV['DATABASE_URL'] = $testDatabaseUrl;
$_SERVER['DATABASE_URL'] = $testDatabaseUrl;

putenv('MAILER_DSN=null://null');
$_ENV['MAILER_DSN'] = 'null://null';
$_SERVER['MAILER_DSN'] = 'null://null';

putenv('MAILER_FROM_ADDRESS=test@test.moodjournal.com');
$_ENV['MAILER_FROM_ADDRESS'] = 'test@test.moodjournal.com';
$_SERVER['MAILER_FROM_ADDRESS'] = 'test@test.moodjournal.com';

putenv('APP_URL=http://localhost');
$_ENV['APP_URL'] = 'http://localhost';
$_SERVER['APP_URL'] = 'http://localhost';

putenv('REDIS_URL=redis://127.0.0.1:6379');
$_ENV['REDIS_URL'] = 'redis://127.0.0.1:6379';
$_SERVER['REDIS_URL'] = 'redis://127.0.0.1:6379';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
