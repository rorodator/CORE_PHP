<?php
/**
 * Minimal assertion helpers for standalone CORE_PHP test runners.
 */

declare(strict_types=1);

/**
 * @throws RuntimeException
 */
function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @throws RuntimeException
 */
function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        $detail = sprintf(
            '%s (expected %s, got %s)',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        );
        throw new RuntimeException($detail);
    }
}

/**
 * @param array<string, mixed> $expectedSubset
 * @param array<string, mixed> $actual
 * @throws RuntimeException
 */
function assertArrayHasSubset(array $expectedSubset, array $actual, string $message): void
{
    foreach ($expectedSubset as $key => $value) {
        if (!array_key_exists($key, $actual)) {
            throw new RuntimeException("{$message}: missing key '{$key}'");
        }
        if ($actual[$key] !== $value) {
            throw new RuntimeException(
                "{$message}: key '{$key}' expected "
                . var_export($value, true)
                . ', got '
                . var_export($actual[$key], true)
            );
        }
    }
}
