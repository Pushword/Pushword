<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use InvalidArgumentException;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Delivery\SendContext;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Service\EmailTemplateRegistry;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Service\SenderProfileRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_EDITOR')]
final class EmailPreviewController extends AbstractController
{
    public function __construct(
        private readonly SiteRegistry $sites,
        private readonly MailRenderer $renderer,
        private readonly EmailTemplateRegistry $templates,
        private readonly TranslatorInterface $translator,
        private readonly SenderProfileRegistry $senderProfiles,
    ) {
    }

    #[Route('/admin/newsletter/campaign-preview', name: 'admin_newsletter_campaign_preview', methods: ['POST'])]
    public function campaign(Request $request, AudienceRepository $audiences): JsonResponse
    {
        $this->assertToken($request);
        $data = new InputBag(array_filter($request->request->all('Campaign'), \is_string(...), \ARRAY_FILTER_USE_KEY));
        if ('' === $data->getString('audience')) {
            return $this->error('newsletter.campaign.design.empty');
        }

        $audience = $audiences->find($data->getInt('audience'));
        if (! $audience instanceof Audience) {
            return $this->error('newsletter.campaign.design.empty');
        }

        $layout = $data->getString('emailLayout');
        $allowed = ['simple', 'framed', 'personal', ''];
        foreach ($this->templates->choices() as $choices) {
            array_push($allowed, ...array_values($choices));
        }

        if (! \in_array($layout, $allowed, true)) {
            return $this->error('newsletter.design.failed');
        }

        $design = clone $audience;
        if ('' !== $layout) {
            $design->emailLayout = $layout;
        }

        $design->transactional = $design->transactional || $data->getBoolean('transactional');

        $campaign = new Campaign();
        $campaign->audience = $design;
        $campaign->senderProfile = $data->getString('senderProfile');

        try {
            $context = $this->senderProfiles->resolve($campaign);
        } catch (InvalidArgumentException) {
            return $this->error('newsletter.preflight.senderInvalid');
        }

        return $this->preview($design, $data->getString('subject'), $data->getString('bodyMarkdown'), $data->getString('preheader'), $context);
    }

    #[Route('/admin/newsletter/template-preview', name: 'admin_newsletter_template_preview', methods: ['POST'])]
    public function template(Request $request): JsonResponse
    {
        $this->assertToken($request);
        $data = new InputBag(array_filter($request->request->all('EmailTemplate'), \is_string(...), \ARRAY_FILTER_USE_KEY));
        $audience = new Audience();
        $audience->mainHost = $this->sites->getDefault()->getMainHost();
        $audience->name = $this->translator->trans('newsletter.template.sampleAudience');
        $audience->headerMarkdown = $data->getString('headerMarkdown');
        $audience->footerMarkdown = $data->getString('footerMarkdown');
        $audience->emailWrapped = $data->getBoolean('wrapped');

        return $this->preview($audience, $data->getString('name'), $this->translator->trans('newsletter.design.sampleBody'), null);
    }

    #[Route('/admin/newsletter/automation-preview', name: 'admin_newsletter_automation_preview', methods: ['POST'])]
    public function automation(Request $request, AudienceRepository $audiences): JsonResponse
    {
        $this->assertToken($request);
        $data = new InputBag(array_filter($request->request->all('Automation'), \is_string(...), \ARRAY_FILTER_USE_KEY));
        if ('' === $data->getString('audience')) {
            return $this->error('newsletter.campaign.design.empty');
        }

        $audience = $audiences->find($data->getInt('audience'));
        if (! $audience instanceof Audience) {
            return $this->error('newsletter.campaign.design.empty');
        }

        $steps = $data->all('steps');
        $index = $request->request->getInt('_preview_step', -1);
        if (! isset($steps[$index]) || ! \is_array($steps[$index])) {
            return $this->error('newsletter.design.failed');
        }

        $step = new InputBag(array_filter($steps[$index], \is_string(...), \ARRAY_FILTER_USE_KEY));
        $design = clone $audience;
        $design->transactional = $design->transactional || $data->getBoolean('transactional');

        return $this->preview($design, $step->getString('subject'), $step->getString('bodyMarkdown'), null);
    }

    #[AdminRoute(path: '/newsletter/template/site/{name}', name: 'newsletter_site_template_preview', options: ['methods' => ['GET']])]
    public function siteTemplate(string $name): Response
    {
        $label = array_search('twig:'.$name, $this->templates->siteChoices(), true);
        if (false === $label) {
            throw $this->createNotFoundException();
        }

        $audience = new Audience();
        $audience->mainHost = $this->sites->getDefault()->getMainHost();
        $audience->emailLayout = 'twig:'.$name;

        return $this->render('@PushwordNewsletter/admin/site_template.html.twig', [
            'newsletterSection' => 'emailtemplate',
            'template_label' => $label,
            'preview_html' => $this->renderPreview($audience, $label, $this->translator->trans('newsletter.design.sampleBody'), null),
        ]);
    }

    #[Route('/admin/newsletter/template-choices', name: 'admin_newsletter_template_choices', methods: ['GET'])]
    public function choices(AudienceRepository $audiences): JsonResponse
    {
        $groups = [];
        foreach ($this->templates->choices() as $label => $choices) {
            $groups[$this->translator->trans($label)] = $choices;
        }

        return $this->jsonPrivate([
            'groups' => $groups,
            'audienceLayouts' => array_column($audiences->findAll(), 'emailLayout', 'id'),
        ]);
    }

    private function assertToken(Request $request): void
    {
        if (! $this->isCsrfTokenValid('newsletter_email_preview', $request->request->getString('_preview_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function preview(Audience $audience, string $subject, string $body, ?string $preheader, ?SendContext $context = null): JsonResponse
    {
        return $this->jsonPrivate(['html' => $this->renderPreview($audience, $subject, $body, $preheader, $context), 'sender' => ($context->fromName ?? $audience->fromName).' <'.($context->fromEmail ?? $audience->fromEmail).'>']);
    }

    private function renderPreview(Audience $audience, string $subject, string $body, ?string $preheader, ?SendContext $context = null): string
    {
        $contact = new Contact($audience, 'preview@example.tld');
        $contact->name = 'Alex';
        $contact->customProperties = ['firstName' => 'Alex'];

        $site = $this->sites->get($audience->mainHost);

        return $this->renderer->html(
            $audience,
            $contact,
            $subject,
            $body,
            $preheader,
            $audience->transactional ? null : $site->baseUrl.'/#unsubscribe',
            null,
            sendContext: $context,
            locale: $site->locale,
        );
    }

    private function error(string $key): JsonResponse
    {
        return $this->jsonPrivate(['error' => $this->translator->trans($key)], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @param array<string, mixed> $data */
    private function jsonPrivate(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
