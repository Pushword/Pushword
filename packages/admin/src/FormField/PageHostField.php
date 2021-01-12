<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\Form\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

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
