<?php

namespace Pushword\Core\Utils;

use Cocur\Slugify\Slugify;
use Exception;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Utility class for media filename operations.
 * Handles slugification, extension extraction, and filename normalization.
 */
final class MediaFileName
{
    /**
     * Extracts a 3-4 character extension from a filename string.
     * Returns empty string if no valid extension found.
     */
    public static function extractExtension(string $filename): string
    {
        if (! str_contains($filename, '.')) {
            return '';
        }

        if (0 === preg_match('#.*(\.[^.\s]{3,4})$#', $filename)) {
            return '';
        }

        return preg_replace('/.*(\\.[^.\\s]{3,4})$/', '$1', $filename)
            ?? throw new Exception('Extension extraction failed');
    }

    /**
     * Extracts extension from a File object using MIME type guessing.
     * Falls back to filename extraction if MIME guessing fails.
     */
    public static function extractExtensionFromFile(File $file, string $originalFilename): string
    {
        $extension = $file->guessExtension(); // From MimeType

        $extension = null === $extension || '' === $extension ? self::extractExtension($originalFilename) : '.'.$extension;

        return self::fixExtension($extension, $originalFilename);
    }

    /**
     * Fixes extension for special cases where MIME type detection is wrong.
     * Currently handles GPX files (MIME returns .txt instead of .gpx).
     */
    public static function fixExtension(string $extension, string $originalFilename): string
    {
        // GPX files are detected as text/plain, returning .txt instead of .gpx
        if ('.gpx' !== self::extractExtension($originalFilename)) {
            return $extension;
        }

        return '.gpx';
    }

    /**
     * Slugifies a string for use as a filename.
     * Preserves dots, handles special characters (registered, trademark, copyright).
     */
    public static function slugify(string $text): string
    {
        // Fast return if text is already clean
        if (1 === \Safe\preg_match('/^[a-z0-9\-_]+$/', $text)) {
            return $text;
        }

        $slug = str_replace(['®', '™'], ' ', $text);

        $slugifier = new Slugify(['regexp' => '/([^A-Za-z0-9\.]|-)+/']);

        // Special handling for copyright symbol - split and rejoin with underscore
        $slug = str_replace(['©', '&copy;', '&#169;', '&#xA9;'], '©', $slug);
        $slug = explode('©', $slug, 2);
        $slug = $slugifier->slugify($slug[0])
            .(isset($slug[1]) ? '_'.$slugifier->slugify(str_replace('©', '', $slug[1])) : '');

        return $slug;
    }

    /**
     * Slugifies a filename while preserving its extension.
     */
    public static function slugifyPreservingExtension(string $filename, string $extension = ''): string
    {
        $extension = '' === $extension ? self::extractExtension($filename) : $extension;
        $basename = str_ends_with($filename, $extension)
            ? substr($filename, 0, \strlen($filename) - \strlen($extension))
            : $filename;
        $slugifiedBasename = self::slugify($basename);

        return $slugifiedBasename.$extension;
    }

    /**
     * Generates a slugified media filename from an original filename.
     * Main entry point for filename normalization when a File object is available.
     */
    public static function normalize(File $file, string $originalFilename = ''): string
    {
        if ('' === $originalFilename) {
            $originalFilename = $file instanceof UploadedFile
                ? $file->getClientOriginalName()
                : $file->getFilename();
        }

        if ('' === $originalFilename) {
            throw new Exception('Cannot normalize empty filename');
        }

        $extension = self::extractExtensionFromFile($file, $originalFilename);

        return self::slugifyPreservingExtension($originalFilename, $extension);
    }

    /**
     * Generates a slugified filename without requiring a File object.
     * Useful when only the filename string is available.
     */
    public static function normalizeFromString(string $filename): string
    {
        if ('' === $filename) {
            throw new Exception('Cannot normalize empty filename');
        }

        return self::slugifyPreservingExtension($filename);
    }
}
