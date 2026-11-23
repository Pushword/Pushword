<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Doctrine;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;
use Override;

final class CompressedJsonType extends JsonType
{
    public const string NAME = 'repurpose_compressed_json';

    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBlobTypeDeclarationSQL($column);
    }

    #[Override]
    public function getBindingType(): ParameterType
    {
        return ParameterType::LARGE_OBJECT;
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $json = parent::convertToDatabaseValue($value, $platform);
        if (null === $json) {
            return null;
        }

        $compressed = gzcompress($json, 1);

        return false !== $compressed ? $compressed : throw SerializationFailed::new($value, self::NAME, 'Zlib compression failed.');
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if (null === $value) {
            return null;
        }

        // Some drivers expose BLOB columns as streams.
        $compressed = \is_resource($value) ? stream_get_contents($value) : $value;
        $json = \is_string($compressed) ? @gzuncompress($compressed) : false;
        if (false === $json) {
            throw ValueNotConvertible::new($value, self::NAME, 'Invalid zlib payload.');
        }

        return parent::convertToPHPValue($json, $platform);
    }
}
