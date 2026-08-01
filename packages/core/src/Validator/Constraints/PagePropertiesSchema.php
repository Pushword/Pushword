<?php

namespace Pushword\Core\Validator\Constraints;

use Attribute;
use Override;
use Symfony\Component\Validator\Constraint;

/**
 * Validates a page's custom properties against the host's declared
 * `page_properties` schema. Undeclared keys pass untouched.
 *
 * Carries the `pw_schema` group besides Default so the flat importer can
 * validate the schema alone, without dragging in unrelated entity
 * constraints.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PagePropertiesSchema extends Constraint
{
    public const string SCHEMA_GROUP = 'pw_schema';

    #[Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
