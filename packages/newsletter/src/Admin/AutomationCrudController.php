<?php

namespace Pushword\Newsletter\Admin;

use DateTimeImmutable;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Trigger\TriggerSourceRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @extends AbstractCrudController<Automation>
 */
#[AdminRoute(path: '/newsletter/automation', name: 'newsletter_automation')]
class AutomationCrudController extends AbstractCrudController
{
    /** Enough matches to tell a working rule from a runaway one. */
    private const int PREVIEW_LIMIT = 5;

    public function __construct(
        private readonly SegmentResolver $segmentResolver,
        private readonly TriggerSourceRegistry $sources,
        private readonly SiteRegistry $siteRegistry,
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
        return $filters->add('audience')->add('enabled')->add('source');
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $preview = Action::new('previewTrigger', 'newsletter.automation.action.preview', 'fa fa-bolt')
            ->linkToCrudAction('previewTrigger')
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
        $hosts = $this->siteRegistry->getHosts();
        $sources = $this->sources->names();

        yield FormField::addFieldset('newsletter.automation.fieldset.trigger')->setIcon('fa fa-bolt');
        yield TextField::new('name', 'newsletter.automation.field.name');
        yield AssociationField::new('audience', 'newsletter.automation.field.audience');
        yield BooleanField::new('enabled', 'newsletter.automation.field.enabled')
            ->setHelp('newsletter.automation.field.enabled.help');
        yield ChoiceField::new('source', 'newsletter.automation.field.source')
            ->setChoices(array_combine($sources, $sources))
            ->setHelp('newsletter.automation.field.source.help');
        yield TextareaField::new('triggerWhenAsJson', 'newsletter.automation.field.triggerWhen')->hideOnIndex()
            ->setNumOfRows(6)
            ->setHelp('newsletter.automation.field.triggerWhen.help');
        yield ChoiceField::new('hosts', 'newsletter.automation.field.hosts')->hideOnIndex()
            ->setChoices(array_combine($hosts, $hosts))
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setRequired(false)
            ->setHelp('newsletter.automation.field.hosts.help');
        yield DateTimeField::new('activeFrom', 'newsletter.automation.field.activeFrom')->hideOnIndex()
            ->setHelp('newsletter.automation.field.activeFrom.help');

        yield FormField::addFieldset('newsletter.automation.fieldset.recipients')->setIcon('fa fa-filter');
        yield TextareaField::new('recipientWhenAsJson', 'newsletter.automation.field.recipientWhen')->hideOnIndex()
            ->setNumOfRows(4)
            ->setHelp('newsletter.automation.field.recipientWhen.help');
        yield TextareaField::new('stopWhenAsJson', 'newsletter.automation.field.stopWhen')->hideOnIndex()
            ->setNumOfRows(4)
            ->setHelp('newsletter.automation.field.stopWhen.help');

        yield FormField::addFieldset('newsletter.automation.fieldset.steps')->setIcon('fa fa-list-ol');
        yield IntegerField::new('stepCount', 'newsletter.automation.field.stepCount')
            ->onlyOnIndex()
            ->formatValue(static fn (mixed $value, ?Automation $automation): string => (string) ($automation?->countSteps() ?? 0));
        yield CollectionField::new('steps', 'newsletter.automation.field.steps')
            ->hideOnIndex()
            ->allowAdd()
            ->allowDelete()
            ->useEntryCrudForm(AutomationStepCrudController::class);
    }

    /** What this would catch if a tick ran right now, on both sides of the rule. */
    #[AdminRoute(path: '/{entityId}/preview-trigger', name: 'preview_trigger')]
    public function previewTrigger(): RedirectResponse
    {
        $automation = $this->getContext()?->getEntity()->getInstance();
        $audience = $automation instanceof Automation ? $automation->audience : null;
        $source = $automation instanceof Automation ? $this->sources->for($automation) : null;

        if (! $automation instanceof Automation || ! $audience instanceof Audience) {
            return new RedirectResponse($this->indexUrl());
        }

        if (null === $source) {
            $this->addFlash('danger', \sprintf('No trigger source is named "%s".', $automation->source));

            return new RedirectResponse($this->indexUrl());
        }

        try {
            $now = new DateTimeImmutable();
            $occurrences = $source->occurrences($automation, $now, self::PREVIEW_LIMIT);
            $total = $source->count($automation, $now);

            // A drip is addressed to whoever triggered it, so counting a segment
            // over the audience would answer a question nobody asked.
            $addressed = [] !== $occurrences && null !== $occurrences[0]->contact
                ? 'each of them receives the sequence'
                : \sprintf('each would mail %d subscribed contact(s)', $this->segmentResolver->count($audience, $automation->recipientWhen));

            $this->addFlash('info', \sprintf('%d waiting — %s.', $total, $addressed));
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
