<?php

namespace Pushword\Admin\FormField;

use Doctrine\ORM\QueryBuilder;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\PageRepository;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

/**
 * @extends AbstractField<PageInterface>
 */
class PageParentPageField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add(
            'parentPage',
            EntityType::class,
            [
                'class' => $this->admin->getModelClass(),
                'label' => 'admin.page.parentPage.label',
                'required' => false,
                'query_builder' => fn (PageRepository $er): QueryBuilder => $er->createQueryBuilder('p')
                ->andWhere('p.id != :id')
                ->andWhere('p.host = :host')
                ->setParameter('id', (int) $this->admin->getSubject()->getId())
                ->setParameter('host', $this->admin->getSubject()->getHost()),
            ]
        );
    }
}
