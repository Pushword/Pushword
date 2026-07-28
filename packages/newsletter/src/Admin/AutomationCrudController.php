<?php

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @extends AbstractCrudController<Automation>
 */
#[AdminRoute(path: '/newsletter/automation', name: 'newsletter_automation')]
class AutomationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SegmentResolver $segmentResolver,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Automation::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('newsletter.automation.label.singular')
            ->setEntityLabelInPlural('newsletter.automation.label.plural')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['name']);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('audience')->add('enabled');
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $preview = Action::new('previewEnrollment', 'newsletter.automation.action.preview', 'fa fa-users')
            ->linkToCrudAction('previewEnrollment')
            ->setCssClass('btn btn-outline-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $preview)
            ->add(Crud::PAGE_DETAIL, $preview)
            ->add(Crud::PAGE_EDIT, $preview);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('newsletter.automation.fieldset.rules')->setIcon('fa fa-filter');
        yield TextField::new('name', 'newsletter.automation.field.name');
        yield AssociationField::new('audience', 'newsletter.automation.field.audience');
        yield BooleanField::new('enabled', 'newsletter.automation.field.enabled')
            ->setHelp('newsletter.automation.field.enabled.help');
        yield TextareaField::new('enrollWhenAsJson', 'newsletter.automation.field.enrollWhen')->hideOnIndex()
            ->setNumOfRows(6)
            ->setHelp('newsletter.automation.field.enrollWhen.help');
        yield TextareaField::new('stopWhenAsJson', 'newsletter.automation.field.stopWhen')->hideOnIndex()
            ->setNumOfRows(4)
            ->setHelp('newsletter.automation.field.stopWhen.help');
        yield DateTimeField::new('enrollFrom', 'newsletter.automation.field.enrollFrom')->hideOnIndex()
            ->setHelp('newsletter.automation.field.enrollFrom.help');
        yield IntegerField::new('stepCount', 'newsletter.automation.field.stepCount')
            ->onlyOnIndex()
            ->formatValue(static fn (mixed $value, ?Automation $automation): string => (string) ($automation?->countSteps() ?? 0));

        yield FormField::addFieldset('newsletter.automation.fieldset.steps')->setIcon('fa fa-list-ol');
        yield CollectionField::new('steps', 'newsletter.automation.field.steps')
            ->hideOnIndex()
            ->allowAdd()
            ->allowDelete()
            ->useEntryCrudForm(AutomationStepCrudController::class);
    }

    /** Who would be enrolled if the automation ran right now. */
    #[AdminRoute(path: '/{entityId}/preview-enrollment', name: 'preview_enrollment')]
    public function previewEnrollment(): RedirectResponse
    {
        $automation = $this->getContext()?->getEntity()->getInstance();
        $audience = $automation instanceof Automation ? $automation->getAudience() : null;

        if (! $automation instanceof Automation || ! $audience instanceof Audience) {
            return new RedirectResponse($this->indexUrl());
        }

        try {
            $count = $this->segmentResolver->count($audience, $automation->getEnrollWhen());
            $this->addFlash('info', \sprintf(
                '%d subscribed contact(s) match; only those registered after %s are enrolled.',
                $count,
                $automation->getEnrollFrom()->format('d M Y H:i'),
            ));
        } catch (SegmentException $segmentException) {
            $this->addFlash('danger', $segmentException->getMessage());
        }

        return new RedirectResponse($this->indexUrl());
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }
}
