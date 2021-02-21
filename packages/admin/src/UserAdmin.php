<?php

namespace Pushword\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class UserAdmin extends AbstractAdmin implements UserAdminInterface
{
    use AdminTrait;

    protected $messagePrefix = 'admin.user';

    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_sort_by' => 'createdAt',
    ];

    protected function exists(string $name): bool
    {
        return method_exists($this->userClass, 'get'.$name);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $fields = $this->getFormFields('admin_user_form_fields');

        $formMapper->with('admin.user.label.id', ['class' => 'col-md-6 mainFields']);
        foreach ($fields[0] as $field) {
            $this->addFormField($field, $formMapper);
        }
        $formMapper->end();

        foreach ($fields[1]  as $k => $block) {
            $formMapper->with($k, ['class' => 'col-md-3 columnFields']);
            foreach ($block as $field) {
                $this->addFormField($field, $formMapper);
            }

            $formMapper->end();
        }
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper->add('id')
            ->add('email')
            //->add('groups')
        ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('username', null, [
                'label' => 'admin.user.username.label',
            ]);
        $listMapper
            ->add('email', null, [
                'label' => 'admin.user.email.label',
            ]);
        if ($this->exists('firstname')) {
            $listMapper->add(
                'firstname',
                TextType::class,
                [
                    'editable' => true,
                    'label' => 'admin.user.firstname.label',
                ]
            );
        }
        if ($this->exists('lastname')) {
            $listMapper->add(
                'lastname',
                TextType::class,
                [
                    'editable' => true,
                    'label' => 'admin.user.lastname.label',
                ]
            );
        }

        $listMapper->add('roles[0]', null, [
            'label' => 'admin.user.role.label',
        ]);

        $listMapper
            ->add('createdAt', null, [
                'editable' => true,
                'label' => 'admin.user.createdAt.label',
            ])
            ->add('_action', null, [
                'actions' => [
                    'edit' => [],
                    'delete' => [],
                ],
                'row_align' => 'right',
                'header_class' => 'text-right',
                'label' => 'admin.action',
            ]);
    }
}
