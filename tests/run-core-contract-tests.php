#!/usr/bin/env php
<?php
/**
 * Characterization tests for Core singleton and configuration contracts.
 */

declare(strict_types=1);

use Core\Base\Core;

require_once __DIR__ . '/support/bootstrap.php';

try {
    // getInstance() must fail before the first boot.
    $threw = false;
    try {
        Core::getInstance();
    } catch (Exception $error) {
        $threw = true;
        assertTrue(
            str_contains($error->getMessage(), 'must provide config path'),
            'First getInstance() without boot must explain missing config path'
        );
    }
    assertTrue($threw, 'Core::getInstance() before boot must throw');

    $configA = core_test_config([
        'app' => [
            'name'    => 'first-boot',
            'version' => '1.0.0',
        ],
        'feature' => [
            'flag' => 'on',
        ],
    ]);

    $coreA = Core::bootFromConfig($configA);
    assertTrue($coreA instanceof Core, 'bootFromConfig must return Core instance');
    assertSame('first-boot', $coreA->getConfigValue('app', 'name'), 'Config value must be readable');
    assertSame('1.0.0', $coreA->getConfigValue('app', 'version'), 'Nested config value must be readable');
    assertSame('on', $coreA->getConfigValue('feature', 'flag'), 'Section value must be readable');
    assertSame('missing-default', $coreA->getConfigValue('feature', 'absent', 'missing-default'), 'Default must be returned for missing keys');

    $section = $coreA->getConfigSection('app');
    assertTrue(is_array($section), 'getConfigSection must return an array');
    assertSame('first-boot', $section['name'] ?? null, 'Section must contain configured keys');
    assertSame([], $coreA->getConfigSection('unknown-section'), 'Unknown section must return empty array');

    $configB = core_test_config([
        'app' => [
            'name' => 'second-boot-ignored',
        ],
    ]);
    $coreB = Core::bootFromConfig($configB);
    assertTrue($coreA === $coreB, 'Second bootFromConfig in same process must return the same singleton');
    assertSame(
        'first-boot',
        $coreB->getConfigValue('app', 'name'),
        'Second boot must not replace configuration from the first boot'
    );

    $merged = Core::mergeConfig(
        [
            'database' => ['host' => '127.0.0.1', 'port' => '3306'],
            'app'      => ['name' => 'base'],
        ],
        [
            'database' => ['port' => '3307', 'dbname' => 'test'],
            'log'      => ['level' => 'ERROR'],
        ]
    );
    assertSame('127.0.0.1', $merged['database']['host'] ?? null, 'mergeConfig must preserve base leaf values');
    assertSame('3307', $merged['database']['port'] ?? null, 'mergeConfig overlay must win on leaf keys');
    assertSame('test', $merged['database']['dbname'] ?? null, 'mergeConfig must add overlay keys');
    assertSame('base', $merged['app']['name'] ?? null, 'mergeConfig must preserve untouched sections');
    assertSame('ERROR', $merged['log']['level'] ?? null, 'mergeConfig must add new sections');

    $bootedCore = core();
    assertTrue($bootedCore === $coreA, 'core() helper must return the booted singleton');

    fwrite(STDOUT, "CORE_PHP Core contract tests passed.\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Core contract test error: ' . $error->getMessage() . "\n");
    exit(1);
}
