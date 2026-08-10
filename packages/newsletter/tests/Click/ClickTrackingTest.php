<?php

namespace Pushword\Newsletter\Tests\Click;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Service\AutomationRunner;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Service\ContactManager;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The click-tracking pipeline end to end: links only rewritten behind both
 * consents, the recording redirect, and the 404 a forged payload earns.
 */
#[Group('integration')]
final class ClickTrackingTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    public function testLinksAreRewrittenBehindBothConsentsAndTheTargetStaysTagged(): void
    {
        $contact = $this->sendTrackedCampaign();

        $html = $this->lastHtml();
        self::assertStringContainsString('https://localhost.dev/newsletter/c/', $html);
        self::assertStringNotContainsString('href="https://localhost.dev/article', $html);

        // The UTM pass ran before the rewrite: following the redirect still
        // lands on the tagged URL.
        $this->client->request(Request::METHOD_GET, $this->trackedPath($html));

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame(
            'https://localhost.dev/article?utm_source=newsletter&utm_medium=email&utm_campaign='.date('ymd').'-janvier',
            $response->headers->get('Location'),
        );

        $clicks = $this->clicksOf($contact);
        self::assertCount(1, $clicks);
        self::assertSame('https://localhost.dev/article?utm_source=newsletter&utm_medium=email&utm_campaign='.date('ymd').'-janvier', $clicks[0]->url);
        self::assertInstanceOf(Campaign::class, $clicks[0]->campaign);
        self::assertSame(1, $clicks[0]->campaign->clickCount);
    }

    public function testAContactWithoutConsentKeepsPlainLinks(): void
    {
        $this->sendTrackedCampaign(contactConsented: false);

        $html = $this->lastHtml();
        self::assertStringNotContainsString('/newsletter/c/', $html);
        // The other pipeline is untouched: UTM alone, exactly as before.
        self::assertStringContainsString('href="https://localhost.dev/article?utm_source=newsletter', $html);
    }

    public function testASwitchedOffAudienceKeepsPlainLinksWhateverTheContactConsented(): void
    {
        $this->sendTrackedCampaign(audienceTracks: false);

        $html = $this->lastHtml();
        self::assertStringNotContainsString('/newsletter/c/', $html);
        self::assertStringContainsString('href="https://localhost.dev/article?utm_source=newsletter', $html);
    }

    public function testATamperedPayloadIsNotFoundNeverARedirect(): void
    {
        $contact = $this->sendTrackedCampaign();

        $path = $this->trackedPath($this->lastHtml());
        $forged = substr($path, 0, -8).str_repeat('0', 8);

        $this->client->request(Request::METHOD_GET, $forged);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertNull($response->headers->get('Location'));
        self::assertCount(0, $this->clicksOf($contact));
    }

    public function testADripStepIsTrackedWithItsAutomationAndPosition(): void
    {
        $audience = $this->createAudience();
        $audience->clickTracking = true;

        $contact = $this->createConsentingContact($audience);

        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);
        $automation->getOrderedSteps()[0]->bodyMarkdown = '[Read](/article)';
        $this->entityManager->flush();

        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->triggerOne($automation, new DateTimeImmutable());
        $runner->advance(10);

        $this->client->request(Request::METHOD_GET, $this->trackedPath($this->lastHtml()));
        self::assertSame(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode());

        $clicks = $this->clicksOf($contact);
        self::assertCount(1, $clicks);
        self::assertNull($clicks[0]->campaign);
        self::assertSame($automation->id, $clicks[0]->automation?->id);
        self::assertSame(0, $clicks[0]->position);
    }

    public function testTheUnsubscribeLinkIsNeverRewritten(): void
    {
        $contact = $this->sendTrackedCampaign();

        self::assertStringContainsString('/newsletter/unsubscribe/'.$contact->token, $this->lastHtml());
    }

    /** No page visit stands behind a mailto: or an anchor, so there is nothing to record. */
    public function testOnlyHttpLinksAreRewritten(): void
    {
        $this->sendTrackedCampaign(body: '[Write](mailto:hi@example.tld) then [jump](#section) then [read](/article)');

        $html = $this->lastHtml();
        self::assertStringContainsString('href="mailto:hi@example.tld"', $html);
        self::assertStringContainsString('href="#section"', $html);
        self::assertStringContainsString('/newsletter/c/', $html);
    }

    /** The reader keeps their link after the campaign is gone; only the reporting is gone. */
    public function testAClickOnADeletedCampaignStillRedirectsAndRecordsNothing(): void
    {
        $contact = $this->sendTrackedCampaign();
        $path = $this->trackedPath($this->lastHtml());

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement(
            'DELETE FROM newsletter_campaign_recipient WHERE campaign_id IN (SELECT id FROM newsletter_campaign WHERE audience_id = :id)',
            ['id' => $contact->audience->id],
        );
        $connection->executeStatement('DELETE FROM newsletter_campaign WHERE audience_id = :id', ['id' => $contact->audience->id]);

        $this->entityManager->clear();

        $this->client->request(Request::METHOD_GET, $path);

        self::assertSame(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $this->entityManager->getRepository(ClickEvent::class)->findAll());
    }

    /** Links already in an inbox keep redirecting after a withdrawal — they just record nothing. */
    public function testAWithdrawnConsentStopsTheRecordingNotTheRedirect(): void
    {
        $contact = $this->sendTrackedCampaign();

        $contact->clickTrackingConsentAt = null;

        $this->entityManager->flush();

        $this->client->request(Request::METHOD_GET, $this->trackedPath($this->lastHtml()));

        self::assertSame(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $this->clicksOf($contact));
    }

    /** The favoured pattern: confirm-and-accept leads, plain confirm sits under it. */
    public function testTheConfirmationMailPutsTheTrackedOptInFirst(): void
    {
        $contact = $this->subscribeToTrackingAudience();

        $html = $this->lastHtml();
        $trackingPosition = strpos($html, $contact->token.'/tracking');
        $plainPosition = strpos($html, $contact->token.'"');

        self::assertIsInt($trackingPosition);
        self::assertIsInt($plainPosition);
        self::assertLessThan($plainPosition, $trackingPosition, 'the consenting link is the one put forward');
    }

    public function testANonTrackingAudienceKeepsItsSingleConfirmButton(): void
    {
        $contact = $this->subscribeToTrackingAudience(audienceTracks: false);

        $html = $this->lastHtml();
        self::assertStringContainsString('/newsletter/confirm/'.$contact->token, $html);
        self::assertStringNotContainsString('/tracking', $html);
    }

    /** The purpose line is what makes the bare confirm button an informed yes — both parts of the mail carry it. */
    public function testTheConfirmationMailStatesTheTrackingPurposeInBothParts(): void
    {
        $hint = $this->trackingHintOf($this->subscribeToTrackingAudience());

        $email = $this->lastEmail();
        self::assertStringContainsString($hint, html_entity_decode((string) $email->getHtmlBody(), \ENT_QUOTES | \ENT_HTML5));
        self::assertStringContainsString($hint, (string) $email->getTextBody());
    }

    public function testANonTrackingConfirmationMailCarriesNoTrackingHint(): void
    {
        $hint = $this->trackingHintOf($this->subscribeToTrackingAudience(audienceTracks: false));

        $email = $this->lastEmail();
        self::assertStringNotContainsString($hint, html_entity_decode((string) $email->getHtmlBody(), \ENT_QUOTES | \ENT_HTML5));
        self::assertStringNotContainsString($hint, (string) $email->getTextBody());
    }

    /** The hint as this contact reads it, whatever locale the subscription resolved to. */
    private function trackingHintOf(Contact $contact): string
    {
        return self::getContainer()->get(TranslatorInterface::class)
            ->trans('newsletter.confirm.trackingHint', [], null, $contact->locale);
    }

    public function testConfirmingThroughTheTrackingLinkGrantsTheDatedConsent(): void
    {
        $contact = $this->subscribeToTrackingAudience();

        $this->client->request(
            Request::METHOD_GET,
            '/newsletter/confirm/'.$contact->token.'/tracking',
            server: ['HTTP_SEC_FETCH_USER' => '?1'],
        );

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $confirmed = $this->freshContact($contact->id);
        self::assertTrue($confirmed->isSubscribed());
        self::assertNotNull($confirmed->clickTrackingConsentAt);
    }

    public function testThePlainConfirmLinkGrantsNoConsent(): void
    {
        $contact = $this->subscribeToTrackingAudience();

        $this->client->request(
            Request::METHOD_GET,
            '/newsletter/confirm/'.$contact->token,
            server: ['HTTP_SEC_FETCH_USER' => '?1'],
        );

        $confirmed = $this->freshContact($contact->id);
        self::assertTrue($confirmed->isSubscribed());
        self::assertNull($confirmed->clickTrackingConsentAt);
    }

    /**
     * Clicking the consenting link later — after a plain confirm, say — still
     * expresses the consent; and a second click keeps the date of the first,
     * because the first consent is the one that dates it.
     */
    public function testALaterTrackingClickGrantsConsentOnceAndKeepsItsFirstDate(): void
    {
        $contact = $this->subscribeToTrackingAudience();
        $contactId = $contact->id;
        self::assertIsInt($contactId);
        $token = $contact->token;

        // Reloaded from the database after each click: the services resetter
        // clears the identity map between two client requests, so the test's
        // own instance stops being the one the controller writes to.
        $this->client->request(Request::METHOD_GET, '/newsletter/confirm/'.$token, server: ['HTTP_SEC_FETCH_USER' => '?1']);
        self::assertNull($this->freshConsent($contactId));

        $this->client->request(Request::METHOD_GET, '/newsletter/confirm/'.$token.'/tracking', server: ['HTTP_SEC_FETCH_USER' => '?1']);
        $granted = $this->freshConsent($contactId);
        self::assertNotNull($granted);

        $this->client->request(Request::METHOD_GET, '/newsletter/confirm/'.$token.'/tracking', server: ['HTTP_SEC_FETCH_USER' => '?1']);
        self::assertEquals($granted, $this->freshConsent($contactId));
    }

    /** @phpstan-impure each call re-reads the database, so two calls may well disagree */
    private function freshConsent(int $contactId): ?DateTimeImmutable
    {
        return $this->freshContact($contactId)->clickTrackingConsentAt;
    }

    /** The row as the database holds it — never the instance a previous request may have detached. */
    private function freshContact(?int $contactId): Contact
    {
        self::assertIsInt($contactId);
        $this->entityManager->clear();
        $contact = $this->entityManager->find(Contact::class, $contactId);
        self::assertInstanceOf(Contact::class, $contact);

        return $contact;
    }

    /** A scanner fetching every link of the mail must not consent on the reader's behalf. */
    public function testAFetchedTrackingLinkConfirmsTheSubscriptionButConsentsToNothing(): void
    {
        $contact = $this->subscribeToTrackingAudience();

        $this->client->request(Request::METHOD_GET, '/newsletter/confirm/'.$contact->token.'/tracking');

        $confirmed = $this->freshContact($contact->id);
        self::assertTrue($confirmed->isSubscribed(), "the subscription half keeps the plain link's behaviour");
        self::assertNull($confirmed->clickTrackingConsentAt);
    }

    /** A pending subscription to a click-tracking audience, its confirmation mail just sent. */
    private function subscribeToTrackingAudience(bool $audienceTracks = true): Contact
    {
        $audience = $this->createAudience();
        $audience->clickTracking = $audienceTracks;

        $this->entityManager->flush();

        return self::getContainer()->get(ContactManager::class)->subscribe($audience, 'optin@example.tld');
    }

    /** The mail whose links this campaign's tests read: one contact, one body link. */
    private function sendTrackedCampaign(bool $audienceTracks = true, bool $contactConsented = true, string $body = '[Read](/article)'): Contact
    {
        $audience = $this->createAudience();
        $audience->clickTracking = $audienceTracks;
        $audience->utmSource = 'newsletter';

        $contact = $contactConsented
            ? $this->createConsentingContact($audience)
            : $this->createContact($audience, 'reader@example.tld');

        $campaign = $this->createCampaign($audience, subject: 'Janvier');
        $campaign->bodyMarkdown = $body;

        $this->entityManager->flush();

        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);
        $sender->drain($campaign, 10);

        return $contact;
    }

    private function createConsentingContact(Audience $audience): Contact
    {
        $contact = $this->createContact($audience, 'reader@example.tld');
        $contact->clickTrackingConsentAt = new DateTimeImmutable();

        $this->entityManager->flush();

        return $contact;
    }

    /** The tracked link's path, read out of the mail the way a mail client would follow it. */
    private function trackedPath(string $html): string
    {
        self::assertSame(1, preg_match('#href="https://localhost\.dev(/newsletter/c/[^"]+)"#', $html, $matches));

        return html_entity_decode($matches[1], \ENT_QUOTES | \ENT_HTML5);
    }

    /** @return list<ClickEvent> */
    private function clicksOf(Contact $contact): array
    {
        return $this->entityManager->getRepository(ClickEvent::class)->findBy(['contact' => $contact]);
    }

    private function lastEmail(): Email
    {
        $messages = self::getMailerMessages();
        $email = end($messages);
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    private function lastHtml(): string
    {
        return (string) $this->lastEmail()->getHtmlBody();
    }
}
