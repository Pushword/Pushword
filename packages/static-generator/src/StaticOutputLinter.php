<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Post-generation guard for invariants that every emitted HTML file must keep:
 * valid UTF-8, and no internal link starting with a configured host as its first
 * path segment (href="/www.example.com/slug" 404s on the live static host).
 */
final class StaticOutputLinter
{
    private const int MAX_REPORTED_FILES = 10;

    /**
     * @param string[] $hosts every configured host, aliases included
     *
     * @return string[] one error per offending file (capped), empty when clean
     */
    public static function lint(string $staticDir, array $hosts): array
    {
        $needles = [];
        foreach ($hosts as $host) {
            if ('' === $host) {
                continue;
            }

            // Exact-segment match only: href="/{host}" or href="/{host}/…" —
            // never href="/assets/{host}/…" or a slug merely starting with it.
            $needles[] = 'href="/'.$host.'"';
            $needles[] = 'href="/'.$host.'/';
        }

        if (! is_dir($staticDir)) {
            return [];
        }

        $errors = [];
        $overflow = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($staticDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            if ('html' !== $file->getExtension()) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if (false === $content) {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen(rtrim($staticDir, '/')) + 1);
            $error = ! mb_check_encoding($content, 'UTF-8')
                ? 'Invalid UTF-8 in '.$relativePath
                : null;

            if (null === $error) {
                foreach ($needles as $needle) {
                    if (str_contains($content, $needle)) {
                        $error = \sprintf(
                            'Host-prefixed internal link (%s…) in %s — static output must be host-less',
                            $needle,
                            $relativePath,
                        );

                        break;
                    }
                }
            }

            if (null === $error) {
                continue;
            }

            if (\count($errors) >= self::MAX_REPORTED_FILES) {
                ++$overflow;
            } else {
                $errors[] = $error;
            }
        }

        if ($overflow > 0) {
            $errors[] = \sprintf('… and %d more static output errors', $overflow);
        }

        return $errors;
    }
}
