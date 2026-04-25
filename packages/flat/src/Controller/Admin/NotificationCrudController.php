<?php

declare(strict_types=1);

namespace Pushword\Flat\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Override;
use Pushword\Flat\Entity\AdminNotification;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Admin CRUD controller for viewing and managing notifications.
 * Read-only: no create or edit actions, only view and delete.
 *
 * @extends AbstractCrudController<AdminNotification>
 */
final class NotificationCrudController extends AbstractCrudController
{
    private const array TYPE_CHOICES = [
        'flatNotificationTypeConflict' => AdminNotification::TYPE_CONFLICT,
        'flatNotificationTypeSyncError' => AdminNotification::TYPE_SYNC_ERROR,
        'flatNotificationTypeLockInfo' => AdminNotification::TYPE_LOCK_INFO,
    ];

    public static function getEntityFqcn(): string
    {
        return AdminNotification::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('flatNotificationSingular')
            ->setEntityLabelInPlural('flatNotificationPlural')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'flatNotificationIndexTitle')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (AdminNotification $n): TranslatableMessage => new TranslatableMessage('flatNotificationDetailTitle', ['%id%' => $n->id]))
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::DELETE]);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type')->setChoices(self::TYPE_CHOICES))
            ->add(BooleanFilter::new('isRead'));
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield ChoiceField::new('type')
            ->setChoices(self::TYPE_CHOICES)
            ->renderAsBadges([
                AdminNotification::TYPE_CONFLICT => 'danger',
                AdminNotification::TYPE_SYNC_ERROR => 'warning',
                AdminNotification::TYPE_LOCK_INFO => 'info',
            ]);

        yield TextareaField::new('message')
            ->setMaxLength(100)
            ->hideOnForm();

        yield TextField::new('host')
            ->setLabel('flatNotificationHostLabel');

        yield BooleanField::new('isRead')
            ->setLabel('flatNotificationReadLabel')
            ->renderAsSwitch(false);

        yield DateTimeField::new('createdAt')
            ->setLabel('flatNotificationDateLabel')
            ->setFormat('yyyy-MM-dd HH:mm');
    }
}
