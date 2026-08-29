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
     * Returns plain text extracted from an HTML fragment (raw DOM textContent).
     */
    public static function getPlainText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $doc = self::loadFragmentDocument($html);
        if ($doc === null) {
            return preg_replace('/<[^>]+>/', ' ', $html) ?? '';
        }

        $root = $doc->documentElement;
        if (!$root instanceof \DOMElement) {
            return '';
        }

        return $root->textContent ?? '';
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

        $cleanedRoot = $doc->createElement('div');
        foreach (self::snapshotChildNodes($root) as $child) {
            $cleaned = self::cleanNode($doc, $child);
            if ($cleaned !== null) {
                self::appendCleanedNode($cleanedRoot, $cleaned);
            }
        }

        $output = '';
        foreach ($cleanedRoot->childNodes as $child) {
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

    /**
     * @return list<\DOMNode>
     */
    private static function snapshotChildNodes(\DOMNode $node): array
    {
        $snapshot = [];
        foreach ($node->childNodes as $child) {
            $snapshot[] = $child;
        }

        return $snapshot;
    }

    private static function appendCleanedNode(\DOMNode $parent, \DOMNode $cleaned): void
    {
        if ($cleaned instanceof \DOMDocumentFragment) {
            while ($cleaned->firstChild !== null) {
                $parent->appendChild($cleaned->firstChild);
            }
            return;
        }

        $parent->appendChild($cleaned);
    }

    private static function cleanNode(\DOMDocument $doc, \DOMNode $node): ?\DOMNode
    {
        if ($node instanceof \DOMText) {
            return $node->cloneNode(false);
        }

        if (!$node instanceof \DOMElement) {
            return null;
        }

        $tag = strtolower($node->nodeName);
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            $fragment = $doc->createDocumentFragment();
            foreach (self::snapshotChildNodes($node) as $child) {
                $cleaned = self::cleanNode($doc, $child);
                if ($cleaned !== null) {
                    self::appendCleanedNode($fragment, $cleaned);
                }
            }

            return $fragment;
        }

        $out = $doc->createElement($tag);
        self::copyAllowedAttributes($node, $out);

        foreach (self::snapshotChildNodes($node) as $child) {
            $cleaned = self::cleanNode($doc, $child);
            if ($cleaned !== null) {
                self::appendCleanedNode($out, $cleaned);
            }
        }

        return $out;
    }

    private static function copyAllowedAttributes(\DOMElement $source, \DOMElement $target): void
    {
        $tag = strtolower($source->nodeName);

        if ($tag === 'a') {
            $safeHref = self::sanitizeHref($source->hasAttribute('href') ? (string)$source->getAttribute('href') : null);
            if ($safeHref === null) {
                return;
            }

            $target->setAttribute('href', $safeHref);
            $target->setAttribute('rel', 'noopener noreferrer');
            if (preg_match('/^https?:/i', $safeHref) === 1) {
                $target->setAttribute('target', '_blank');
            }
            return;
        }

        if (in_array($tag, ['span', 'p', 'div'], true) && $source->hasAttribute('style')) {
            $safeStyle = self::sanitizeStyle((string)$source->getAttribute('style'));
            if ($safeStyle !== null) {
                $target->setAttribute('style', $safeStyle);
            }
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
