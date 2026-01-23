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
}
