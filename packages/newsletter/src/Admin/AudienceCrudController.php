<?php

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;

/**
 * @extends AbstractCrudController<Audience>
 */
#[AdminRoute(path: '/newsletter/audience', name: 'newsletter_audience')]
class AudienceCrudController extends AbstractCrudController
{
    public function __construct(private readonly SiteRegistry $siteRegistry)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Audience::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('newsletter.audience.label.singular')
            ->setEntityLabelInPlural('newsletter.audience.label.plural')
            ->setDefaultSort(['slug' => 'ASC'])
            ->setSearchFields(['slug', 'name', 'fromEmail']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $hosts = $this->siteRegistry->getHosts();

        yield FormField::addFieldset('newsletter.audience.fieldset.identity')->setIcon('fa fa-users');
        yield TextField::new('name', 'newsletter.audience.field.name');
        yield TextField::new('slug', 'newsletter.audience.field.slug')
            ->setHelp('newsletter.audience.field.slug.help');
        yield ChoiceField::new('mainHost', 'newsletter.audience.field.mainHost')
            ->setChoices(array_combine($hosts, $hosts))
            ->renderAsNativeWidget()
            ->setHelp('newsletter.audience.field.mainHost.help');

        yield FormField::addFieldset('newsletter.audience.fieldset.sender')->setIcon('fa fa-paper-plane');
        yield TextField::new('fromName', 'newsletter.audience.field.fromName')->hideOnIndex();
        yield EmailField::new('fromEmail', 'newsletter.audience.field.fromEmail');
        yield EmailField::new('replyTo', 'newsletter.audience.field.replyTo')->hideOnIndex();

        yield FormField::addFieldset('newsletter.audience.fieldset.rules')->setIcon('fa fa-sliders');
        yield BooleanField::new('requireDoubleOptIn', 'newsletter.audience.field.doubleOptIn')
            ->setHelp('newsletter.audience.field.doubleOptIn.help');
        yield ArrayField::new('interests', 'newsletter.audience.field.interests')->hideOnIndex()
            ->setHelp('newsletter.audience.field.interests.help');
        yield IntegerField::new('rateSeconds', 'newsletter.audience.field.rateSeconds')->hideOnIndex()
            ->setHelp('newsletter.audience.field.rateSeconds.help');
        yield TextField::new('utmSource', 'newsletter.audience.field.utmSource')->hideOnIndex()
            ->setRequired(false)
            ->setHelp('newsletter.audience.field.utmSource.help');
    }
}
