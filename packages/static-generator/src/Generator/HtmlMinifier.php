<?php

namespace Pushword\StaticGenerator\Generator;

use Exception;
use Symfony\Component\DomCrawler\Crawler;

class HtmlMinifier
{
    public static function compress(string $html): string
    {
        $html = preg_replace('/<!--(.*?)-->/s', '', $html) ?? throw new Exception();

        return self::removeExtraWhiteSpace($html);
    }

    public static function removeExtraWhiteSpace(string $html): string
    {
        if (! str_starts_with($html, '<!DOCTYPE html>')) {
            return $html;
        }

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
