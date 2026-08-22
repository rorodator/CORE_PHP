<?php
/**
 * Lang REST scenario runner (isolated boot with fixture label files).
 */

declare(strict_types=1);

namespace Core\Tests\Support;

use Core\Base\RestService;
use Core\Tests\Fixtures\LangCurrentStub;
use Core\Tests\Fixtures\LangGetFrStub;
use Core\Tests\Fixtures\LangSetEnStub;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/rest-scenario-runner.php';
require_once dirname(__DIR__) . '/fixtures/LangTestDoubles.php';

/**
 * @return array{httpCode: int, body: array<string, mixed>|null, raw: string, exitCode: int}
 */
function lang_invoke_scenario(string $scenario): array
{
    $worker = __DIR__ . '/../bin/lang-scenario-worker.php';
    $command = [PHP_BINARY, $worker, $scenario];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2));
    if (!is_resource($process)) {
        throw new \RuntimeException('Failed to start Lang scenario worker.');
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

function lang_run_scenario(string $scenario): void
{
    rest_reset_request_state();

    core_test_boot([
        'services' => [
            'lang'           => \Core\Base\Lang::class,
            'session'        => \Core\Base\Session::class,
            'paramValidator' => \Core\Base\ParamValidator::class,
            'log'            => \Core\Base\Log::class,
        ],
        'lang' => [
            'path'      => dirname(__DIR__) . '/fixtures/lang/',
            'default'   => 'fr',
            'available' => 'fr,en',
        ],
    ]);

    $map = [
        'lang-get'      => [LangGetFrStub::class, 'POST'],
        'lang-set'      => [LangSetEnStub::class, 'POST'],
        'lang-current'  => [LangCurrentStub::class, 'GET'],
    ];

    if (!isset($map[$scenario])) {
        throw new \RuntimeException("Unknown Lang scenario: {$scenario}");
    }

    [$class, $method] = $map[$scenario];
    $_SERVER['REQUEST_METHOD'] = $method;

    /** @var RestService $service */
    $service = new $class();

    register_shutdown_function(static function (): void {
        fwrite(STDERR, 'HTTP_CODE:' . (int)(http_response_code() ?: 200) . "\n");
    });

    $result = $service->handle();

    if (is_array($result)) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_THROW_ON_ERROR);
        exit(0);
    }
}
