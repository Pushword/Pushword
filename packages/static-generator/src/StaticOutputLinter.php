<?php

namespace Pushword\StaticGenerator;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Post-generation guard: static output is served from each host's root, so no
 * internal link may start with a configured host as its first path segment
 * (href="/www.example.com/slug" 404s on the live static host). This symptom has
 * had several distinct causes (a services reset re-enabling the /{host}/ route
 * prefix, stale cached fragments, hand-written content links); linting the
 * emitted HTML catches every future one at generation time, before the broken
 * export replaces the last good one.
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

        if ([] === $needles || ! is_dir($staticDir)) {
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

            foreach ($needles as $needle) {
                if (! str_contains($content, $needle)) {
                    continue;
                }

                if (\count($errors) >= self::MAX_REPORTED_FILES) {
                    ++$overflow;
                } else {
                    $relativePath = substr($file->getPathname(), \strlen(rtrim($staticDir, '/')) + 1);
                    $errors[] = \sprintf(
                        'Host-prefixed internal link (%s…) in %s — static output must be host-less',
                        $needle,
                        $relativePath,
                    );
                }

                break; // one report per file is enough
            }
        }

        if ($overflow > 0) {
            $errors[] = \sprintf('… and %d more files with host-prefixed links', $overflow);
        }

        return $errors;
    }
}
