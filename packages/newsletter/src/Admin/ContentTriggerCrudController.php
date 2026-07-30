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
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Content\PageMatcher;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\ContentTrigger;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @extends AbstractCrudController<ContentTrigger>
 */
#[AdminRoute(path: '/newsletter/content-trigger', name: 'newsletter_content_trigger')]
class ContentTriggerCrudController extends AbstractCrudController
{
    /** Enough matches to tell a working rule from a runaway one. */
    private const int PREVIEW_LIMIT = 5;

    public function __construct(
        private readonly PageMatcher $pageMatcher,
        private readonly SegmentResolver $segmentResolver,
        private readonly SiteRegistry $siteRegistry,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContentTrigger::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('newsletter.contentTrigger.label.singular')
            ->setEntityLabelInPlural('newsletter.contentTrigger.label.plural')
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
        $preview = Action::new('previewPages', 'newsletter.contentTrigger.action.preview', 'fa fa-file-lines')
            ->linkToCrudAction('previewPages')
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

        yield FormField::addFieldset('newsletter.contentTrigger.fieldset.rules')->setIcon('fa fa-filter');
        yield TextField::new('name', 'newsletter.contentTrigger.field.name');
        yield AssociationField::new('audience', 'newsletter.contentTrigger.field.audience');
        yield BooleanField::new('enabled', 'newsletter.contentTrigger.field.enabled')
            ->setHelp('newsletter.contentTrigger.field.enabled.help');
        yield ChoiceField::new('hosts', 'newsletter.contentTrigger.field.hosts')->hideOnIndex()
            ->setChoices(array_combine($hosts, $hosts))
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setRequired(false)
            ->setHelp('newsletter.contentTrigger.field.hosts.help');
        yield TextareaField::new('pageWhenAsJson', 'newsletter.contentTrigger.field.pageWhen')->hideOnIndex()
            ->setNumOfRows(6)
            ->setHelp('newsletter.contentTrigger.field.pageWhen.help');
        yield TextareaField::new('segmentAsJson', 'newsletter.contentTrigger.field.segment')->hideOnIndex()
            ->setNumOfRows(4)
            ->setHelp('newsletter.contentTrigger.field.segment.help');
        yield DateTimeField::new('triggerFrom', 'newsletter.contentTrigger.field.triggerFrom')->hideOnIndex()
            ->setHelp('newsletter.contentTrigger.field.triggerFrom.help');

        yield FormField::addFieldset('newsletter.contentTrigger.fieldset.mail')->setIcon('fa fa-pen-nib');
        yield IntegerField::new('delayMinutes', 'newsletter.contentTrigger.field.delay')
            ->setHelp('newsletter.contentTrigger.field.delay.help');
        yield TextField::new('subjectTemplate', 'newsletter.contentTrigger.field.subject')
            ->setHelp('newsletter.contentTrigger.field.subject.help');
        yield TextareaField::new('bodyTemplate', 'newsletter.contentTrigger.field.body')->hideOnIndex()
            ->setNumOfRows(14)
            ->setHelp('newsletter.contentTrigger.field.body.help');
    }

    /** What this trigger would catch if a tick ran right now, on both sides of the rule. */
    #[AdminRoute(path: '/{entityId}/preview-pages', name: 'preview_pages')]
    public function previewPages(): RedirectResponse
    {
        $trigger = $this->getContext()?->getEntity()->getInstance();
        $audience = $trigger instanceof ContentTrigger ? $trigger->getAudience() : null;

        if (! $trigger instanceof ContentTrigger || ! $audience instanceof Audience) {
            return new RedirectResponse($this->indexUrl());
        }

        try {
            $now = new DateTimeImmutable();
            $pages = $this->pageMatcher->pages($trigger, $now, self::PREVIEW_LIMIT);
            $total = $this->pageMatcher->count($trigger, $now);
            $recipients = $this->segmentResolver->count($audience, $trigger->getSegment());

            $this->addFlash('info', \sprintf(
                '%d page(s) waiting%s — each would mail %d subscribed contact(s).',
                $total,
                [] === $pages ? '' : ': '.implode(', ', array_map(static fn (object $page): string => (string) $page, $pages)),
                $recipients,
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
