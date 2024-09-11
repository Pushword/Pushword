<?php

namespace Pushword\Admin;

use Pushword\Core\Entity\Page;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
#[AutoconfigureTag('sonata.admin', [
    'model_class' => Page::class,
    'manager_type' => 'orm',
    'label' => 'admin.label.page',
    'default' => true,
])]
class PageAdmin extends PageAbstractAdmin
{
}
