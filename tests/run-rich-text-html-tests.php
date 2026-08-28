<?php

declare(strict_types=1);

require_once __DIR__ . '/support/bootstrap.php';
require_once __DIR__ . '/../Core/Util/RichTextHtml.php';

use Core\Util\RichTextHtml;

assert(RichTextHtml::escapeHtml('a & b <c>') === 'a &amp; b &lt;c&gt;');
assert(str_contains(RichTextHtml::sanitize('<p>Hi</p><script>x</script>'), '<p>Hi</p>'));
assert(!str_contains(RichTextHtml::sanitize('<p>Hi</p><script>x</script>'), 'script'));
assert(str_contains(
    RichTextHtml::sanitize('<a href="https://example.com">Link</a>'),
    'href="https://example.com"'
));
assert(RichTextHtml::getPlainText('<p>Hello <strong>world</strong></p>') === 'Hello world');

echo "RichTextHtml tests passed.\n";
