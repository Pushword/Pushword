<?php

namespace Pushword\Newsletter\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Newsletter\Click\ClickPayload;
use Pushword\Newsletter\Click\ClickTracker;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\AutomationRepository;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Repository\ContactRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The redirect a tracked link goes through: record the click, then send the
 * reader where the mail said.
 *
 * The payload is only honoured when its HMAC recomputes — it names the redirect
 * target, so without the signature this endpoint would be an open redirect. A
 * payload that does not prove itself is a 404, never a redirect.
 *
 * A valid payload redirects even when nothing gets recorded: the link sits in a
 * mailbox for years, and neither a deleted contact, a withdrawn consent nor a
 * switched-off audience is a reason to break it. What they all stop is the
 * recording.
 */
final class ClickController extends AbstractController
{
    public function __construct(
        private readonly ClickTracker $clickTracker,
        private readonly ContactRepository $contactRepository,
        private readonly CampaignRepository $campaignRepository,
        private readonly AutomationRepository $automationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(
        path: '/newsletter/c/{payload}',
        name: 'pushword_newsletter_click',
        requirements: ['payload' => '[A-Za-z0-9_\-]+\.[0-9a-f]+'],
        methods: ['GET'],
    )]
    public function click(string $payload): RedirectResponse
    {
        $decoded = $this->clickTracker->decode($payload);

        if (! $decoded instanceof ClickPayload) {
            throw $this->createNotFoundException();
        }

        $this->record($decoded);

        return new RedirectResponse($decoded->url);
    }

    /**
     * Both consent gates are asked again here, not only at send time: a consent
     * withdrawn after the mail went out still covers the links it already
     * carries, so those links must keep working and stop writing.
     */
    private function record(ClickPayload $clickPayload): void
    {
        $contact = $this->contactRepository->find($clickPayload->contactId);

        if (! $contact instanceof Contact || ! $contact->audience->clickTracking || ! $contact->hasClickTrackingConsent()) {
            return;
        }

        if (null !== $clickPayload->campaignId) {
            $campaign = $this->campaignRepository->find($clickPayload->campaignId);

            // Deleted since the send: nothing left to report the click under.
            if (! $campaign instanceof Campaign) {
                return;
            }

            $this->entityManager->persist(ClickEvent::onCampaign($contact, $campaign, $clickPayload->url));
            $campaign->incrementClick();
            $this->entityManager->flush();

            return;
        }

        $automation = null !== $clickPayload->automationId ? $this->automationRepository->find($clickPayload->automationId) : null;

        if (! $automation instanceof Automation || null === $clickPayload->position) {
            return;
        }

        $this->entityManager->persist(ClickEvent::onStep($contact, $automation, $clickPayload->position, $clickPayload->url));
        $this->entityManager->flush();
    }
}
