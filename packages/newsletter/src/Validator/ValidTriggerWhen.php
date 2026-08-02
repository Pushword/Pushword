<?php

namespace Pushword\Newsletter\Validator;

use Attribute;
use Override;
use Symfony\Component\Validator\Constraint;

/**
 * An automation's `triggerWhen` is written in its source's vocabulary, and which
 * vocabulary that is only the registry knows — so this cannot be an
 * `#[Assert\Callback]` on the entity like the two contact-side rules next to it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ValidTriggerWhen extends Constraint
{
    public string $unknownSource = 'No trigger source is named "{{ source }}". Known sources: {{ known }}.';

    #[Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
