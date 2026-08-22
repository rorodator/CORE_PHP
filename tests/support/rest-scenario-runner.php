<?php
/**
 * Execute a RestService scenario inside a CLI worker (handles exit() paths).
 */

declare(strict_types=1);

namespace Core\Tests\Support;

use Core\Base\RestService;
use Core\Tests\Fixtures\AdminGrantedStub;
use Core\Tests\Fixtures\AdminMissingRoleStub;
use Core\Tests\Fixtures\AuthenticatedSuccessStub;
use Core\Tests\Fixtures\CsrfDisabledStub;
use Core\Tests\Fixtures\CsrfFailedStub;
use Core\Tests\Fixtures\CsrfLegacySkipStub;
use Core\Tests\Fixtures\CsrfValidStub;
use Core\Tests\Fixtures\InconsistentAuthenticatedNoAuthStub;
use Core\Tests\Fixtures\InconsistentPublicAuthStub;
use Core\Tests\Fixtures\MethodMatchStub;
use Core\Tests\Fixtures\MethodMismatchStub;
use Core\Tests\Fixtures\MissingHttpMethodStub;
use Core\Tests\Fixtures\MissingSecurityMetadataStub;
use Core\Tests\Fixtures\NonSuccessStatusStub;
use Core\Tests\Fixtures\OwnerDefaultDenyStub;
use Core\Tests\Fixtures\OwnerGrantedStub;
use Core\Tests\Fixtures\RateLimitBucketStub;
use Core\Tests\Fixtures\RateLimitDisabledStub;
use Core\Tests\Fixtures\RateLimitInvalidStub;
use Core\Tests\Fixtures\RateLimitNullStub;
use Core\Tests\Fixtures\SuccessEnvelopeStub;
use Core\Tests\Fixtures\UndefinedSecurityStub;
use Core\Tests\Fixtures\UnauthenticatedStub;
use Core\Tests\Fixtures\UnknownPolicyKeyStub;
use Core\Tests\Fixtures\ValidationArrayItemsStub;
use Core\Tests\Fixtures\ValidationJsonSourceStub;
use Core\Tests\Fixtures\ValidationRequiredStub;
use Core\Tests\Fixtures\ValidationStrictIntStub;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/fixtures/RestStubServices.php';

/**
 * Run a named RestService scenario and normalize the response envelope.
 *
 * @return array{httpCode: int, body: array<string, mixed>|null, raw: string}
 */
function rest_run_scenario(string $scenario): array
{
    rest_reset_request_state();
    core_test_boot();

    $service = rest_create_service($scenario);
    rest_apply_scenario_request($scenario);

    register_shutdown_function(static function (): void {
        $code = http_response_code();
        fwrite(STDERR, 'HTTP_CODE:' . (int)($code ?: 200) . "\n");
    });

    $result = $service->handle();

    if (is_array($result)) {
        $httpCode = (int)($result['status'] ?? 400);
        if (isset($result['error']) && $result['error'] === true && isset($result['message'])) {
            http_response_code($httpCode);
            header('Content-Type: application/json');
            echo json_encode($result, JSON_THROW_ON_ERROR);
            exit(0);
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_THROW_ON_ERROR);
        exit(0);
    }

    return [
        'httpCode' => 200,
        'body'     => null,
        'raw'      => '',
    ];
}

/**
 * Invoke a scenario in a subprocess to capture exit()-based responses.
 *
 * @return array{httpCode: int, body: array<string, mixed>|null, raw: string, exitCode: int}
 */
function rest_invoke_scenario(string $scenario): array
{
    $worker = __DIR__ . '/../bin/rest-scenario-worker.php';
    $command = [
        PHP_BINARY,
        $worker,
        $scenario,
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2));
    if (!is_resource($process)) {
        throw new \RuntimeException('Failed to start RestService scenario worker.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $httpCode = 0;
    if (preg_match('/HTTP_CODE:(\d+)/', (string)$stderr, $matches) === 1) {
        $httpCode = (int)$matches[1];
    }

    $raw = trim((string)$stdout);
    $body = null;
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }

    return [
        'httpCode' => $httpCode,
        'body'     => $body,
        'raw'      => $raw,
        'exitCode' => $exitCode,
    ];
}

