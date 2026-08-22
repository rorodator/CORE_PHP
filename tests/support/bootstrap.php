<?php
/**
 * Shared bootstrap for CORE_PHP characterization tests.
 */

declare(strict_types=1);

$coreRoot = dirname(__DIR__, 2);

spl_autoload_register(static function (string $class) use ($coreRoot): void {
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    $paths = [
        $coreRoot . DIRECTORY_SEPARATOR . $relativePath,
        $coreRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . $relativePath,
    ];
    foreach ($paths as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

require_once $coreRoot . '/Core/core_header.php';

require_once __DIR__ . '/assert.php';

/**
 * Build a minimal Core configuration suitable for contract tests.
 *
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function core_test_config(array $overrides = []): array
{
    $base = [
        'services' => [
            'session'        => \Core\Base\Session::class,
            'paramValidator' => \Core\Base\ParamValidator::class,
            'log'            => \Core\Base\Log::class,
        ],
        'log' => [
            'level' => 'ERROR',
            'file'  => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'core-php-contract-test.log',
        ],
    ];

    return array_replace_recursive($base, $overrides);
}

/**
 * Boot Core in an isolated subprocess-friendly way.
 *
 * @param array<string, mixed> $config
 */
function core_test_boot(array $config = []): \Core\Base\Core
{
    return \Core\Base\Core::bootFromConfig(core_test_config($config));
}
