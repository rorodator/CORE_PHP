# Rich-text HTML sanitization (`Core\Util\RichTextHtml`)

Server-side counterpart to CORE_UX `core-rich-text` and `lib/html/rich-text-html.js`.

## Security boundary

The browser is **not** a security boundary. Client-side sanitization improves UX but must never be trusted for persistence or server-side rendering.

**Always** call `RichTextHtml::sanitize()` on the server before storing or emitting user HTML, even when the client already sanitized.

Recommended storage format: **sanitized HTML** (output of `sanitize()`).

## Public API

| Method | Role |
|--------|------|
| `sanitize(string $html): string` | Whitelist sanitizer — primary persistence/render gate |
| `escapeHtml(string $text): string` | Escape plain text for HTML contexts |
| `getPlainText(string $html): string` | Extract visible text for business checks |

No extra normalization API in CORE_PHP: `normalizeRichTextHtml()` lives in CORE_UX for editor output cleanup only.

## Contract (aligned with CORE_UX)

### Allowed tags

`p`, `br`, `strong`, `b`, `em`, `i`, `u`, `ul`, `ol`, `li`, `a`, `span`, `div`

Disallowed tags are **unwrapped** (children kept). Tags and capabilities beyond this list are out of scope — no `img`, `iframe`, `object`, `embed`, `script`, `style`, SVG, MathML, or arbitrary custom elements.

### Allowed attributes

| Tag | Attributes |
|-----|------------|
| `a` | `href`, `target`, `rel` (server-set) |
| `span`, `p`, `div` | `style` (sanitized) |

Event handlers, `class`, `id`, arbitrary `data-*`, and unknown attributes are removed.

### Links (`href`)

Allowed schemes (after trim): `http:`, `https:`, `mailto:`, `#`, `/`, `?`

Rejected explicitly: `javascript:`, `data:`, and any other scheme.

External `http(s)` links receive `rel="noopener noreferrer"` and `target="_blank"`. Relative, hash, mailto, and query-relative links receive `rel` only (no `target`). User-supplied `rel` / `target` are not preserved.

### Styles

Only `color` (hex, `rgb(...)`, or named) and `text-align` (`left`, `center`, `right`, `justify`). Other CSS declarations are dropped.

### Plain text

Use `getPlainText()` to test for real content, apply **application-owned** length limits on visible text, or build plain-text previews. CORE_PHP does not enforce business max lengths (e.g. 5000 characters).

## Semantic parity vs CORE_UX

PHP uses `DOMDocument`; CORE_UX uses the browser DOM. Output may differ in insignificant HTML serialization (attribute order, void tags, whitespace) while remaining **semantically equivalent** under the same whitelist rules.

## Example

```php
use Core\Util\RichTextHtml;

$html = RichTextHtml::sanitize($_POST['body'] ?? '');
$plain = RichTextHtml::getPlainText($html);

if ($plain === '') {
    // application validation
}

// persist $html
```
