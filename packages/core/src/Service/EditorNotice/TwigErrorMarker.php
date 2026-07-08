<?php

namespace Pushword\Core\Service\EditorNotice;

/**
 * Invisible marker emitted in place of a content block whose Twig failed to
 * compile or render.
 *
 * One malformed `{{ … }}` typed by an editor must degrade to an invisible
 * comment, never take down the whole page with a 500. The visitor sees nothing
 * (the static generator strips comments), the page scanner reports it, and the
 * EditorNoticeListener turns it into a visible badge for logged-in editors.
 *
 * The message is html-escaped so a value containing `-->` cannot break out of
 * the comment (same guard as BrokenImageComment).
 */
final class TwigErrorMarker
{
    /** Matches the emitted marker and captures the (escaped) error message. */
    public const string PATTERN = '/<!-- pushword:twig-error msg="([^"]*)" -->/';

    public static function for(string $message): string
    {
        return '<!-- pushword:twig-error msg="'.self::encode($message).'" -->';
    }

    /**
     * Messages of every twig-error marker present in $html, decoded back to
     * their original form.
     *
     * @return string[]
     */
    public static function extractMessages(string $html): array
    {
        if (false === preg_match_all(self::PATTERN, $html, $matches) || [] === $matches[1]) {
            return [];
        }

        return array_map(htmlspecialchars_decode(...), $matches[1]);
    }

    private static function encode(string $message): string
    {
        $message = (string) preg_replace('/\s+/', ' ', trim($message));

        return htmlspecialchars($message, \ENT_QUOTES);
    }
}
