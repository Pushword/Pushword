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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Override;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Enum\RecipientState;

/**
 * The send ledger, read-only: who a newsletter went to, one row per contact, and
 * what became of their mail.
 *
 * The counters on a campaign say how many; this says which — and it is the only
 * place a failure's reason is legible, since the transport's message is kept on
 * the row rather than summed into `failedCount`.
 *
 * Nothing is writable: a row is the record of a send that happened, and editing
 * it would make the ledger disagree with what left the server. What can still be
 * acted on — a contact who bounced, a campaign to re-send — is reachable from
 * the links here.
 *
 * @extends AbstractCrudController<CampaignRecipient>
 */
#[AdminRoute(path: '/newsletter/campaign-recipient', name: 'newsletter_campaign_recipient')]
class CampaignRecipientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CampaignRecipient::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('newsletter.recipient.label.singular')
            ->setEntityLabelInPlural('newsletter.recipient.label.plural')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['contact.email', 'error'])
            // Most rows have no error and half have no send date yet; a column of
            // "Null" badges would read as something having gone wrong.
            ->hideNullValues();
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        // The states read the same in the dropdown as they do in the badges, so
        // the label of a choice is its value.
        $states = array_column(RecipientState::cases(), 'value');

        return $filters
            ->add('campaign')
            ->add(ChoiceFilter::new('state', 'newsletter.recipient.field.state')->setChoices(array_combine($states, $states)))
            ->add('sentAt');
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('campaign', 'newsletter.recipient.field.campaign');
        yield AssociationField::new('contact', 'newsletter.recipient.field.contact');
        yield ChoiceField::new('state', 'newsletter.recipient.field.state')
            ->renderAsBadges([
                RecipientState::Pending->value => 'secondary',
                RecipientState::Sent->value => 'success',
                RecipientState::Skipped->value => 'info',
                RecipientState::Failed->value => 'danger',
                RecipientState::Bounced->value => 'warning',
            ]);
        yield DateTimeField::new('sentAt', 'newsletter.recipient.field.sentAt');
        yield TextField::new('error', 'newsletter.recipient.field.error');
    }
}