/**
 * Reset superglobals that influence RestService behaviour.
 */
function rest_reset_request_state(): void
{
    $_GET = [];
    $_POST = [];
    $_REQUEST = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'POST',
    ];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
}

/**
 * @return RestService
 */
function rest_create_service(string $scenario): RestService
{
    $map = [
        'undefined-security'              => UndefinedSecurityStub::class,
        'missing-http-method'             => MissingHttpMethodStub::class,
        'inconsistent-public-auth'        => InconsistentPublicAuthStub::class,
        'inconsistent-authenticated-auth' => InconsistentAuthenticatedNoAuthStub::class,
        'missing-security-metadata'       => MissingSecurityMetadataStub::class,
        'unknown-policy-key'              => UnknownPolicyKeyStub::class,
        'method-match'                    => MethodMatchStub::class,
        'method-mismatch'                 => MethodMismatchStub::class,
        'unauthenticated'                 => UnauthenticatedStub::class,
        'authenticated-success'           => AuthenticatedSuccessStub::class,
        'owner-default-deny'              => OwnerDefaultDenyStub::class,
        'owner-granted'                   => OwnerGrantedStub::class,
        'admin-missing-role'              => AdminMissingRoleStub::class,
        'admin-granted'                   => AdminGrantedStub::class,
        'csrf-disabled'                   => CsrfDisabledStub::class,
        'csrf-valid'                      => CsrfValidStub::class,
        'csrf-failed'                     => CsrfFailedStub::class,
        'csrf-legacy-skip'                => CsrfLegacySkipStub::class,
        'rate-limit-false'                => RateLimitDisabledStub::class,
        'rate-limit-null'                 => RateLimitNullStub::class,
        'rate-limit-bucket'               => RateLimitBucketStub::class,
        'rate-limit-invalid'              => RateLimitInvalidStub::class,
        'validation-required'               => ValidationRequiredStub::class,
        'validation-strict-int'             => ValidationStrictIntStub::class,
        'validation-json-source'            => ValidationJsonSourceStub::class,
        'validation-array-items'            => ValidationArrayItemsStub::class,
        'success-envelope'                  => SuccessEnvelopeStub::class,
        'non-success-status'                => NonSuccessStatusStub::class,
    ];

    if (!isset($map[$scenario])) {
        throw new \RuntimeException("Unknown RestService scenario: {$scenario}");
    }

    $class = $map[$scenario];
    return new $class();
}

function rest_apply_scenario_request(string $scenario): void
{
    $_SERVER['REQUEST_METHOD'] = 'POST';

    if ($scenario === 'method-mismatch') {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        return;
    }

    if ($scenario === 'validation-required') {
        $_REQUEST = [];
        return;
    }

    if ($scenario === 'validation-strict-int') {
        $_REQUEST = ['count' => '5'];
        return;
    }

    if ($scenario === 'validation-array-items') {
        $_REQUEST = ['tags' => ['alpha', 'beta']];
        return;
    }

    if (in_array($scenario, ['authenticated-success', 'owner-granted', 'admin-granted'], true)) {
        core()->session->set('user', [
            'id'    => 'user-1',
            'roles' => ['admin'],
        ]);
        return;
    }

    if (in_array($scenario, ['owner-default-deny', 'admin-missing-role'], true)) {
        core()->session->set('user', [
            'id'    => 'user-1',
            'roles' => ['member'],
        ]);
        return;
    }

    if ($scenario === 'csrf-valid') {
        core()->session->set('csrf_token', 'token-abc');
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'token-abc';
        return;
    }

    if ($scenario === 'csrf-failed') {
        core()->session->set('csrf_token', 'token-abc');
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong-token';
        return;
    }

    if ($scenario === 'csrf-legacy-skip') {
        // Deliberately no csrf_token in session — legacy skip path.
        return;
    }

    if ($scenario === 'lang-current') {
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}
