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
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\ContactStatus;

/**
 * @extends AbstractCrudController<Contact>
 */
#[AdminRoute(path: '/newsletter/contact', name: 'newsletter_contact')]
class ContactCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Contact::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('newsletter.contact.label.singular')
            ->setEntityLabelInPlural('newsletter.contact.label.plural')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['email', 'name', 'tags']);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('audience')
            ->add('status')
            ->add('locale')
            ->add('createdAt');
    }

    /**
     * Contacts are not created by hand: a row without a recorded opt-in is a row
     * that cannot be defended. Use the form, the API or an import.
     */
    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn('col-12 col-lg-8 mainFields');
        yield FormField::addFieldset('newsletter.contact.fieldset.identity');
        yield EmailField::new('email', 'newsletter.contact.field.email');
        yield TextField::new('name', 'newsletter.contact.field.name');
        yield ChoiceField::new('status', 'newsletter.contact.field.status')
            ->renderAsBadges([
                ContactStatus::Pending->value => 'warning',
                ContactStatus::Subscribed->value => 'success',
                ContactStatus::Unsubscribed->value => 'secondary',
                ContactStatus::Bounced->value => 'danger',
            ])
            ->hideOnForm();
        yield TextField::new('tags', 'newsletter.contact.field.tags')->setRequired(false);
        yield TextareaField::new('unmanagedPropertiesAsYaml', 'newsletter.contact.field.customProperties')
            ->hideOnIndex()
            ->setHelp('newsletter.contact.field.customProperties.help');

        yield FormField::addColumn('col-12 col-lg-4 columnFields');
        yield FormField::addFieldset('newsletter.contact.fieldset.consent');
        yield AssociationField::new('audience', 'newsletter.contact.field.audience');
        yield TextField::new('locale', 'newsletter.contact.field.locale');
        yield TextField::new('source', 'newsletter.contact.field.source')->hideOnIndex()->setDisabled();
        yield TextField::new('optinHost', 'newsletter.contact.field.optinHost')->hideOnIndex()->setDisabled();
        yield TextField::new('optinIp', 'newsletter.contact.field.optinIp')->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'newsletter.contact.field.registeredAt')->hideOnForm();
        yield DateTimeField::new('confirmedAt', 'newsletter.contact.field.confirmedAt')->onlyOnDetail();
        yield DateTimeField::new('unsubscribedAt', 'newsletter.contact.field.unsubscribedAt')->onlyOnDetail();
        yield DateTimeField::new('bouncedAt', 'newsletter.contact.field.bouncedAt')->onlyOnDetail();
    }
}
