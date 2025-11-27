<?php

namespace Pushword\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use LogicException;
use Override;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Core\Entity\User;

/** @extends AbstractAdminCrudController<User> */
class UserCrudController extends AbstractAdminCrudController
{
    public const string MESSAGE_PREFIX = 'admin.user';

    private ?AdminUrlGenerator $adminUrlGenerator = null;

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('admin.label.user')
            ->setEntityLabelInPlural('admin.label.users')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return $this->getIndexFields();
        }

        return $this->getFormFieldsDefinition();
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    private function getFormFieldsDefinition(): iterable
    {
        $instance = $this->getContext()?->getEntity()?->getInstance();
        $this->setSubject($instance instanceof User ? $instance : new User());
        $this->adminFormFieldManager->setMessagePrefix(self::MESSAGE_PREFIX);

        $fields = array_replace(
            [[], [], []],
            $this->adminFormFieldManager->getFormFields($this, 'admin_user_form_fields'),
        );
        [$mainFields, $sidebarBlocks] = $fields;

        yield FormField::addColumn('col-12 col-md-8 mainFields');
        yield FormField::addFieldset();
        yield from $this->adminFormFieldManager->getEasyAdminFields($mainFields, $this);

        if ([] !== $sidebarBlocks) {
            yield FormField::addColumn('col-12 col-md-4 columnFields');
            foreach ($sidebarBlocks as $groupName => $block) {
                if (\is_string($groupName)) {
                    yield FormField::addFieldset($groupName);
                }

                $classes = $this->normalizeBlock($block);
                yield from $this->adminFormFieldManager->getEasyAdminFields($classes, $this);
            }
        }
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getIndexFields(): iterable
    {
        yield TextField::new('username', 'admin.user.username.label')
            ->setSortable(false)
            ->renderAsHtml()
            ->formatValue(fn (?string $value, User $user): string => $this->formatUsernameColumn($user));
        yield EmailField::new('email', 'admin.user.email.label')
            ->setSortable(false);
        yield TextField::new('rolesForListing', 'admin.user.role.label')
            ->setSortable(false);
        yield DateTimeField::new('createdAt', 'admin.user.createdAt.label')
            ->setSortable(true);
    }

    /**
     * @param array<int|string, mixed>|class-string<AbstractField<User>> $block
     *
     * @return list<class-string<AbstractField<User>>>
     */
    private function normalizeBlock(array|string $block): array
    {
        if (\is_array($block)) {
            if (isset($block['fields']) && \is_array($block['fields'])) {
                /** @var list<class-string<AbstractField<User>>> $fields */
                $fields = $block['fields'];

                return $fields;
            }

            return $this->filterFieldClassList($block);
        }

        /** @var class-string<AbstractField<User>> $block */
        return [$block];
    }

    /**
     * @param array<int|string, mixed> $values
     *
     * @return list<class-string<AbstractField<User>>>
     */
    private function filterFieldClassList(array $values): array
    {
        $classes = [];
        foreach ($values as $value) {
            if (\is_string($value) && is_subclass_of($value, AbstractField::class)) {
                /** @var class-string<AbstractField<User>> $value */
                $classes[] = $value;
            }
        }

        /** @var list<class-string<AbstractField<User>>> $classes */
        return $classes;
    }

    private function formatUsernameColumn(User $user): string
    {
        $username = $user->getUsername();
        $editUrl = $this->buildEditUrl($user);

        return sprintf(
            '<a href="%s" class="text-muted d-flex justify-content-between align-items-center w-100 ms-2" style="gap: 8px;">'
            .'<span class="text-truncate">%s</span>'
            .'<i class="fa fa-edit me-1 opacity-50"></i>'
            .'</a>',
            htmlspecialchars($editUrl, \ENT_QUOTES),
            htmlspecialchars($username, \ENT_QUOTES),
        );
    }

    private function buildEditUrl(User $user): string
    {
        $generator = clone $this->getAdminUrlGenerator();

        return $generator
            ->setController(static::class)
            ->setAction(Action::EDIT)
            ->setEntityId($user->getId())
            ->generateUrl();
    }

    private function getAdminUrlGenerator(): AdminUrlGenerator
    {
        if (null !== $this->adminUrlGenerator) {
            return $this->adminUrlGenerator;
        }

        if (! isset($this->container)) {
            throw new LogicException('Container not available to generate admin URLs.');
        }

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        $this->adminUrlGenerator = $adminUrlGenerator;

        return $this->adminUrlGenerator;
    }
}
