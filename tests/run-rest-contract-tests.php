#!/usr/bin/env php
<?php
/**
 * Characterization tests for RestService contracts (security, HTTP, CSRF, validation).
 */

declare(strict_types=1);

use Core\Tests\Support;

require_once __DIR__ . '/support/rest-scenario-runner.php';

/**
 * @param array{httpCode?: int, body?: array<string, mixed>|null} $response
 * @param array<string, mixed> $expectedBodySubset
 */
function rest_assert_security_error(array $response, int $expectedHttp, string $expectedStatus, array $expectedBodySubset = []): void
{
    assertSame($expectedHttp, $response['httpCode'] ?? 0, 'Unexpected HTTP status');
    assertTrue(is_array($response['body']), 'Expected JSON body');
    assertArrayHasSubset(
        array_merge(
            [
                'data'   => null,
                'status' => $expectedStatus,
                'error'  => true,
            ],
            $expectedBodySubset
        ),
        $response['body'],
        'Security error envelope'
    );
}

/**
 * @param array{httpCode?: int, body?: array<string, mixed>|null} $response
 * @param array<string, mixed> $expectedBodySubset
 */
function rest_assert_validation_error(array $response, string $expectedMessageFragment): void
{
    assertSame(400, $response['httpCode'] ?? 0, 'Validation must return HTTP 400');
    assertTrue(is_array($response['body']), 'Expected JSON body');
    assertArrayHasSubset(
        [
            'error'  => true,
            'status' => 400,
        ],
        $response['body'],
        'Validation error envelope'
    );
    assertTrue(
        str_contains((string)($response['body']['message'] ?? ''), $expectedMessageFragment),
        'Validation message must mention: ' . $expectedMessageFragment
    );
}

/**
 * @param array{httpCode?: int, body?: array<string, mixed>|null} $response
 * @param array<string, mixed> $expectedDataSubset
 */
function rest_assert_success_envelope(array $response, string $expectedStatus, array $expectedDataSubset = []): void
{
    assertSame(200, $response['httpCode'] ?? 0, 'Success envelope must return HTTP 200');
    assertTrue(is_array($response['body']), 'Expected JSON body');
    assertArrayHasSubset(['status' => $expectedStatus], $response['body'], 'Functional status');
    if ($expectedDataSubset !== []) {
        assertTrue(is_array($response['body']['data'] ?? null), 'Expected data object');
        assertArrayHasSubset($expectedDataSubset, $response['body']['data'], 'Success data');
    }
}

try {
    // 1. Mandatory declarations
    rest_assert_security_error(
        Support\rest_invoke_scenario('undefined-security'),
        500,
        'SECURITY_DECLARATION_ERROR'
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('missing-http-method'),
        500,
        'SECURITY_DECLARATION_ERROR'
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('inconsistent-public-auth'),
        500,
        'SECURITY_DECLARATION_ERROR'
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('inconsistent-authenticated-auth'),
        500,
        'SECURITY_DECLARATION_ERROR'
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('missing-security-metadata'),
        500,
        'SECURITY_DECLARATION_ERROR'
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('unknown-policy-key'),
        500,
        'SECURITY_DECLARATION_ERROR'
    );

    // 2. HTTP method
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('method-match'),
        'SUCCESS',
        ['ok' => true]
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('method-mismatch'),
        405,
        'METHOD_NOT_ALLOWED'
    );

    // 3. Authentication / authorization
    rest_assert_security_error(
        Support\rest_invoke_scenario('unauthenticated'),
        401,
        'UNAUTHENTICATED'
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('owner-default-deny'),
        403,
        'NOT_OWNER'
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('admin-missing-role'),
        403,
        'MISSING_ROLE'
    );
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('authenticated-success'),
        'SUCCESS'
    );
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('owner-granted'),
        'SUCCESS'
    );
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('admin-granted'),
        'SUCCESS'
    );

    // 4. CSRF
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('csrf-disabled'),
        'SUCCESS'
    );
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('csrf-valid'),
        'SUCCESS'
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('csrf-failed'),
        403,
        'CSRF_FAILED'
    );
    // Legacy/current: no csrf_token in session skips verification (may be tightened later).
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('csrf-legacy-skip'),
        'SUCCESS'
    );

    // 5. Rate-limit declaration hook (no counter store in CORE)
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('rate-limit-false'),
        'SUCCESS'
    );
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('rate-limit-null'),
        'SUCCESS'
    );
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('rate-limit-bucket'),
        'SUCCESS'
    );
    rest_assert_security_error(
        Support\rest_invoke_scenario('rate-limit-invalid'),
        500,
        'SECURITY_DECLARATION_ERROR'
    );

    // 6. Validation
    rest_assert_validation_error(
        Support\rest_invoke_scenario('validation-required'),
        'Missing required parameter'
    );
    rest_assert_validation_error(
        Support\rest_invoke_scenario('validation-strict-int'),
        'Invalid strict int'
    );
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('validation-json-source'),
        'SUCCESS'
    );
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('validation-array-items'),
        'SUCCESS'
    );

    // 7. Success contract
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('success-envelope'),
        'SUCCESS',
        ['id' => 42, 'name' => 'sample']
    );
    rest_assert_success_envelope(
        Support\rest_invoke_scenario('non-success-status'),
        'TEAM_EXISTS',
        ['exists' => true]
    );

    fwrite(STDOUT, "CORE_PHP RestService contract tests passed.\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'RestService contract test error: ' . $error->getMessage() . "\n");
    exit(1);
}
