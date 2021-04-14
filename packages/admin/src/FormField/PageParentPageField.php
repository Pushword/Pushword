<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Repository\PageRepository;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class PageParentPageField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add(
            'parentPage',
            EntityType::class,
            array_merge(
                [
                    'class' => $this->admin->getPageClass(),
                    'label' => 'admin.page.parentPage.label',
                    'required' => false,
                ],
                ($this->admin->getSubject()->getId() ? ['query_builder' => function (PageRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->andWhere('p.id != :id')
                        ->setParameter('id', $this->admin->getSubject()->getId())
                        ->andWhere('p.parentPage != :page')
                        // TODO
                        // this one must be recursive to avoid error when user
                        // select a page wich has among this parents the current page
                        ->setParameter('page', $this->admin->getSubject());
                }] : [])
            )
        );
    }
}
