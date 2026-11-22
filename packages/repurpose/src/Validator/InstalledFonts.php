<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Every font a pairing draws with must be on disk: the renderer would otherwise
 * fall back to Roboto without a word. The studio and the API download missing
 * Google Fonts families before validating, so this only fails there when the
 * download did; on the command line it names the install command.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class InstalledFonts extends Constraint
{
    public string $message = 'repurpose.fontPairing.notInstalled';
}
