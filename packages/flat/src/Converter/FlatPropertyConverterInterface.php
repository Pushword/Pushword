<?php

namespace Pushword\Flat\Converter;

/**
 * Interface for property converters that transform values between database and flat file formats.
 *
 * Implementations are auto-tagged via instanceof in packages/flat/src/config/services.php.
 */
interface FlatPropertyConverterInterface
{
    /**
     * Property name this converter handles (e.g., 'mainImageFormat').
     */
    public function getPropertyName(): string;

    /**
     * Convert database value to flat file value (export).
     */
    public function toFlatValue(mixed $value): mixed;

    /**
     * Convert flat file value to database value (import).
     */
    public function fromFlatValue(mixed $value): mixed;
}
