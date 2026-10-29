<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use LogicException;
use Override;
use Pushword\AdminBlockEditor\Form\EditorjsType;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Controller\CriteriaController;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Service\EmailTemplateRegistry;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Service\NewsletterMailer;
use Pushword\Newsletter\Service\SenderProfileRegistry;
use Pushword\Newsletter\Utm\UtmTag;
use Pushword\Newsletter\Validation\CampaignPreflight;
use Pushword\Newsletter\Validation\CampaignValidationFailed;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * @extends AbstractCrudController<Campaign>
 */
#[AdminRoute(path: '/newsletter/campaign', name: 'newsletter_campaign')]
class CampaignCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CampaignSender $campaignSender,
        private readonly NewsletterMailer $mailer,
        private readonly SegmentResolver $segmentResolver,
        private readonly ContactRepository $contactRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EmailTemplateRegistry $emailTemplates,
        private readonly MailRenderer $renderer,
        private readonly SiteRegistry $sites,
        private readonly RequestStack $requests,
        private readonly CampaignPreflight $preflight,
        private readonly SenderProfileRegistry $senderProfiles,
        private readonly EditorialTimezone $timezone,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Campaign::class;
    }

    #[Override]
    public function createEntity(string $entityFqcn): Campaign
    {
        $request = $this->requests->getCurrentRequest();
        $id = $request?->query->getInt('duplicate') ?? 0;
        if (0 === $id) {
            $campaign = new Campaign();
            $value = $request?->query->getString('scheduledAt') ?? '';
            if ('' !== $value) {
                $date = DateTimeImmutable::createFromFormat(10 === \strlen($value) ? '!Y-m-d' : DateTimeInterface::ATOM, $value, $this->timezone->timezone);
                if (false === $date || false !== DateTimeImmutable::getLastErrors()) {
                    throw new BadRequestHttpException('Invalid calendar date.');
                }

                $campaign->scheduledAt = DateTimeImmutable::createFromMutable($this->timezone->parse($date->format(DateTimeInterface::ATOM)));
            }

            return $campaign;
        }

        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $original = $this->entityManager->find(Campaign::class, $id) ?? throw $this->createNotFoundException();

        return $original->duplicate();
    }

    /** @param AdminContext<Campaign> $context */
    #[Override]
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $context->getRequest()->query->remove('duplicate');
        $context->getRequest()->query->remove('scheduledAt');

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        $crud = $crud
            ->overrideTemplate('layout', '@PushwordNewsletter/admin/layout.html.twig')
            ->overrideTemplate('crud/index', '@PushwordNewsletter/admin/index.html.twig')
            ->overrideTemplate('crud/detail', '@PushwordNewsletter/admin/campaign_detail.html.twig')
            ->overrideTemplate('crud/new', '@PushwordNewsletter/admin/compose_new.html.twig')
            ->overrideTemplate('crud/edit', '@PushwordNewsletter/admin/compose_edit.html.twig')
            ->addFormTheme('@PushwordNewsletter/admin/campaign_form_theme.html.twig')
            ->setEntityLabelInSingular('newsletter.campaign.label.singular')
            ->setEntityLabelInPlural('newsletter.campaign.label.plural')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['subject', 'workingTitle']);

        if (class_exists(EditorjsType::class)) {
            return $crud->addFormTheme('@PushwordNewsletter/admin/block_editor_theme.html.twig');
        }

        return $crud;
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('audience')->add('status')->add('editorialStage');
    }

    #[Override]
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_DETAIL !== $responseParameters->get('pageName')) {
            return $responseParameters;
        }

        $campaign = $this->getContext()?->getEntity()->getInstance();
        if (! $campaign instanceof Campaign) {
            return $responseParameters;
        }

        $responseParameters->set('preflight_issues', $this->preflight->check($campaign));
        if (! $campaign->audience instanceof Audience) {
            return $responseParameters;
        }

        $audience = $campaign->audience;
        $site = $this->sites->get($audience->mainHost);
        $contact = new Contact($audience, 'preview@example.tld');
        $contact->name = 'Alex';
        $contact->customProperties = ['firstName' => 'Alex'];
        $contact->locale = $site->locale;

        try {
            $sender = $this->senderProfiles->resolve($campaign);
            $responseParameters->set('preview_sender', ($sender->fromName ?? $audience->fromName).' <'.($sender->fromEmail ?? $audience->fromEmail).'>');
            $responseParameters->set('preview_html', $this->renderer->html(
                $audience,
                $contact,
                $campaign->subject,
                $campaign->bodyMarkdown,
                $campaign->preheader,
                $campaign->isTransactional() ? null : $site->baseUrl.'/#unsubscribe',
                null,
                sendContext: $sender,
                locale: $site->locale,
                emailLayout: $campaign->emailLayout,
            ));
        } catch (Throwable) {
            // The preflight report already explains why rendering failed.
        }

        return $responseParameters;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $duplicate = Action::new('duplicate', 'newsletter.campaign.action.duplicate', 'fa fa-copy')
            ->linkToUrl(fn (Campaign $campaign): string => $this->urlGenerator->generate('admin_newsletter_campaign_new', ['duplicate' => $campaign->id]))
            ->setCssClass('btn btn-outline-secondary');

        $calendar = Action::new('calendar', 'newsletter.calendar.title', 'fa fa-calendar')
            ->linkToRoute('admin_newsletter_campaign_calendar')
            ->createAsGlobalAction();

        $catalogue = Action::new('catalogue', 'newsletter.catalogue.browseBySite', 'fa fa-filter')
            ->linkToRoute('admin_newsletter_campaign_catalogue')
            ->createAsGlobalAction();

        $send = Action::new('send', 'newsletter.campaign.action.send', 'fa fa-paper-plane')
            ->linkToCrudAction('send')
            ->setTemplatePath('@pwAdmin/crud/action_post.html.twig')
            ->displayIf(static fn (Campaign $campaign): bool => $campaign->isDraft())
            ->setCssClass('btn btn-success');

        $cancelSchedule = Action::new('cancelSchedule', 'newsletter.campaign.action.cancelSchedule', 'fa fa-ban')
            ->linkToCrudAction('cancelSchedule')
            ->setTemplatePath('@pwAdmin/crud/action_post.html.twig')
            ->displayIf(static fn (Campaign $campaign): bool => $campaign->isScheduled())
            ->setCssClass('btn btn-outline-warning');

        $preview = Action::new('previewSegment', 'newsletter.campaign.action.previewSegment', 'fa fa-users')
            ->linkToCrudAction('previewSegment')
            ->displayIf(static fn (Campaign $campaign): bool => $campaign->isDraft())
            ->setCssClass('btn btn-outline-secondary');

        // The counters answer "how many"; this opens the rows behind them. A
        // campaign only has rows once armed, which is exactly when the count
        // stops being zero.
        $recipients = Action::new('recipients', 'newsletter.campaign.action.recipients', 'fa fa-list-check')
            ->linkToUrl(fn (Campaign $campaign): string => $this->recipientsUrl($campaign))
            ->displayIf(static fn (Campaign $campaign): bool => $campaign->recipientCount > 0)
            ->setCssClass('btn btn-outline-secondary');

        // A test never touches recipients or counters, so it stays available.
        $sendTest = Action::new('sendTest', 'newsletter.campaign.action.sendTest', 'fa fa-flask')
            ->linkToCrudAction('sendTest')
            ->setCssClass('btn btn-outline-secondary');

        foreach ([Crud::PAGE_DETAIL, Crud::PAGE_EDIT] as $page) {
            $actions = $actions
                ->add($page, $send)
                ->add($page, $cancelSchedule)
                ->add($page, $preview)
                ->add($page, $recipients)
                ->add($page, $duplicate)
                ->add($page, $sendTest);
        }

        return $actions
            ->disable(Action::SAVE_AND_ADD_ANOTHER)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, static fn (Action $action): Action => $action->setLabel('newsletter.campaign.action.saveDraft'))
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action
                ->setLabel('newsletter.campaign.action.create')
                ->linkToRoute('admin_newsletter_campaign_new'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $catalogue)
            ->add(Crud::PAGE_INDEX, $calendar)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $action): Action => $action
                ->setLabel('newsletter.campaign.action.review')
                ->setCssClass('action-detail btn btn-outline-primary'))
            ->add(Crud::PAGE_INDEX, $recipients);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $isForm = \in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true);
        $campaign = $this->getContext()?->getEntity()->getInstance();
        $preparing = ! $campaign instanceof Campaign || $campaign->isDraft();
        $audienceField = AssociationField::new('audience', 'newsletter.campaign.field.audience')->setFormTypeOption('disabled', ! $preparing)
            ->setRequired(false)
            ->setFormTypeOption('label_attr', ['id' => 'newsletter-campaign-audience-label'])
            ->setFormTypeOption('attr', ['aria-labelledby' => 'newsletter-campaign-audience-label']);

        yield FormField::addFieldset('newsletter.editorial.preparation')->setIcon('fa fa-calendar');
        yield TextField::new('workingTitle', 'newsletter.editorial.workingTitle')->setRequired(false)->hideOnIndex()->setColumns('col-12');
        yield TextField::new('title', 'newsletter.editorial.workingTitle')->onlyOnIndex()->setSortable(false);
        yield ChoiceField::new('editorialStage', 'newsletter.editorial.stage')
            ->setColumns('col-12')
            ->renderAsNativeWidget()
            ->setFormTypeOption('placeholder', false)
            ->setFormTypeOption('disabled', ! $preparing);
        yield TextareaField::new('internalNotes', 'newsletter.editorial.internalNotes')->setRequired(false)->hideOnIndex()->setColumns('col-12')->setNumOfRows(3);

        if ($isForm) {
            yield FormField::addFieldset('newsletter.campaign.fieldset.design')->setIcon('fa fa-palette');
            yield $audienceField->setColumns('col-12');
            yield ChoiceField::new('emailLayout', 'newsletter.campaign.design.label')->setFormTypeOption('disabled', ! $preparing)
                ->setChoices([
                    'newsletter.campaign.design.inherit' => '',
                    'newsletter.design.simple' => 'simple',
                    'newsletter.design.wrapped' => 'framed',
                    'newsletter.design.personal' => 'personal',
                    ...$this->emailTemplates->choices(),
                ])
                ->renderAsNativeWidget()
                ->setRequired(false)
                ->setColumns('col-12')
                ->setFormTypeOption('placeholder', false)
                ->setFormTypeOption('attr', [
                    'data-pw-campaign-design' => json_encode($this->audienceDesigns(), \JSON_THROW_ON_ERROR),
                    'data-choices-url' => $this->urlGenerator->generate('admin_newsletter_template_choices'),
                ]);
        }

        yield ChoiceField::new('senderProfile', 'newsletter.sender.label')
            ->setChoices(['newsletter.sender.inherit' => '', ...$this->senderProfiles->choices()])
            ->renderAsNativeWidget()->setRequired(false)->hideOnIndex()->setColumns('col-12')
            ->setFormTypeOption('placeholder', false)
            ->setFormTypeOption('disabled', ! $preparing);

        yield FormField::addFieldset('newsletter.campaign.fieldset.content')->setIcon('fa fa-pen-nib');
        yield TextField::new('subject', 'newsletter.campaign.field.subject')->setFormTypeOption('disabled', ! $preparing)->setRequired(false)->hideOnIndex()->setColumns('col-12');
        yield ChoiceField::new('status', 'newsletter.campaign.field.status')
            ->renderAsBadges([
                CampaignStatus::Draft->value => 'secondary',
                CampaignStatus::Scheduled->value => 'info',
                CampaignStatus::Sending->value => 'warning',
                CampaignStatus::Sent->value => 'success',
            ])
            ->hideOnForm();
        yield DateTimeField::new('sendDate', 'newsletter.campaign.field.sendDate')->onlyOnIndex()->setSortable(false)->formatValue(
            static fn (mixed $value): string => $value instanceof DateTimeInterface ? $value->format('d/m/Y H:i') : '—',
        );
        yield TextField::new('preheader', 'newsletter.campaign.field.preheader')->setFormTypeOption('disabled', ! $preparing)->hideOnIndex()->setColumns('col-12')
            ->setHelp('newsletter.campaign.field.preheader.help')
            ->setFormTypeOption('context_help', true);
        yield $this->bodyField()->setFormTypeOption('disabled', ! $preparing);

        yield FormField::addFieldset('newsletter.campaign.fieldset.audience')->setIcon('fa fa-users');
        if (! $isForm) {
            yield $audienceField;
        }

        yield CriteriaField::new('segmentAsJson', 'newsletter.campaign.field.segment', CriteriaController::SIDE_CONTACT, $this->urlGenerator)->setFormTypeOption('disabled', ! $preparing)
            ->setNumOfRows(6)
            ->setHelp('newsletter.campaign.field.segment.help')
            ->setFormTypeOption('context_help', true);
        yield FormField::addFieldset('newsletter.campaign.fieldset.schedule')->setIcon('fa fa-clock')
            ->setHelp('newsletter.campaign.fieldset.scheduleHelp')
            ->setFormTypeOption('context_help', true);
        yield DateTimeField::new('scheduledAt', 'newsletter.campaign.field.scheduledAt')->hideOnIndex()
            ->setTimezone($this->timezone->timezone->getName())
            ->setFormTypeOption('view_timezone', $this->timezone->timezone->getName())
            ->setFormTypeOption('disabled', ! $preparing)
            ->setHelp('newsletter.campaign.field.scheduledAt.help')
            ->setFormTypeOption('context_help', 'newsletterHelpSchedule');
        yield FormField::addFieldset('newsletter.campaign.fieldset.languages')->setIcon('fa fa-language')
            ->setHelp($this->coverage())
            ->hideOnIndex()
            ->collapsible()
            ->renderCollapsed();
        yield TextareaField::new('translationsAsJson', 'newsletter.campaign.field.translations')->setFormTypeOption('disabled', ! $preparing)
            ->hideOnIndex()
            ->setRequired(false)
            ->setNumOfRows(10)
            ->setHelp('newsletter.campaign.field.translations.help');

        yield FormField::addFieldset('newsletter.campaign.fieldset.advanced')->setIcon('fa fa-gear')
            ->collapsible()
            ->renderCollapsed();
        yield TextField::new('slug', 'newsletter.campaign.field.slug')->setFormTypeOption('disabled', ! $preparing)->hideOnIndex()
            ->setRequired(false)
            ->setHelp('newsletter.campaign.field.slug.help')
            ->setFormTypeOption('context_help', true);
        yield IntegerField::new('rateSeconds', 'newsletter.campaign.field.rateSeconds')->setFormTypeOption('disabled', ! $preparing)->hideOnIndex()
            ->setRequired(false)
            ->setHelp('newsletter.campaign.field.rateSeconds.help')
            ->setFormTypeOption('context_help', 'newsletterHelpCadence');
        yield BooleanField::new('transactional', 'newsletter.campaign.field.transactional')->setFormTypeOption('disabled', ! $preparing)->hideOnIndex()
            ->setHelp('newsletter.campaign.field.transactional.help')
            ->setFormTypeOption('context_help', 'newsletterHelpTransactional');

        yield FormField::addFieldset('newsletter.campaign.fieldset.performance')->setIcon('fa fa-chart-simple')->onlyOnDetail();
        yield IntegerField::new('recipientCount', 'newsletter.campaign.field.recipients')->hideOnForm();
        yield IntegerField::new('sentCount', 'newsletter.campaign.field.sent')->hideOnForm();
        yield IntegerField::new('failedCount', 'newsletter.campaign.field.failed')->hideOnForm()->hideOnIndex();
        yield IntegerField::new('unsubCount', 'newsletter.campaign.field.unsubscribed')->hideOnForm()->hideOnIndex();
        yield IntegerField::new('bounceCount', 'newsletter.campaign.field.bounced')->hideOnForm()->hideOnIndex();
        yield IntegerField::new('clickCount', 'newsletter.campaign.field.clicks')->hideOnForm()->hideOnIndex();
        yield DateTimeField::new('sentAt', 'newsletter.campaign.field.sentAt')->onlyOnDetail()->formatValue(
            static fn (mixed $value): string => $value instanceof DateTimeInterface ? $value->format('d M Y H:i') : '—',
        );
    }

    /** @return array<int, array{name: string, layout: string, url: string}> */
    private function audienceDesigns(): array
    {
        $designs = [];
        foreach ($this->entityManager->getRepository(Audience::class)->findAll() as $audience) {
            $designs[$audience->id ?? throw new LogicException()] = [
                'name' => $audience->name,
                'layout' => $audience->emailLayout,
                'url' => $this->urlGenerator->generate('admin_newsletter_audience_email_design', ['entityId' => $audience->id]),
            ];
        }

        return $designs;
    }

    /**
     * Which languages the audience is read in, and which of them this campaign
     * is written in — the denominator being the contacts' own locales rather
     * than the site's hosts, since an audience is a list of readers and not a
     * set of domains.
     *
     * Whoever is about to press Send should be able to see, before doing so,
     * that four of eight languages will receive the default text.
     */
    private function coverage(): string
    {
        $campaign = $this->getContext()?->getEntity()->getInstance();

        if (! $campaign instanceof Campaign || ! $campaign->audience instanceof Audience) {
            return '';
        }

        $spoken = $this->contactRepository->localesIn($campaign->audience);

        // One language is not a coverage question, and a campaign not yet saved
        // has no audience to ask about.
        if (\count($spoken) < 2) {
            return '';
        }

        $translated = array_intersect($spoken, $campaign->translatedLocales());
        $missing = array_diff($spoken, $translated);
        $read = \sprintf('Read in %d languages: %s.', \count($spoken), implode(', ', $spoken));

        if ([] === $missing) {
            return $read.' All of them translated.';
        }

        return \sprintf(
            '%s Translated: %s. %s receive the text above.',
            $read,
            [] === $translated ? 'none' : implode(', ', $translated),
            implode(', ', $missing),
        );
    }

    #[AdminRoute(path: '/{entityId}/send', name: 'send', options: ['methods' => ['POST']])]
    public function send(Request $request): RedirectResponse
    {
        $campaign = $this->campaign();

        if (! $campaign instanceof Campaign) {
            return new RedirectResponse($this->indexUrl());
        }

        $this->assertActionToken($request, 'send', (int) $campaign->id);

        if (! $campaign->isDraft()) {
            $this->addFlash('warning', 'newsletter.campaign.flash.notDraft');

            return new RedirectResponse($this->indexUrl());
        }

        try {
            $count = $this->campaignSender->send($campaign);
            $this->addFlash('success', $campaign->isScheduled() ? 'newsletter.calendar.scheduled' : \sprintf('%d recipient(s) queued at 1 mail / %d s.', $count, $campaign->getEffectiveRateSeconds()));
        } catch (CampaignValidationFailed) {
            $this->addFlash('danger', 'newsletter.preflight.blocked');

            return $this->redirectToRoute('admin_newsletter_campaign_detail', ['entityId' => $campaign->id]);
        } catch (Throwable $throwable) {
            $this->addFlash('danger', $throwable->getMessage());
        }

        return new RedirectResponse($this->indexUrl());
    }

    #[AdminRoute(path: '/{entityId}/schedule', name: 'schedule', options: ['methods' => ['POST']])]
    public function schedule(Request $request): RedirectResponse
    {
        $campaign = $this->campaign();

        if (! $campaign instanceof Campaign) {
            return new RedirectResponse($this->indexUrl());
        }

        $this->assertActionToken($request, 'schedule', (int) $campaign->id);

        $when = $campaign->scheduledAt;

        if (! $campaign->isDraft()) {
            $this->addFlash('warning', 'newsletter.campaign.flash.notDraft');
        } elseif (! $when instanceof DateTimeImmutable) {
            $this->addFlash('danger', 'newsletter.campaign.flash.noDate');
        } else {
            try {
                $this->campaignSender->schedule($campaign, $when);
                $this->addFlash('success', 'newsletter.calendar.scheduled');
            } catch (LogicException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        return new RedirectResponse($this->indexUrl());
    }

    #[AdminRoute(path: '/{entityId}/cancel-schedule', name: 'cancel_schedule', options: ['methods' => ['POST']])]
    public function cancelSchedule(Request $request): RedirectResponse
    {
        $campaign = $this->campaign();

        if ($campaign instanceof Campaign) {
            $this->assertActionToken($request, 'cancelSchedule', (int) $campaign->id);
        }

        if ($campaign instanceof Campaign && $campaign->isScheduled()) {
            $campaign->revertToDraft();
            $this->entityManager->flush();
            $this->addFlash('success', 'newsletter.campaign.flash.backToDraft');
        }

        return new RedirectResponse($this->indexUrl());
    }

    private function assertActionToken(Request $request, string $action, int $id): void
    {
        if (! $this->isCsrfTokenValid('admin_action_'.$action.'_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /** How many contacts the segment reaches right now — a segment you cannot count is one you will not trust. */
    #[AdminRoute(path: '/{entityId}/preview-segment', name: 'preview_segment')]
    public function previewSegment(): RedirectResponse
    {
        $campaign = $this->campaign();
        $audience = $campaign?->audience;

        if (! $campaign instanceof Campaign || ! $audience instanceof Audience) {
            return new RedirectResponse($this->indexUrl());
        }

        try {
            $subscribed = $this->segmentResolver->count($audience, $campaign->segment);
            $mailable = $this->segmentResolver->countMailable($audience, $campaign->segment);

            $this->addFlash('info', $subscribed === $mailable
                ? \sprintf('%d subscribed contact(s) match this segment.', $subscribed)
                : \sprintf('%d subscribed contact(s) match this segment, %d of them can be mailed.', $subscribed, $mailable));
        } catch (SegmentException $segmentException) {
            $this->addFlash('danger', $segmentException->getMessage());
        }

        return new RedirectResponse($this->detailUrl($campaign));
    }

    #[AdminRoute(path: '/{entityId}/send-test', name: 'send_test', options: ['methods' => ['GET', 'POST']])]
    public function sendTest(Request $request): Response
    {
        $campaign = $this->campaign();
        $audience = $campaign?->audience;

        if (! $campaign instanceof Campaign || ! $audience instanceof Audience) {
            return new RedirectResponse($this->indexUrl());
        }

        $backUrl = $this->detailUrl($campaign);
        $csrfId = 'newsletter_test_'.$campaign->id;

        if ($request->isMethod('POST')) {
            if (! $this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'newsletter.campaign.flash.invalidToken');

                return new RedirectResponse($backUrl);
            }

            $this->mailTests($request, $campaign, $audience);

            return new RedirectResponse($backUrl);
        }

        return $this->render('@PushwordNewsletter/admin/campaign_test_send.html.twig', [
            'campaign' => $campaign,
            'default_emails' => $this->getUser()?->getUserIdentifier() ?? '',
            // Only offered once a translation exists: a list of one language is
            // a choice nobody has.
            'locales' => $campaign->translatedLocales(),
            'back_url' => $backUrl,
            'csrf_id' => $csrfId,
        ]);
    }

    private function mailTests(Request $request, Campaign $campaign, Audience $audience): void
    {
        $split = preg_split('/[\s,;]+/', (string) $request->request->get('emails', ''));
        $addresses = array_filter(
            array_map(trim(...), false !== $split ? $split : []),
            static fn (string $address): bool => '' !== $address,
        );

        // Which language to proofread. Empty is the audience host's own, which
        // is the campaign's default text.
        $locale = trim($request->request->getString('locale'));
        $content = $campaign->contentFor($locale);

        $sent = [];
        $failed = [];

        foreach ($addresses as $address) {
            if (false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                $failed[] = $address;

                continue;
            }

            try {
                $this->mailer->sendTest($audience, $content['subject'], $content['bodyMarkdown'], $content['preheader'], $address, UtmTag::forCampaign($campaign), $locale, $campaign->isTransactional(), $campaign->emailLayout, $this->senderProfiles->resolve($campaign));
                $sent[] = $address;
            } catch (Throwable $throwable) {
                $failed[] = $address.' ('.$throwable->getMessage().')';
            }
        }

        if ([] !== $sent) {
            $this->addFlash('success', \sprintf('Test sent to %s.', implode(', ', $sent)));
        }

        if ([] !== $failed) {
            $this->addFlash('danger', \sprintf('Not sent: %s', implode(', ', $failed)));
        }
    }

    private function bodyField(): Field|TextareaField
    {
        if (! class_exists(EditorjsType::class)) {
            return TextareaField::new('bodyMarkdown', 'newsletter.campaign.field.body')
                ->hideOnIndex()
                ->setHelp('newsletter.campaign.field.body.help')
                ->setNumOfRows(18)->setColumns('col-12');
        }

        $campaign = $this->getContext()?->getEntity()->getInstance();
        $host = $campaign instanceof Campaign ? ($campaign->audience->mainHost ?? '') : '';

        // page_id is page-only context (the PagesList block preview URL); a
        // campaign has no page, so it stays empty but the key must exist.
        return Field::new('bodyMarkdown', 'newsletter.campaign.field.body')
            ->setColumns('col-12')
            ->setHelp('newsletter.campaign.field.body.help')
            ->setFormType(EditorjsType::class)
            ->setFormTypeOptions(['required' => false, 'attr' => ['page_host' => $host, 'page_id' => '']])
            ->hideOnIndex();
    }

    private function campaign(): ?Campaign
    {
        $campaign = $this->getContext()?->getEntity()->getInstance();

        if (! $campaign instanceof Campaign) {
            $this->addFlash('danger', 'newsletter.campaign.flash.notFound');

            return null;
        }

        return $campaign;
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    /** The send ledger of one campaign: its own rows, none of any other's. */
    private function recipientsUrl(Campaign $campaign): string
    {
        return $this->adminUrlGenerator
            ->setController(CampaignRecipientCrudController::class)
            ->setAction(Action::INDEX)
            ->set('filters', ['campaign' => ['comparison' => ComparisonType::EQ, 'value' => $campaign->id]])
            ->generateUrl();
    }

    private function detailUrl(Campaign $campaign): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($campaign->id)
            ->generateUrl();
    }
}
