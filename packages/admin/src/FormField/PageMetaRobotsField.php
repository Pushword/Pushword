<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractField<PageInterface>
 */
class PageMetaRobotsField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('metaRobots', ChoiceType::class, [
            'choices' => [
                'admin.page.metaRobots.choice.noIndex' => 'noindex',
            ],
            'label' => 'admin.page.metaRobots.label',
            'required' => false,
        ]);
    }
}
