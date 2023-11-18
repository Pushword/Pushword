<?php

namespace Pushword\Admin;

use Pushword\Core\Entity\UserInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractAdmin<UserInterface>
 *
 * @implements AdminInterface<UserInterface>
 */
#[AutoconfigureTag('sonata.admin', [
    'model_class' => '%pw.entity_user%',
    'manager_type' => 'orm',
    'label' => 'admin.label.user',
])]
class UserAdmin extends AbstractAdmin implements AdminInterface
{
    final public const MESSAGE_PREFIX = 'admin.user';

    public function __construct(
        private readonly AdminFormFieldManager $adminFormFieldManager
    ) {
        $this->adminFormFieldManager->setMessagePrefix(self::MESSAGE_PREFIX);
        parent::__construct();
    }

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
        return method_exists($this->getModelClass(), 'get'.$name);
    }

    /**
     * @psalm-suppress  InvalidArgument // use only phpstan
     */
    protected function configureFormFields(FormMapper $form): void
    {
        $fields = $this->adminFormFieldManager->getFormFields($this, 'admin_user_form_fields');
        // if (! isset($fields[0]) || ! \is_array($fields[0]) || ! isset($fields[1]) || ! \is_array($fields[1])) { throw new \LogicException(); }

        $form->with('admin.user.label.id', ['class' => 'col-md-6 mainFields']);
        foreach ($fields[0] as $field) {
            $this->adminFormFieldManager->addFormField($field, $form, $this);
        }

        $form->end();

        foreach ($fields[1] as $k => $block) {
            $block = \is_array($block) ? $block : throw new \Exception();
            $form->with($k, ['class' => 'col-md-3 columnFields']);
            foreach ($block as $field) {
                $field = \is_string($field) ? $field : throw new \Exception();
                $this->adminFormFieldManager->addFormField($field, $form, $this);
            }

            $form->end();
        }
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter->add('id')
            ->add('email')
            // ->add('groups')
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
