<?php

declare(strict_types=1);

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
