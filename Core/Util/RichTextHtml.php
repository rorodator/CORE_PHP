<?php

namespace Core\Util;

/**
 * Server-side rich-text HTML sanitizer aligned with CORE_UX {@code core-rich-text}.
 *
 * The browser is not a security boundary: always call {@see sanitize()} before
 * persisting or rendering stored user HTML, even when the client already sanitized.
 * Recommended storage format is sanitized HTML. Use {@see getPlainText()} for
 * business validation (presence, visible length, previews); length limits belong
 * in the consuming application.
 */
final class RichTextHtml
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'span', 'div',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'target', 'rel'],
        'span' => ['style'],
        'p' => ['style'],
        'div' => ['style'],
    ];

    private const SAFE_HREF = '/^(https?:|mailto:|#|\/|\?)/i';

    private const ALLOWED_STYLE = '/^(?:color:\s*(#[0-9a-f]{3,8}|rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)|[a-z]+)\s*;?\s*)?(?:text-align:\s*(left|center|right|justify)\s*;?\s*)?$/i';

    /**
     * Escapes plain text for safe insertion into HTML markup.
     */
    public static function escapeHtml(string $text): string
    {
        if ($text === '') {
            return '';
        }

        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Returns plain text extracted from an HTML fragment (visible text only).
     */
    public static function getPlainText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $doc = self::loadFragmentDocument($html);
        if ($doc === null) {
            return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        }

        $root = $doc->documentElement;
        if (!$root instanceof \DOMElement) {
            return '';
        }

        return trim($root->textContent ?? '');
    }

    /**
     * Whitelist sanitizer aligned with core-rich-text formatting commands.
     */
    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $doc = self::loadFragmentDocument($html);
        if ($doc === null) {
            return '';
        }

        $root = $doc->documentElement;
        if (!$root instanceof \DOMElement) {
            return '';
        }

        self::cleanElementTree($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    private static function loadFragmentDocument(string $html): ?\DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $doc->loadHTML(
            '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded !== true) {
            return null;
        }

        return $doc;
    }

    private static function cleanElementTree(\DOMElement $element): void
    {
        $remove = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $element->insertBefore($child->firstChild, $child);
                }
                $remove[] = $child;
                continue;
            }

            self::cleanAttributes($child);
            self::cleanElementTree($child);
        }

        foreach ($remove as $node) {
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private static function cleanAttributes(\DOMElement $element): void
    {
        $tag = strtolower($element->nodeName);
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

        if ($element->hasAttributes()) {
            $toRemove = [];
            foreach ($element->attributes as $attribute) {
                if (!in_array(strtolower($attribute->nodeName), $allowed, true)) {
                    $toRemove[] = $attribute->nodeName;
                }
            }
            foreach ($toRemove as $name) {
                $element->removeAttribute($name);
            }
        }

        if ($tag === 'a') {
            self::applyAnchorAttributes($element);
            return;
        }

        if (in_array($tag, ['span', 'p', 'div'], true) && $element->hasAttribute('style')) {
            $safeStyle = self::sanitizeStyle((string)$element->getAttribute('style'));
            if ($safeStyle === null) {
                $element->removeAttribute('style');
            } else {
                $element->setAttribute('style', $safeStyle);
            }
        }
    }

    private static function applyAnchorAttributes(\DOMElement $element): void
    {
        $safeHref = self::sanitizeHref($element->hasAttribute('href') ? (string)$element->getAttribute('href') : null);
        $element->removeAttribute('href');
        $element->removeAttribute('target');
        $element->removeAttribute('rel');

        if ($safeHref === null) {
            return;
        }

        $element->setAttribute('href', $safeHref);
        $element->setAttribute('rel', 'noopener noreferrer');
        if (preg_match('/^https?:/i', $safeHref) === 1) {
            $element->setAttribute('target', '_blank');
        }
    }

    /**
     * @return string|null Sanitized href or null when rejected.
     */
    private static function sanitizeHref(?string $href): ?string
    {
        if ($href === null || $href === '') {
            return null;
        }

        $value = trim($href);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^javascript:/i', $value) === 1) {
            return null;
        }

        if (preg_match('/^data:/i', $value) === 1) {
            return null;
        }

        if (preg_match(self::SAFE_HREF, $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * @return string|null Sanitized style or null when rejected.
     */
    private static function sanitizeStyle(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(';', $raw)), static fn (string $part): bool => $part !== ''));
        $kept = [];
        foreach ($parts as $part) {
            if (preg_match('/^color\s*:/i', $part) === 1 || preg_match('/^text-align\s*:/i', $part) === 1) {
                $kept[] = $part;
            }
        }

        if ($kept === []) {
            return null;
        }

        $normalized = implode('; ', $kept);
        if (preg_match(self::ALLOWED_STYLE, $normalized . ';') !== 1) {
            return null;
        }

        return $normalized;
    }
}
