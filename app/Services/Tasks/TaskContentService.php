<?php

namespace App\Services\Tasks;

/** Provides task content service behavior within the WorkIntel application. */ class TaskContentService
{
    /**
     * Tiptap output is limited to a deliberately small HTML vocabulary.
     * Arbitrary attributes are stripped from every allowed element. Links are
     * rebuilt with an allow-listed protocol, so event/style attributes cannot
     * cross the backend trust boundary.
     */
    /** Handles the sanitize operation for the current WorkIntel workflow. */ public function sanitize(?string $html): ?string
    {
        if (! filled($html)) return null;

        $html = (string) $html;
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? $html;
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><h1><h2><h3><ul><ol><li><blockquote><pre><code><hr><a>');
        $html = preg_replace_callback('/<([a-z0-9]+)\b([^>]*)>/i', function (array $match) {
            $tag = strtolower($match[1]);
            if ($tag !== 'a') return '<'.$tag.'>';

            $href = '';
            if (preg_match('/href\s*=\s*(["\'])(.*?)\1/i', $match[2], $hrefMatch)) {
                $candidate = trim(html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (preg_match('#^(https?://|mailto:)#i', $candidate)) {
                    $href = htmlspecialchars($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
            return $href ? '<a href="'.$href.'" target="_blank" rel="noopener noreferrer">' : '<a>';
        }, $html) ?? $html;

        return trim($html) ?: null;
    }

    /** Handles the plain text operation for the current WorkIntel workflow. */ public function plainText(?string $html, ?string $fallback = null): ?string
    {
        if (filled($html)) {
            $text = html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>', '<br/>', '<br />'], "\n", (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
            if ($text !== '') return mb_substr($text, 0, 5000);
        }
        return filled($fallback) ? mb_substr(trim((string) $fallback), 0, 5000) : null;
    }
}
