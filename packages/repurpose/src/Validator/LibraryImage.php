<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * A slide image must name a raster image of the media library. The renderer
 * paints anything else as a flat placeholder, so a typo would otherwise pass
 * validation and export a grey slide.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class LibraryImage extends Constraint
{
    public string $message = 'repurpose.image.media.notFound';
}
