<?php

namespace Pushword\Newsletter\Admin;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Pushword\AdminBlockEditor\Form\EditorjsType;
use Pushword\Newsletter\Controller\CriteriaController;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Enum\CampaignStatus;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Service\NewsletterMailer;
use Pushword\Newsletter\Utm\UtmTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Campaign::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        $crud = $crud
            ->setEntityLabelInSingular('newsletter.campaign.label.singular')
            ->setEntityLabelInPlural('newsletter.campaign.label.plural')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['subject']);

        if (class_exists(EditorjsType::class)) {
            return $crud->addFormTheme('@PushwordAdminBlockEditor/editorjs_widget.html.twig');
        }

        return $crud;
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('audience')->add('status');
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $send = Action::new('send', 'newsletter.campaign.action.send', 'fa fa-paper-plane')
            ->linkToCrudAction('send')
            ->displayIf(static fn (Campaign $campaign): bool => $campaign->isDraft())
            ->setCssClass('btn btn-success');

        $schedule = Action::new('schedule', 'newsletter.campaign.action.schedule', 'fa fa-clock')
            ->linkToCrudAction('schedule')
            ->displayIf(static fn (Campaign $campaign): bool => $campaign->isDraft())
            ->setCssClass('btn btn-primary');

        $cancelSchedule = Action::new('cancelSchedule', 'newsletter.campaign.action.cancelSchedule', 'fa fa-ban')
            ->linkToCrudAction('cancelSchedule')
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

        foreach ([Crud::PAGE_INDEX, Crud::PAGE_DETAIL, Crud::PAGE_EDIT] as $page) {
            $actions = $actions
                ->add($page, $send)
                ->add($page, $schedule)
                ->add($page, $cancelSchedule)
                ->add($page, $preview)
                ->add($page, $recipients)
                ->add($page, $sendTest);
        }

        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('newsletter.campaign.fieldset.content')->setIcon('fa fa-pen-nib');
        yield TextField::new('subject', 'newsletter.campaign.field.subject');
        yield ChoiceField::new('status', 'newsletter.campaign.field.status')
            ->renderAsBadges([
                CampaignStatus::Draft->value => 'secondary',
                CampaignStatus::Scheduled->value => 'info',
                CampaignStatus::Sending->value => 'warning',
                CampaignStatus::Sent->value => 'success',
            ])
            ->hideOnForm();
        yield TextField::new('preheader', 'newsletter.campaign.field.preheader')->hideOnIndex()
            ->setHelp('newsletter.campaign.field.preheader.help');
        yield TextField::new('slug', 'newsletter.campaign.field.slug')->hideOnIndex()
            ->setRequired(false)
            ->setHelp('newsletter.campaign.field.slug.help');
        yield $this->bodyField();

        yield FormField::addFieldset('newsletter.campaign.fieldset.audience')->setIcon('fa fa-users');
        yield AssociationField::new('audience', 'newsletter.campaign.field.audience')->hideOnIndex();
        yield CriteriaField::new('segmentAsJson', 'newsletter.campaign.field.segment', CriteriaController::SIDE_CONTACT, $this->urlGenerator)
            ->setNumOfRows(6)
            ->setHelp('newsletter.campaign.field.segment.help');
        yield DateTimeField::new('scheduledAt', 'newsletter.campaign.field.scheduledAt')->hideOnIndex()
            ->setHelp('newsletter.campaign.field.scheduledAt.help');
        yield IntegerField::new('rateSeconds', 'newsletter.campaign.field.rateSeconds')->hideOnIndex()
            ->setRequired(false)
            ->setHelp('newsletter.campaign.field.rateSeconds.help');

        yield FormField::addFieldset('newsletter.campaign.fieldset.performance')->setIcon('fa fa-chart-simple')->onlyOnDetail();
        yield IntegerField::new('recipientCount', 'newsletter.campaign.field.recipients')->hideOnForm();
        yield IntegerField::new('sentCount', 'newsletter.campaign.field.sent')->hideOnForm();
        yield IntegerField::new('failedCount', 'newsletter.campaign.field.failed')->hideOnForm()->hideOnIndex();
        yield IntegerField::new('unsubCount', 'newsletter.campaign.field.unsubscribed')->hideOnForm()->hideOnIndex();
        yield IntegerField::new('bounceCount', 'newsletter.campaign.field.bounced')->hideOnForm()->hideOnIndex();
        yield TextField::new('sentAt', 'newsletter.campaign.field.sentAt')->onlyOnDetail()->formatValue(
            static fn (mixed $value): string => $value instanceof DateTimeInterface ? $value->format('d M Y H:i') : '—',
        );
    }

    #[AdminRoute(path: '/{entityId}/send', name: 'send')]
    public function send(): RedirectResponse
    {
        $campaign = $this->campaign();

        if (! $campaign instanceof Campaign) {
            return new RedirectResponse($this->indexUrl());
        }

        if (! $campaign->isDraft()) {
            $this->addFlash('warning', 'newsletter.campaign.flash.notDraft');

            return new RedirectResponse($this->indexUrl());
        }

        try {
            $count = $this->campaignSender->arm($campaign);
            $this->addFlash('success', \sprintf('%d recipient(s) queued at 1 mail / %d s.', $count, $campaign->getEffectiveRateSeconds()));
        } catch (Throwable $throwable) {
            $this->addFlash('danger', $throwable->getMessage());
        }

        return new RedirectResponse($this->indexUrl());
    }

    #[AdminRoute(path: '/{entityId}/schedule', name: 'schedule')]
    public function schedule(): RedirectResponse
    {
        $campaign = $this->campaign();

        if (! $campaign instanceof Campaign) {
            return new RedirectResponse($this->indexUrl());
        }

        $when = $campaign->scheduledAt;

        if (! $campaign->isDraft()) {
            $this->addFlash('warning', 'newsletter.campaign.flash.notDraft');
        } elseif (null === $when) {
            $this->addFlash('danger', 'newsletter.campaign.flash.noDate');
        } else {
            $campaign->schedule($when);
            $this->entityManager->flush();
            $this->addFlash('success', \sprintf('Scheduled for %s.', $when->format('d M Y H:i')));
        }

        return new RedirectResponse($this->indexUrl());
    }

    #[AdminRoute(path: '/{entityId}/cancel-schedule', name: 'cancel_schedule')]
    public function cancelSchedule(): RedirectResponse
    {
        $campaign = $this->campaign();

        if ($campaign instanceof Campaign && $campaign->isScheduled()) {
            $campaign->revertToDraft();
            $this->entityManager->flush();
            $this->addFlash('success', 'newsletter.campaign.flash.backToDraft');
        }

        return new RedirectResponse($this->indexUrl());
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
            $count = $this->segmentResolver->count($audience, $campaign->segment);
            $this->addFlash('info', \sprintf('%d subscribed contact(s) match this segment.', $count));
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

        $sent = [];
        $failed = [];

        foreach ($addresses as $address) {
            if (false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                $failed[] = $address;

                continue;
            }

            try {
                $this->mailer->sendTest($audience, $campaign->subject, $campaign->bodyMarkdown, $campaign->preheader, $address, UtmTag::forCampaign($campaign));
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
                ->setNumOfRows(18);
        }

        $campaign = $this->getContext()?->getEntity()->getInstance();
        $host = $campaign instanceof Campaign ? ($campaign->audience->mainHost ?? '') : '';

        // page_id is page-only context (the PagesList block preview URL); a
        // campaign has no page, so it stays empty but the key must exist.
        return Field::new('bodyMarkdown', 'newsletter.campaign.field.body')
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
