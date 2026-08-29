#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract tests for Core\Util\RichTextHtml — parity with CORE_UX lib/html/rich-text-html.js.
 */

require_once __DIR__ . '/support/bootstrap.php';
require_once __DIR__ . '/../Core/Util/RichTextHtml.php';

use Core\Util\RichTextHtml;

/**
 * @throws RuntimeException
 */
function assertContains(string $needle, string $haystack, string $message): void
{
    if ($needle === '' || !str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

/**
 * @throws RuntimeException
 */
function assertNotContains(string $needle, string $haystack, string $message): void
{
    if ($needle !== '' && str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

/**
 * @throws RuntimeException
 */
function assertSanitized(string $input, callable $assertion, string $message): void
{
    $output = RichTextHtml::sanitize($input);
    $assertion($output);
    if ($message !== '') {
        // no-op marker for readable failure context in call sites
    }
}

// 1. Simple text
assertSame('a &amp; b &lt;c&gt; &quot;d&quot;', RichTextHtml::escapeHtml('a & b <c> "d"'), 'escapeHtml escapes markup characters');

// 2. Paragraphs
$output = RichTextHtml::sanitize('<p>Hello</p><p>World</p>');
assertContains('<p>Hello</p>', $output, 'paragraphs preserved');
assertContains('<p>World</p>', $output, 'multiple paragraphs preserved');

// 3. strong / em / u
$output = RichTextHtml::sanitize('<p><strong>B</strong> <em>I</em> <u>U</u></p>');
assertContains('<strong>B</strong>', $output, 'strong preserved');
assertContains('<em>I</em>', $output, 'em preserved');
assertContains('<u>U</u>', $output, 'underline preserved');

// 4. ul / ol / li
$output = RichTextHtml::sanitize('<ul><li>One</li></ul><ol><li>Two</li></ol>');
assertContains('<ul>', $output, 'unordered list preserved');
assertContains('<li>One</li>', $output, 'list item preserved');
assertContains('<ol>', $output, 'ordered list preserved');
assertContains('<li>Two</li>', $output, 'ordered list item preserved');

// 5. https link
$output = RichTextHtml::sanitize('<a href="https://example.com">Link</a>');
assertContains('href="https://example.com"', $output, 'https href preserved');
assertContains('rel="noopener noreferrer"', $output, 'external link gets rel');
assertContains('target="_blank"', $output, 'https link opens in new tab');

// 6. mailto / relative / hash
$output = RichTextHtml::sanitize('<a href="mailto:user@example.com">Mail</a>');
assertContains('href="mailto:user@example.com"', $output, 'mailto allowed');
assertContains('rel="noopener noreferrer"', $output, 'mailto gets rel');
assertNotContains('target=', $output, 'mailto has no target');

$output = RichTextHtml::sanitize('<a href="/relative/path">Rel</a>');
assertContains('href="/relative/path"', $output, 'relative path allowed');
assertNotContains('target=', $output, 'relative link has no target');

$output = RichTextHtml::sanitize('<a href="#section">Hash</a>');
assertContains('href="#section"', $output, 'hash link allowed');

$output = RichTextHtml::sanitize('<a href="?query=1">Query</a>');
assertContains('href="?query=1"', $output, 'query-relative link allowed');

// 7. javascript rejected (case and spacing)
foreach ([
    'javascript:alert(1)',
    'JavaScript:alert(1)',
    '  javascript:alert(1)',
    '&#106;avascript:alert(1)',
] as $href) {
    $output = RichTextHtml::sanitize('<a href="' . $href . '">x</a>');
    assertNotContains('href=', $output, 'javascript href rejected: ' . $href);
    assertContains('<a>x</a>', $output, 'anchor text kept without href: ' . $href);
}

// 8. data rejected
foreach ([
    'data:text/html,<script>alert(1)</script>',
    'DATA:text/plain,hello',
] as $href) {
    $output = RichTextHtml::sanitize('<a href="' . $href . '">x</a>');
    assertNotContains('href=', $output, 'data href rejected: ' . $href);
}

// 9. onclick removed
$output = RichTextHtml::sanitize('<p onclick="alert(1)">Text</p>');
assertSame('<p>Text</p>', $output, 'event handler attribute removed');

// 10. unknown attribute removed
$output = RichTextHtml::sanitize('<p class="evil" data-foo="bar">Text</p>');
assertSame('<p>Text</p>', $output, 'unknown attributes removed');

// 11. script removed without retaining tag name
$output = RichTextHtml::sanitize('<p>Hello</p><script>alert(1)</script><strong>World</strong>');
assertContains('Hello', $output, 'text before script kept');
assertContains('<strong>World</strong>', $output, 'text after script kept');
assertNotContains('script', $output, 'script tag removed');
assertNotContains('onerror', $output, 'inline handlers from removed tags gone');

// 12. iframe / object / embed removed
foreach (['iframe', 'object', 'embed'] as $tag) {
    $output = RichTextHtml::sanitize('<p>Safe</p><' . $tag . ' src="evil"></' . $tag . '>');
    assertContains('Safe', $output, $tag . ' wrapper removed, text kept');
    assertNotContains('<' . $tag, $output, $tag . ' tag removed');
    assertNotContains('evil', $output, $tag . ' payload removed when empty');
}

// 13. img rejected
$output = RichTextHtml::sanitize('<p>Hi</p><img src="x" onerror="alert(1)">');
assertContains('Hi', $output, 'text around img kept');
assertNotContains('img', $output, 'img tag removed');
assertNotContains('onerror', $output, 'img onerror removed');

// 14. color allowed
$output = RichTextHtml::sanitize('<span style="color: #112233">C</span>');
assertContains('color: #112233', $output, 'hex color allowed');

$output = RichTextHtml::sanitize('<p style="color: rgb(1, 2, 3)">C</p>');
assertContains('color: rgb(1, 2, 3)', $output, 'rgb color allowed');

// 15. text-align allowed
$output = RichTextHtml::sanitize('<div style="text-align: center">C</div>');
assertContains('text-align: center', $output, 'text-align allowed');

// 16. arbitrary CSS removed
$output = RichTextHtml::sanitize('<span style="background: red; color: blue">C</span>');
assertContains('color: blue', $output, 'only color kept from mixed style');
assertNotContains('background', $output, 'background removed');

$output = RichTextHtml::sanitize('<p style="position: absolute">C</p>');
assertSame('<p>C</p>', $output, 'disallowed style property removes attribute');

// 17. malformed HTML
$output = RichTextHtml::sanitize('<p>unclosed<div>nested</p></div>');
assertContains('unclosed', $output, 'malformed HTML still yields readable text');
assertNotContains('<script', $output, 'malformed HTML does not introduce script');

// 18. hostile nesting
$output = RichTextHtml::sanitize('<a href="https://example.com"><script>alert(1)</script>Link</a>');
assertContains('href="https://example.com"', $output, 'link href kept in nested hostile input');
assertNotContains('script', $output, 'script inside anchor removed');
assertContains('Link', $output, 'anchor text kept');

$output = RichTextHtml::sanitize('<div><svg onload="alert(1)"><text>x</text></svg><p>OK</p></div>');
assertNotContains('svg', $output, 'svg removed');
assertContains('<p>OK</p>', $output, 'allowed sibling kept');

// 19. UTF-8 / accents
$output = RichTextHtml::sanitize('<p>Été — café <strong>naïve</strong></p>');
assertContains('Été', $output, 'accented characters preserved');
assertContains('café', $output, 'UTF-8 preserved');
assertContains('naïve', $output, 'UTF-8 in formatting preserved');

// 20. getPlainText
assertSame('Hello world', RichTextHtml::getPlainText('<p>Hello <strong>world</strong></p>'), 'plain text extraction');
assertSame('', RichTextHtml::getPlainText('<p><br></p>'), 'empty-ish HTML yields empty plain text');
assertSame('Visibleignored()', RichTextHtml::getPlainText('<p>Visible</p><script>ignored()</script>'), 'plain text includes unwrapped script text nodes like DOM textContent');

// 21. idempotent sanitize
$input = '<p>Hello <a href="https://example.com">Link</a></p><script>x</script>';
$once = RichTextHtml::sanitize($input);
$twice = RichTextHtml::sanitize($once);
assertSame($once, $twice, 'sanitize is idempotent');
assertNotContains('script', $twice, 'double sanitize does not reintroduce script');

// 22. external link rel/target contract
$output = RichTextHtml::sanitize('<a href="http://example.com" rel="nofollow" target="_self">L</a>');
assertContains('rel="noopener noreferrer"', $output, 'user rel replaced with safe default');
assertContains('target="_blank"', $output, 'http external link forced to _blank');
assertNotContains('nofollow', $output, 'user-supplied rel not kept');

$output = RichTextHtml::sanitize('<a href="/local">L</a>');
assertContains('rel="noopener noreferrer"', $output, 'relative link still gets rel');
assertNotContains('target=', $output, 'relative link has no target');

// CORE_UX fixture parity (conceptual, no runtime dependency)
$fixtureInput = '<p>Hello</p><script>alert(1)</script><img src=x onerror=alert(1)><strong>World</strong>';
$fixtureOutput = RichTextHtml::sanitize($fixtureInput);
assertContains('<strong>World</strong>', $fixtureOutput, 'CORE_UX fixture: strong kept');
assertContains('Hello', $fixtureOutput, 'CORE_UX fixture: hello kept');
assertNotContains('script', $fixtureOutput, 'CORE_UX fixture: script gone');
assertNotContains('img', $fixtureOutput, 'CORE_UX fixture: img gone');

$listFixture = '<ul><li>One</li></ul><a href="https://example.com">Link</a>';
$listOutput = RichTextHtml::sanitize($listFixture);
assertContains('<ul><li>One</li></ul>', $listOutput, 'CORE_UX fixture: list kept');
assertContains('href="https://example.com"', $listOutput, 'CORE_UX fixture: link kept');
assertContains('rel="noopener noreferrer"', $listOutput, 'CORE_UX fixture: rel kept');

echo "RichTextHtml tests passed.\n";
