<?php

namespace Pushword\Core\PropertySchema;

use Symfony\Component\Validator\Constraint;

final readonly class PagePropertySchema
{
    /** @param list<Constraint> $constraints */
    public function __construct(
        public string $name,
        public PagePropertyType $type = PagePropertyType::String,
        public bool $required = false,
        public array $constraints = [],
    ) {
    }
}
