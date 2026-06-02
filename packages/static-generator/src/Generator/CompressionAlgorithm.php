<?php

namespace Pushword\StaticGenerator\Generator;

enum CompressionAlgorithm: string
{
    case Zstd = 'zstd';
    case Brotli = 'brotli';
    case Gzip = 'gzip';

    public function getExtension(): string
    {
        return match ($this) {
            self::Zstd => '.zst',
            self::Brotli => '.br',
            self::Gzip => '.gz',
        };
    }

    /**
     * The primary file (empty suffix) plus every compressed sidecar extension.
     * Single source of truth for code that copies or deletes a page's whole
     * file set, so a new algorithm is covered everywhere automatically.
     *
     * @return string[]
     */
    public static function fileSuffixes(): array
    {
        return ['', ...array_map(static fn (self $algorithm): string => $algorithm->getExtension(), self::cases())];
    }

    public function nativeCompress(string $content): ?string
    {
        return match ($this) {
            self::Gzip => \function_exists('gzencode') ? (gzencode($content, 9) ?: null) : null,
            self::Brotli => \function_exists('brotli_compress') ? (brotli_compress($content) ?: null) : null,
            self::Zstd => null, // PHP ext doesn't support window size control; browsers reject default 128MB window
        };
    }

    public function hasNativeSupport(): bool
    {
        return match ($this) {
            self::Gzip => \function_exists('gzencode'),
            self::Brotli => \function_exists('brotli_compress'),
            self::Zstd => false, // PHP ext doesn't support window size control; browsers reject large window sizes
        };
    }
}
