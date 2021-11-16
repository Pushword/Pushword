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
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add(
            'parentPage',
            EntityType::class,
            array_merge(
                [
                    'class' => $this->admin->getPageClass(),
                    'label' => 'admin.page.parentPage.label',
                    'required' => false,
                ],
                (null !== $this->admin->getSubject()->getId() ? ['query_builder' => fn (PageRepository $er): QueryBuilder => $er->createQueryBuilder('p')
                    ->andWhere('p.id != :id')
                    ->setParameter('id', $this->admin->getSubject()->getId()), ] : [])
            )
        );
    }
}
