<?php

namespace Pushword\Admin;

use Override;
use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('sonata.admin', [
    'model_class' => Page::class,
    'manager_type' => 'orm',
    'label' => 'admin.label.page',
    'default' => false,
])]
class PageRedirectionAdmin extends PageAbstractAdmin
{
    #[Override]
    protected function configureFormFields(FormMapper $form): void
    {
        $this->formFieldKey = 'admin_redirection_form_fields';
        parent::configureFormFields($form);
    }

    #[Override]
    protected function generateBaseRouteName(bool $isChildAdmin = false): string
    {
        return 'admin_redirection';
    }

    #[Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'app/redirection';
    }

    #[Override]
    protected function configureQuery(ProxyQueryInterface $query): ProxyQueryInterface
    {
        $query = AbstractAdmin::configureQuery($query);

        $qb = $this->getQueryBuilderFrom($query);

        $rootAlias = current($qb->getRootAliases());

        $qb->andWhere(
            $qb->expr()->like($rootAlias.'.mainContent', ':mcf')
        );
        $qb->setParameter('mcf', 'Location:%');

        return $query;
    }

    #[Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list->addIdentifier('h1', 'html', [
            'label' => 'admin.page.title.label',
            'template' => '@pwAdmin/page/page_list_titleField.html.twig',
        ]);
        $list->add('mainContent', 'text', [
            'label' => 'Redirection',
        ]);
        $list->add('updatedAt', 'datetime', [
            'format' => 'd/m à H:m',
            'label' => 'admin.page.updatedAt.label',
        ]);
        $list->add('_actions', null, [
            'actions' => [
                'edit' => [],
                'show' => [],
                'delete' => [],
            ],
            'row_align' => 'right',
            'header_class' => 'text-right',
            'label' => 'admin.action',
        ]);
    }
}
