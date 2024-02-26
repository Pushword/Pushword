<?php

namespace Pushword\StaticGenerator\Generator;

use Symfony\Component\DomCrawler\Crawler;

class HtmlMinifier
{
    public static function compress(string $html): string
    {
        $html = preg_replace('/<!--(.*?)-->/s', '', $html) ?? throw new \Exception();

        return self::removeExtraWhiteSpace($html);
    }

    public static function removeExtraWhiteSpace(string $html): string
    {
        $crawler = new Crawler($html);
        $html = '<!DOCTYPE html>'.$crawler->outerHtml(); // remove useless whitespace in tag attributes (but not in attribute !)

        $skippedTags = ['code', 'pre', 'script', 'style', 'textarea'];
        $protectedTags = [];

        foreach ($skippedTags as $tagName) {
            $crawler->filter($tagName)->each(static function (Crawler $node, string $i) use ($tagName, &$protectedTags, $html): void {
                $placeholder = '<'.$tagName.'-placeholder-'.$i.'></'.$tagName.'-placeholder-'.$i.'>';
                $protectedTags[$placeholder] = $node->outerHtml();
                $html = str_replace($node->outerHtml(), $placeholder, $html);
            });
        }

        $html = preg_replace('/\h{2,}/u', ' ', $html) ?? $html; // remove multiple horizontal whitespaces
        $html = preg_replace('/\n\h{1,}/u', "\n", $html) ?? $html; // remove whitespace starting a new line
        $html = preg_replace('/\n{1,}/u', '', $html) ?? $html; // remove all newlines (a bit extreme ?!)
        // $html = preg_replace('/\n{2,}/', "\n", $html);

        // Restore the original content of <pre> and <textarea>
        foreach ($protectedTags as $placeholder => $originalContent) {
            $html = str_replace($placeholder, $originalContent, $html);
        }

        return $html;
    }
}
