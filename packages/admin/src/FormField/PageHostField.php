<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class PageHostField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        if (null === $this->admin->getSubject()->getHost()) {
            $this->admin->getSubject()->setHost($this->admin->getApps()->getMainHost());
        }

        return $formMapper->add('host', ChoiceType::class, [
            'choices' => array_combine($this->getHosts(), $this->getHosts()),
            'required' => false,
            'label' => 'admin.page.host.label',
            'empty_data' => $this->getHosts()[0],
        ]);
    }

    private function getHosts()
    {
        return $this->admin->getApps()->getHosts();
    }
}
