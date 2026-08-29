<?php

namespace Core\Util;

/**
 * Rich-text HTML helpers aligned with CORE_UX {@code core-rich-text}.
 * Sanitize persisted HTML on the server even when the client already sanitizes.
 */
final class RichTextHtml
{
    /** @var string[] */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'span', 'div',
    ];

    /**
     * Escapes plain text for safe insertion into HTML markup.
     */
    public static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Returns plain text extracted from an HTML fragment.
     */
    public static function getPlainText(string $html): string
    {
        if ($html === '') {
            return '';
        }
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Whitelist sanitizer aligned with core-rich-text formatting.
     */
    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $allowed = '<' . implode('><', self::ALLOWED_TAGS) . '>';
        $clean = strip_tags($html, $allowed);
        if ($clean === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<?xml encoding="utf-8" ?><div>' . $clean . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->documentElement;
        if (!$root instanceof \DOMElement) {
            return '';
        }

        self::cleanElementTree($doc, $root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    private static function cleanElementTree(\DOMDocument $doc, \DOMElement $element): void
    {
        $remove = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->nodeName);
                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    while ($child->firstChild) {
                        $element->insertBefore($child->firstChild, $child);
                    }
                    $remove[] = $child;
                    continue;
                }
                self::cleanAttributes($child);
                self::cleanElementTree($doc, $child);
            }
        }
        foreach ($remove as $node) {
            $element->removeChild($node);
        }
    }

    private static function cleanAttributes(\DOMElement $element): void
    {
        $tag = strtolower($element->nodeName);
        $allowed = [];
        if ($tag === 'a') {
            $allowed = ['href', 'target', 'rel'];
        } elseif (in_array($tag, ['span', 'p', 'div'], true)) {
            $allowed = ['style'];
        }

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
            $href = trim((string)$element->getAttribute('href'));
            if ($href === '' || preg_match('/^javascript:/i', $href) || !preg_match('/^(https?:|mailto:|#|\/|\?)/i', $href)) {
                $element->removeAttribute('href');
                $element->removeAttribute('target');
                $element->removeAttribute('rel');
                return;
            }
            $element->setAttribute('rel', 'noopener noreferrer');
            if (preg_match('/^https?:/i', $href)) {
                $element->setAttribute('target', '_blank');
            } else {
                $element->removeAttribute('target');
            }
            return;
        }

        if (in_array($tag, ['span', 'p', 'div'], true) && $element->hasAttribute('style')) {
            $safeStyle = self::sanitizeStyle((string)$element->getAttribute('style'));
            if ($safeStyle === '') {
                $element->removeAttribute('style');
            } else {
                $element->setAttribute('style', $safeStyle);
            }
        }
    }

    private static function sanitizeStyle(string $raw): string
    {
        $parts = array_filter(array_map('trim', explode(';', $raw)));
        $kept = [];
        foreach ($parts as $part) {
            if (preg_match('/^color\s*:\s*(#[0-9a-f]{3,8}|rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)|[a-z]+)\s*$/i', $part)) {
                $kept[] = $part;
                continue;
            }
            if (preg_match('/^text-align\s*:\s*(left|center|right|justify)\s*$/i', $part)) {
                $kept[] = $part;
            }
        }
        return implode('; ', $kept);
    }
}
