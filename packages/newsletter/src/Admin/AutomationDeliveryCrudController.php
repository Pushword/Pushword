<?php

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Override;
use Pushword\Newsletter\Entity\AutomationDelivery;
use Pushword\Newsletter\Enum\RecipientState;

/**
 * What a drip actually sent, read-only: one row per step per contact.
 *
 * A campaign's recipients are the same ledger for the other half of the bundle.
 * This one is where a sequence's failures are legible at all — a step that the
 * transport refused is logged and then stepped over, so the row is the only
 * lasting trace of a mail somebody never got.
 *
 * @extends AbstractCrudController<AutomationDelivery>
 */
#[AdminRoute(path: '/newsletter/automation-delivery', name: 'newsletter_automation_delivery')]
class AutomationDeliveryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AutomationDelivery::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('newsletter.delivery.label.singular')
            ->setEntityLabelInPlural('newsletter.delivery.label.plural')
            ->setDefaultSort(['attemptedAt' => 'DESC'])
            ->setSearchFields(['contact.email', 'automation.name', 'subject', 'error'])
            // Only a failure has an error, so a column of "Null" badges would
            // read as something having gone wrong on every successful send.
            ->hideNullValues();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        // A drip row is born final, so only the states it can be born in are
        // offered; they read the same in the dropdown as in the badges.
        $states = array_column([RecipientState::Sent, RecipientState::Failed, RecipientState::Bounced], 'value');

        return $filters
            ->add('automation')
            ->add('contact')
            ->add(ChoiceFilter::new('state', 'newsletter.delivery.field.state')->setChoices(array_combine($states, $states)))
            ->add('attemptedAt');
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        // Opening a row is the only action it has: the index truncates a long
        // subject and a long transport error, and which run of the sequence a
        // row belongs to is only worth the width on the row you asked about.
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('attemptedAt', 'newsletter.delivery.field.attemptedAt');
        yield AssociationField::new('contact', 'newsletter.delivery.field.contact');
        yield AssociationField::new('automation', 'newsletter.delivery.field.automation');
        yield IntegerField::new('position', 'newsletter.delivery.field.position');
        yield TextField::new('subject', 'newsletter.delivery.field.subject');
        yield ChoiceField::new('state', 'newsletter.delivery.field.state')
            ->renderAsBadges([
                RecipientState::Sent->value => 'success',
                RecipientState::Failed->value => 'danger',
                RecipientState::Bounced->value => 'warning',
            ]);
        yield IntegerField::new('subjectId', 'newsletter.delivery.field.subjectId')->onlyOnDetail();
        yield TextField::new('error', 'newsletter.delivery.field.error');
    }
}
