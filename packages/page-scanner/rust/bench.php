<?php

declare(strict_types=1);

use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\NativeWorker;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
if (! is_array($arguments) || count($arguments) < 2) {
    throw new InvalidArgumentException('Usage: php bench.php <static-dir-or-html-file> [...]');
}

$binary = __DIR__.'/target/release/pushword-page-facts';
if (! is_executable($binary)) {
    throw new RuntimeException('Build the release worker with cargo build --release --manifest-path packages/page-scanner/rust/Cargo.toml');
}

/** @return array{hrefs: list<string>, missing_alt: list<string>, anchors: list<string>, linked_docs: list<string>, crawlable_links: list<string>, mailto_links: list<string>, date_shortcodes: list<string>} */
function phpFacts(string $html): array
{
    preg_match_all('/<a\s[^>]*?href=(["\'])(?P<href>[^"\']*)\1/i', $html, $links);
    preg_match_all('/<img\s[^>]*>/i', $html, $images);

    $missingAlt = [];
    $seen = [];
    foreach ($images[0] as $image) {
        if (1 === preg_match('/\s(?:role\s*=\s*["\']?presentation|aria-hidden\s*=\s*["\']?true)/i', $image)
            || '' !== phpAttribute($image, '/\salt\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i')) {
            continue;
        }

        $src = phpAttribute($image, '/\ssrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i');
        if (isset($seen[$src])) {
            continue;
        }

        $seen[$src] = true;
        $missingAlt[] = '' !== $src ? $src : $image;
    }

    $anchors = [];
    $crawler = new Crawler($html);
    foreach ($crawler->filter('[id], [name]') as $node) {
        if (! $node instanceof DOMElement) {
            continue;
        }

        foreach (['id', 'name'] as $attribute) {
            if ($node->hasAttribute($attribute)) {
                $anchors[$node->getAttribute($attribute)] = true;
            }
        }
    }

    $anchors = array_map(strval(...), array_keys($anchors));
    sort($anchors, \SORT_STRING);

    $searchable = preg_replace('#<(code|pre)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
    preg_match_all('/ (href|data\-rot|src|data\-img|data\-bg)=((["\'])([^\3]+)\3|([^\s>]+)[\s>])/iU', $searchable, $attributes);
    $linkedDocs = [];
    $crawlableLinks = [];
    $mailtoLinks = [];
    foreach ($attributes[0] as $index => $_) {
        $obfuscated = 'data-rot' === $attributes[1][$index];
        $uri = '' !== $attributes[4][$index] ? $attributes[4][$index] : $attributes[5][$index];
        $uri = $obfuscated ? LinkProvider::decrypt($uri) : $uri;
        if (! $obfuscated && (str_contains($uri, 'mailto:') || str_contains($uri, 'tel:'))) {
            $mailtoLinks[] = $uri;
        } elseif ('' !== $uri && phpWebLink($uri)) {
            if (! $obfuscated) {
                $crawlableLinks[$uri] = true;
            }

            $linkedDocs[] = $uri;
        }
    }

    preg_match_all('/\s(?:srcset|imagesrcset|data-srcset)=(["\'])(.*?)\1/i', $searchable, $srcsets);
    foreach ($srcsets[2] as $srcset) {
        foreach (explode(',', $srcset) as $entry) {
            $uri = preg_split('/\s+/', trim($entry))[0] ?? '';
            if ('' !== $uri && phpWebLink($uri)) {
                $crawlableLinks[$uri] = true;
                $linkedDocs[] = $uri;
            }
        }
    }

    $withoutLiterals = preg_replace('#<(code|pre|script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
    preg_match_all('/\bdate\([\'"]?%?(?:Y[-+]1|[YSWBMAe])[\'"]?\)/i', $withoutLiterals, $dates);

    return [
        'hrefs' => $links['href'],
        'missing_alt' => $missingAlt,
        'anchors' => $anchors,
        'linked_docs' => array_values(array_unique($linkedDocs)),
        'crawlable_links' => array_map(strval(...), array_keys($crawlableLinks)),
        'mailto_links' => $mailtoLinks,
        'date_shortcodes' => array_values(array_unique($dates[0])),
    ];
}

function phpWebLink(string $uri): bool
{
    foreach (['https://wa.me/', 'https://maps.app.goo.gl', 'https://goo.gl/', 'https://g.page/', 'https://www.tripadvisor.fr/', 'https://www.facebook.com/'] as $ignored) {
        if (str_starts_with($uri, $ignored)) {
            return false;
        }
    }

    return 1 === preg_match('@^((?:(http:|https:)//([\wà-üÀ-Ü-]+\.)+[\w-]+){0,1}(/?[\wà-üÀ-Ü~,;\-\./?%&+#=]*))$@', $uri);
}

function phpAttribute(string $tag, string $pattern): string
{
    if (1 !== preg_match($pattern, $tag, $match)) {
        return '';
    }

    return trim(($match[1] ?? '').($match[2] ?? '').($match[3] ?? ''));
}

/** @return array{hrefs: list<string>, missing_alt: list<string>, anchors: list<string>, linked_docs: list<string>, crawlable_links: list<string>, mailto_links: list<string>, date_shortcodes: list<string>} */
function nativeFacts(mixed $raw): array
{
    if (! $raw instanceof stdClass || ! isset($raw->hrefs, $raw->missing_alt, $raw->anchors, $raw->linked_docs, $raw->crawlable_links, $raw->mailto_links, $raw->date_shortcodes)
    ) {
        throw new RuntimeException('Invalid native facts');
    }

    return [
        'hrefs' => stringList($raw->hrefs),
        'missing_alt' => stringList($raw->missing_alt),
        'anchors' => stringList($raw->anchors),
        'linked_docs' => stringList($raw->linked_docs),
        'crawlable_links' => stringList($raw->crawlable_links),
        'mailto_links' => stringList($raw->mailto_links),
        'date_shortcodes' => stringList($raw->date_shortcodes),
    ];
}

/** @return list<string> */
function stringList(mixed $raw): array
{
    if (! is_array($raw) || ! array_is_list($raw)) {
        throw new RuntimeException('Invalid native fact list');
    }

    $values = [];
    foreach ($raw as $value) {
        if (! is_string($value)) {
            throw new RuntimeException('Invalid native fact value');
        }

        $values[] = $value;
    }

    return $values;
}

/** @return list<string> */
function htmlFiles(string $path): array
{
    if (is_file($path)) {
        return [$path];
    }

    $files = [];
    foreach (Finder::create()->files()->name('/\.html$/i')->in($path) as $file) {
        $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
}

foreach (array_slice($arguments, 1) as $path) {
    if (! is_string($path)) {
        throw new InvalidArgumentException('Every input path must be a string');
    }

    $singleWorker = new NativeWorker($binary, 10.0);
    $batchWorker = new NativeWorker($binary, 10.0);
    $files = htmlFiles($path);
    $phpNs = 0;
    $singleNs = 0;
    $batchNs = 0;
    $bytes = 0;

    foreach (array_chunk($files, 8) as $chunk) {
        $documents = array_map(static fn (string $file): string => (string) file_get_contents($file), $chunk);
        $expected = [];
        foreach ($documents as $html) {
            $bytes += strlen($html);
            $start = hrtime(true);
            $expected[] = phpFacts($html);
            $phpNs += hrtime(true) - $start;
        }

        foreach ($documents as $index => $html) {
            $start = hrtime(true);
            $actual = nativeFacts($singleWorker->request('scan_rendered_html', [$html])[0]);
            $singleNs += hrtime(true) - $start;
            if ($actual !== $expected[$index]) {
                foreach (['hrefs', 'missing_alt', 'anchors', 'linked_docs', 'crawlable_links', 'mailto_links', 'date_shortcodes'] as $field) {
                    if ($actual[$field] !== $expected[$index][$field]) {
                        $mismatch = 0;
                        while (isset($expected[$index][$field][$mismatch], $actual[$field][$mismatch])
                            && $expected[$index][$field][$mismatch] === $actual[$field][$mismatch]) {
                            ++$mismatch;
                        }

                        $phpSample = json_encode(array_slice($expected[$index][$field], $mismatch, 3), \JSON_UNESCAPED_UNICODE);
                        $rustSample = json_encode(array_slice($actual[$field], $mismatch, 3), \JSON_UNESCAPED_UNICODE);

                        throw new RuntimeException(sprintf('PHP/Rust %s differ at %d for %s: PHP %s, Rust %s', $field, $mismatch, $chunk[$index], substr((string) $phpSample, 0, 200), substr((string) $rustSample, 0, 200)));
                    }
                }
            }
        }

        $start = hrtime(true);
        $actualBatch = array_map(nativeFacts(...), $batchWorker->request('scan_rendered_html', $documents));
        $batchNs += hrtime(true) - $start;
        if ($actualBatch !== $expected) {
            throw new RuntimeException('PHP/Rust batch facts differ in '.$path);
        }
    }

    printf(
        "%s: %d HTML, %.1f MiB; PHP %.3f s, Rust single %.3f s, Rust batches of 8 %.3f s; exact parity\n",
        $path,
        count($files),
        $bytes / 1048576,
        $phpNs / 1e9,
        $singleNs / 1e9,
        $batchNs / 1e9,
    );
}
