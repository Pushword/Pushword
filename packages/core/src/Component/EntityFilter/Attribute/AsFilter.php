<?php

namespace Pushword\Core\Component\EntityFilter\Attribute;

use Attribute;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsFilter extends AutoconfigureTag
{
    public function __construct()
    {
        parent::__construct('pushword.entity_filter');
    }
}
