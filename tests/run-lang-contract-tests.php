#!/usr/bin/env php
<?php
/**
 * Characterization tests for Lang REST envelope differences (get/set/current).
 */

declare(strict_types=1);

use Core\Tests\Support;

require_once __DIR__ . '/support/lang-scenario-runner.php';

try {
    // lang/get — canonical {data, status} envelope via sendJson().
    $getResponse = Support\lang_invoke_scenario('lang-get');
    assertSame(200, $getResponse['httpCode'], 'LangGetService must return HTTP 200');
    assertTrue(is_array($getResponse['body']), 'LangGetService must return JSON');
    assertArrayHasSubset(['status' => 'SUCCESS'], $getResponse['body'], 'LangGetService envelope');
    assertTrue(isset($getResponse['body']['data']['labels']), 'LangGetService must expose labels in data');
    assertTrue(!isset($getResponse['body']['success']), 'LangGetService must not use success flag today');

    // lang/set — legacy {success, data} envelope (returned by handle(), not sendJson).
    $setResponse = Support\lang_invoke_scenario('lang-set');
    assertSame(200, $setResponse['httpCode'], 'LangSetService must return HTTP 200');
    assertArrayHasSubset(['success' => true], $setResponse['body'] ?? [], 'LangSetService success flag');
    assertTrue(isset($setResponse['body']['data']['lang']), 'LangSetService must return lang in data');
    assertTrue(!isset($setResponse['body']['status']), 'LangSetService must not use status field today');

    // lang/current — legacy {success, data} envelope.
    $currentResponse = Support\lang_invoke_scenario('lang-current');
    assertSame(200, $currentResponse['httpCode'], 'LangCurrentService must return HTTP 200');
    assertArrayHasSubset(['success' => true], $currentResponse['body'] ?? [], 'LangCurrentService success flag');
    assertTrue(isset($currentResponse['body']['data']['currentLang']), 'LangCurrentService must expose currentLang');
    assertTrue(!isset($currentResponse['body']['status']), 'LangCurrentService must not use status field today');

    fwrite(STDOUT, "CORE_PHP Lang contract tests passed.\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Lang contract test error: ' . $error->getMessage() . "\n");
    exit(1);
}
