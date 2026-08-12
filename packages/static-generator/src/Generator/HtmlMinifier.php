<?php

namespace Pushword\StaticGenerator\Generator;

use Exception;
use Pushword\Core\Utils\Html5SvgCasePatch;
use Symfony\Component\DomCrawler\Crawler;

class HtmlMinifier
{
    /**
     * Number of times minification was skipped because it produced invalid
     * UTF-8. Per-process: parallel workers each keep their own count.
     */
    public static int $skippedOnBrokenLibxml = 0;

    public static function compress(string $html): string
    {
        $stripped = preg_replace('/<!--(.*?)-->/s', '', $html) ?? throw new Exception();

        $compressed = self::removeExtraWhiteSpace($stripped);

        if (mb_check_encoding($compressed, 'UTF-8')) {
            return $compressed;
        }

        // Some libxml versions (observed on 2.10.2, gone on 2.12.10) drop the
        // leading byte of a multi-byte character whose first byte lands on a
        // 4096-byte parse boundary, so "débutant" comes back as "d\xA9butant".
        // It hits DomCrawler, not DOMDocument::loadHTML, and is deterministic
        // for a given input. Serving the unminified HTML is the only lossless
        // answer; the comment stripping above never goes through libxml, so it
        // is kept.
        ++self::$skippedOnBrokenLibxml;

        return $stripped;
    }

    public static function removeExtraWhiteSpace(string $html): string
    {
        if (! str_starts_with($html, '<!DOCTYPE html>')) {
            return $html;
        }

        Html5SvgCasePatch::apply();
        $crawler = new Crawler($html);
        $html = '<!DOCTYPE html>'.$crawler->outerHtml(); // remove useless whitespace in tag attributes (but not in attribute !)

        $skippedTags = ['pre', 'code', 'script', 'textarea'];
        $protectedTags = [];

        foreach ($skippedTags as $tagName) {
            $crawler->filter($tagName)->each(static function (Crawler $node, int $i) use ($tagName, &$protectedTags, &$html): void {
                $placeholder = '<'.$tagName.'-placeholder-'.$i.'></'.$tagName.'-placeholder-'.$i.'>';
                $protectedTags[$placeholder] = $node->outerHtml();
                $html = str_replace($node->outerHtml(), $placeholder, $html);
            });
        }

        // Collapse every newline (and the whitespace around it) to a single space.
        // This is what a browser renders, so whitespace between text and inline
        // elements (e.g. ": <strong>") is preserved instead of being swallowed.
        // We match only ASCII space and tab explicitly (not \h): without the /u
        // flag \h works byte-by-byte and matches 0xA0, the second byte of many
        // UTF-8 characters (e.g. "à" = C3 A0), which would corrupt them. Using
        // [ \t] also leaves an intentional &nbsp; / U+00A0 untouched.
        $html = preg_replace('/[ \t]*\n[ \t\r\n\f]*/', ' ', $html) ?? $html;
        // Collapse remaining runs of horizontal whitespace to a single space.
        $html = preg_replace('/[ \t]{2,}/', ' ', $html) ?? $html;

        // That single space is only insignificant next to a block-level element
        // edge, where the browser would not render it: drop it there to stay tight.
        $block = 'address|article|aside|base|blockquote|body|canvas|dd|details|dialog|div|dl|dt'
            .'|fieldset|figcaption|figure|footer|form|h[1-6]|head|header|hgroup|hr|html|li|link'
            .'|main|map|meta|nav|ol|p|picture|section|source|style|summary|table|tbody|td|tfoot'
            .'|th|thead|title|tr|ul|video';
        // Tag names are lowercase ASCII (DomCrawler normalised them above), so no
        // case-insensitive or UTF-8 matching is needed here.
        // before an opening/closing block tag
        $html = preg_replace('#[ \t]+(</?(?:'.$block.')\b)#', '$1', $html) ?? $html;
        // after an opening/closing block tag
        $html = preg_replace('#(</?(?:'.$block.')\b[^>]*+>)[ \t]+#', '$1', $html) ?? $html;

        // Restore the original content of <pre> and <textarea>
        foreach ($protectedTags as $placeholder => $originalContent) {
            $html = str_replace($placeholder, $originalContent, $html);
        }

        return $html;
    }
}
