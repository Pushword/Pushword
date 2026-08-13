<?php

namespace Pushword\Core\Image;

/**
 * The throwaway files the image writers put beside their target before renaming
 * the finished bytes into place — {@see ImageOptimizer} and {@see ImageEncoder}.
 *
 * They have to sit next to that target, because rename() is only atomic within one
 * filesystem, so anything walking the media tree meets them. They exist for the
 * length of one write and then vanish, which makes them poison for a reader: a
 * static build that copies one either publishes a truncated image or dies when the
 * file disappears mid-copy. Every writer names them here so a single predicate
 * recognizes them all.
 */
final class ImageScratchFile
{
    /**
     * Matches every name pathFor() builds, and the writer-less form the encoder
     * wrote before it named them here — those are still lying in older media trees.
     * A regex rather than a glob so one pattern covers both; Finder takes either.
     */
    public const string NAME_PATTERN = '#\.(?:[a-z]+-)?\d+\.[0-9a-z]+\.tmp$#';

    /**
     * $writer tags who left the file behind, which is what makes an orphan (a
     * process killed before its `finally`) traceable to the code that wrote it.
     * PID plus uniqid: two writers racing on one target never share a name.
     */
    public static function pathFor(string $target, string $writer): string
    {
        return $target.'.'.$writer.'-'.getmypid().'.'.uniqid().'.tmp';
    }

    public static function isScratch(string $path): bool
    {
        return 1 === preg_match(self::NAME_PATTERN, basename($path));
    }
}
