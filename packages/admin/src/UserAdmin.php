<?php

namespace Pushword\Admin;

use LogicException;
use Pushword\Core\Entity\UserInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractAdmin<UserInterface>
 */
class UserAdmin extends AbstractAdmin implements UserAdminInterface
{
    /**
     * @use AdminTrait<UserInterface>
     */
    use AdminTrait;

    /**
     * @var string
     */
    protected $messagePrefix = 'admin.user';

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues = [
            '_page' => 1,
            '_sort_order' => 'DESC',
            '_sort_by' => 'createdAt',
        ];
    }

    protected function exists(string $name): bool
    {
        return method_exists($this->userClass, 'get'.$name);
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $fields = $this->getFormFields('admin_user_form_fields');
        if (! isset($fields[0]) || ! \is_array($fields[0]) || ! isset($fields[1]) || ! \is_array($fields[1])) {
            throw new LogicException();
        }

        $form->with('admin.user.label.id', ['class' => 'col-md-6 mainFields']);
        foreach ($fields[0] as $field) {
            $this->addFormField($field, $form);
        }

        $form->end();

        foreach ($fields[1]  as $k => $block) {
            $form->with($k, ['class' => 'col-md-3 columnFields']);
            foreach ($block as $singleBlock) {
                $this->addFormField($singleBlock, $form);
            }

            $form->end();
        }
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter->add('id')
            ->add('email')
            //->add('groups')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('username', null, [
                'label' => 'admin.user.username.label',
            ]);
        $list
            ->add('email', null, [
                'label' => 'admin.user.email.label',
            ]);
        if ($this->exists('firstname')) {
            $list->add(
                'firstname',
                TextType::class,
                [
                    'editable' => true,
                    'label' => 'admin.user.firstname.label',
                ]
            );
        }

        if ($this->exists('lastname')) {
            $list->add(
                'lastname',
                TextType::class,
                [
                    'editable' => true,
                    'label' => 'admin.user.lastname.label',
                ]
            );
        }

        $list->add('roles[0]', null, [
            'label' => 'admin.user.role.label',
        ]);

        $list
            ->add('createdAt', null, [
                'editable' => true,
                'label' => 'admin.user.createdAt.label',
            ])
            ->add('_actions', null, [
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
