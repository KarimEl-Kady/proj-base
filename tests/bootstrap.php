<?php

/**
 * PHPUnit's <php><env force="true"> only reliably overrides $_ENV and
 * putenv() — not $_SERVER. PHP's CLI SAPI mirrors real process environment
 * variables into $_SERVER at startup regardless, and Laravel's env() helper
 * checks $_SERVER first. In Docker, docker-compose.yml sets real
 * DB_CONNECTION=mysql/CACHE_STORE=redis/etc. for the app service, so without
 * this, `force="true"` in phpunit.xml is a no-op there: every test silently
 * ran against the live dev database and a real, cross-run-persistent Redis
 * cache — the eventual cause of F19's cross-test rate-limit/DB bleed.
 * Setting $_SERVER explicitly, before vendor/autoload.php ever boots the
 * framework, is the only override that's reliable everywhere.
 */
$testEnvironment = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

/**
 * Deliberate escape hatch for driver-gated tests (e.g.
 * UserFullTextSearchTest) that need a real MySQL/PostgreSQL connection —
 * SQLite has no fulltext equivalent, so without this those tests could
 * never run anywhere. Leaves DB_CONNECTION/DB_DATABASE/DB_URL as whatever
 * the real environment already provides; every other key above (cache,
 * session, queue, mail) stays forced, so this narrows the isolation gap to
 * exactly the one thing being tested rather than reopening all of it.
 *
 * Point DB_DATABASE at a disposable database, never a real dev/shared one
 * — RefreshDatabase runs migrate:fresh against whatever it connects to:
 *
 *   PHPUNIT_DB_DRIVER_TEST=true DB_CONNECTION=mysql DB_DATABASE=proj_base_test \
 *     php artisan test --filter=UserFullTextSearchTest
 */
if (getenv('PHPUNIT_DB_DRIVER_TEST') === 'true') {
    unset($testEnvironment['DB_CONNECTION'], $testEnvironment['DB_DATABASE'], $testEnvironment['DB_URL']);
}

foreach ($testEnvironment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
