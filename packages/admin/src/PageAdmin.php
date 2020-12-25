<?php

namespace Pushword\Admin;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
#[AutoconfigureTag('sonata.admin', [
    'model_class' => '%pw.entity_page%',
    'manager_type' => 'orm',
    'label' => 'admin.label.page',
    'default' => true,
])]
class PageAdmin extends PageAbstractAdmin
{
}
