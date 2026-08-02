<?php

namespace Pushword\Newsletter\Tests\Admin;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class NewsletterAdminTest extends AbstractAdminTestClass
{
    private ?int $audienceId = null;

    /** @var list<int> */
    private array $extraAudienceIds = [];

    protected function tearDown(): void
    {
        if (null !== $this->client) {
            $connection = $this->entityManager()->getConnection();
            $audienceIds = array_filter(
                [$this->audienceId, ...$this->extraAudienceIds],
                static fn (?int $audienceId): bool => null !== $audienceId,
            );

            foreach ($audienceIds as $audienceId) {
                foreach ([
                    'DELETE FROM newsletter_trigger_log WHERE automation_id IN (SELECT id FROM newsletter_automation WHERE audience_id = :id)',
                    'DELETE FROM newsletter_automation_step WHERE automation_id IN (SELECT id FROM newsletter_automation WHERE audience_id = :id)',
                    'DELETE FROM newsletter_automation WHERE audience_id = :id',
                    'DELETE FROM newsletter_campaign WHERE audience_id = :id',
                    'DELETE FROM newsletter_contact WHERE audience_id = :id',
                    'DELETE FROM newsletter_audience WHERE id = :id',
                ] as $sql) {
                    $connection->executeStatement($sql, ['id' => $audienceId]);
                }
            }
        }

        $this->extraAudienceIds = [];

        parent::tearDown();
    }

    public function testEveryIndexRenders(): void
    {
        $client = $this->loginUser();
        $this->seed();

        foreach (['audience', 'contact', 'campaign', 'automation'] as $section) {
            $client->request(Request::METHOD_GET, '/admin/newsletter/'.$section);
            self::assertSame(200, $client->getResponse()->getStatusCode(), $section.' index');
        }
    }

    public function testTheCampaignFormOffersItsAudienceAndSegment(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $html = (string) $client->getResponse()->getContent();
        self::assertMatchesRegularExpression('/name="[^"]*\[subject\]"/', $html);
        self::assertMatchesRegularExpression('/name="[^"]*\[segmentAsJson\]"/', $html);
        self::assertMatchesRegularExpression('/name="[^"]*\[audience\]"/', $html);
    }

    /** A malformed segment must come back as a form error, never as a 500. */
    public function testAMalformedSegmentIsAFormError(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');
        $form = $crawler->filter('form[name="Campaign"]')->form();
        $form['Campaign[subject]'] = 'Broken segment';
        $form['Campaign[audience]'] = (string) $audience->id;
        $form['Campaign[segmentAsJson]'] = '[{"field":"nope","op":"=","value":"x"}]';
        $client->submit($form);

        self::assertSame(422, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('unknown field', (string) $client->getResponse()->getContent());
    }

    public function testAValidCampaignIsCreatedWithItsSegment(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');
        $form = $crawler->filter('form[name="Campaign"]')->form();
        $form['Campaign[subject]'] = 'Good segment';
        $form['Campaign[audience]'] = (string) $audience->id;
        $form['Campaign[segmentAsJson]'] = '[{"field":"tag","op":"has","value":"AmTrek"}]';
        $client->submit($form);

        $campaign = $this->entityManager()->getRepository(Campaign::class)->findOneBy(['subject' => 'Good segment']);
        self::assertInstanceOf(Campaign::class, $campaign);
        self::assertSame([['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']], $campaign->segment);
    }

    /** Same guarantee for the page grammar, which the source picks: a bad rule is a form error, never a 500. */
    public function testAMalformedPageRuleIsAFormError(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/automation/new');
        $form = $crawler->filter('form[name="Automation"]')->form();
        $form['Automation[name]'] = 'Broken rule';
        $form['Automation[audience]'] = (string) $audience->id;
        $form['Automation[source]'] = 'page';
        // A contact field: `tag` reads the same on both sides, `confirmedAt` belongs to one.
        $form['Automation[triggerWhenAsJson]'] = '[{"field":"confirmedAt","op":"olderThan","value":"7d"}]';
        $client->submit($form);

        self::assertSame(422, $client->getResponse()->getStatusCode());
        // Named, not merely refused: the two vocabularies overlap enough that
        // "unknown field" would leave the editor guessing which side they wrote.
        self::assertStringContainsString('filters a contact, not a page', (string) $client->getResponse()->getContent());
    }

    /** A rule you cannot count is one you will not switch on. */
    public function testTheTriggerPreviewReportsBothSidesOfTheRule(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $automation = new Automation();
        $automation->audience = $audience;
        $automation->name = 'Preview me';
        $automation->source = 'page';
        $automation->hosts = ['localhost.dev'];
        $automation->triggerWhen = [['field' => 'slug', 'op' => 'startsWith', 'value' => 'nothing-matches-this/']];
        $this->entityManager()->persist($automation);
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, '/admin/newsletter/automation/'.$automation->id.'/preview-trigger');
        $client->followRedirect();

        self::assertStringContainsString('0 waiting', (string) $client->getResponse()->getContent());
    }

    /** Contacts are only ever created by an opt-in, never by hand in the admin. */
    public function testContactsCannotBeCreatedByHand(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $client->request(Request::METHOD_GET, '/admin/newsletter/contact/new');

        self::assertNotSame(200, $client->getResponse()->getStatusCode());
    }

    /** The opt-in an admin can defend: it records who opened it, and the address is subscribed at once. */
    public function testAnAdminOptsInAContactByHand(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/opt-in');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('form[method=post]')->form();
        $form['audience'] = (string) $audience->id;
        $form['email'] = 'by-hand@example.tld';
        $form['name'] = 'By Hand';
        $client->submit($form, ['alreadyConsented' => '1']);

        $contact = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'email' => 'by-hand@example.tld']);

        self::assertInstanceOf(Contact::class, $contact);
        self::assertTrue($contact->isSubscribed());
        self::assertStringStartsWith('admin:', (string) $contact->source);
    }

    /** The same address on a second list is a second subscription, not the first one moved. */
    public function testTheSameAddressJoinsASecondListAndBothShowOnTheEditPage(): void
    {
        $client = $this->loginUser();
        $first = $this->seed();
        $second = $this->createAudience('Second list');

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/opt-in');
        $form = $crawler->filter('form[method=post]')->form();
        $form['audience'] = (string) $second->id;
        $form['email'] = 'admin-contact@example.tld';
        $client->submit($form, ['alreadyConsented' => '1']);

        $contacts = $this->entityManager()->getRepository(Contact::class)
            ->findBy(['email' => 'admin-contact@example.tld']);
        self::assertCount(2, $contacts);

        $onFirstList = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $first, 'email' => 'admin-contact@example.tld']);
        $onSecondList = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $second, 'email' => 'admin-contact@example.tld']);
        self::assertInstanceOf(Contact::class, $onFirstList);
        self::assertInstanceOf(Contact::class, $onSecondList);

        // Editing one subscription must show the other, and link to it — the
        // audience select alone would list every audience whether joined or not.
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$onFirstList->id.'/edit');
        $panel = $crawler->filter('.form-fieldset')->reduce(
            static fn (Crawler $node): bool => str_contains($node->text(), 'Subscriptions'),
        );

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertCount(1, $panel);
        self::assertStringContainsString($first->name, $panel->text());
        self::assertCount(1, $panel->filter('a[href*="/admin/newsletter/contact/'.$onSecondList->id.'/edit"]'));
    }

    /**
     * The unticked checkbox is the defensible default: the audience's rule
     * decides, so the address stays pending until it answers the mail.
     */
    public function testTheOptInFollowsTheAudienceDoubleOptInRule(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/opt-in');
        $form = $crawler->filter('form[method=post]')->form();
        $form['audience'] = (string) $audience->id;
        $form['email'] = 'wants-to-confirm@example.tld';
        $client->submit($form);

        $contact = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'email' => 'wants-to-confirm@example.tld']);

        self::assertInstanceOf(Contact::class, $contact);
        self::assertTrue($contact->isPending());
        self::assertEmailCount(1);
    }

    /** Neither a list nor an address is guessed: nothing is written and the form comes back. */
    public function testAMalformedOptInWritesNothing(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/opt-in');
        $form = $crawler->filter('form[method=post]')->form();
        $form['audience'] = (string) $audience->id;
        $form['email'] = 'not-an-address';
        $client->submit($form);
        $client->followRedirect();

        self::assertStringContainsString('/admin/newsletter/contact/opt-in', $client->getRequest()->getUri());
        self::assertCount(0, $this->entityManager()->getRepository(Contact::class)
            ->findBy(['email' => 'not-an-address']));
    }

    /** The opt-in writes to the consent ledger, so a forged post must not reach it. */
    public function testAnOptInWithoutATokenIsRefused(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $client->request(Request::METHOD_POST, '/admin/newsletter/contact/opt-in', [
            'audience' => (string) $audience->id,
            'email' => 'forged@example.tld',
            'alreadyConsented' => '1',
        ]);

        self::assertCount(0, $this->entityManager()->getRepository(Contact::class)
            ->findBy(['email' => 'forged@example.tld']));
    }

    /** Leaving and coming back, both from the admin and both through ContactManager. */
    public function testAContactIsUnsubscribedThenPutBackFromTheAdmin(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $contact = new Contact($audience, 'leaving@example.tld');
        $contact->optIn(false);
        $this->entityManager()->persist($contact);
        $this->entityManager()->flush();
        $contactId = $contact->id;

        $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$contactId.'/unsubscribe');
        $client->followRedirect();
        self::assertSame('unsubscribed', $this->reloadContact($contactId)->getStatusLabel());

        $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$contactId.'/resubscribe');
        $client->followRedirect();
        self::assertSame('subscribed', $this->reloadContact($contactId)->getStatusLabel());
    }

    /** The detail page carries the same panel — a mistyped template override renders nothing and still returns 200. */
    public function testTheDetailPageListsTheSubscriptionsToo(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $contact = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'email' => 'admin-contact@example.tld']);
        self::assertInstanceOf(Contact::class, $contact);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$contact->id);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('.form-fieldset')->reduce(
            static fn (Crawler $node): bool => str_contains($node->text(), 'Subscriptions'),
        ));
    }

    /** A pending opt-in an admin can vouch for becomes a subscription without the click. */
    public function testAPendingContactIsConfirmedByHand(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $contact = new Contact($audience, 'pending@example.tld');
        $contact->optIn(true);
        $this->entityManager()->persist($contact);
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$contact->id.'/confirm');
        $client->followRedirect();

        $confirmed = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'email' => 'pending@example.tld']);

        self::assertInstanceOf(Contact::class, $confirmed);
        self::assertTrue($confirmed->isSubscribed());
    }

    private function reloadContact(?int $contactId): Contact
    {
        $contact = $this->entityManager()->getRepository(Contact::class)->find($contactId);
        self::assertInstanceOf(Contact::class, $contact);

        return $contact;
    }

    private function createAudience(string $name): Audience
    {
        $audience = new Audience();
        $audience->slug = 'admin-'.bin2hex(random_bytes(5));
        $audience->name = $name;
        $audience->mainHost = 'localhost.dev';
        $audience->fromEmail = 'newsletter@localhost.dev';

        $entityManager = $this->entityManager();
        $entityManager->persist($audience);
        $entityManager->flush();

        $audienceId = $audience->id;
        self::assertIsInt($audienceId);
        $this->extraAudienceIds[] = $audienceId;

        return $audience;
    }

    private function seed(): Audience
    {
        $entityManager = $this->entityManager();

        if (null !== $this->audienceId) {
            $audience = $entityManager->getRepository(Audience::class)->find($this->audienceId);
            self::assertInstanceOf(Audience::class, $audience);

            return $audience;
        }

        $audience = new Audience();
        $audience->slug = 'admin-'.bin2hex(random_bytes(5));
        $audience->name = 'Admin test';
        $audience->mainHost = 'localhost.dev';
        $audience->fromEmail = 'newsletter@localhost.dev';
        $audience->interests = ['AmTrek'];

        $entityManager->persist($audience);

        $contact = new Contact($audience, 'admin-contact@example.tld');
        $contact->setTags(['AmTrek'])->optIn(false);
        $entityManager->persist($contact);
        $entityManager->flush();

        $this->audienceId = $audience->id;

        return $audience;
    }

    private function entityManager(): EntityManagerInterface
    {
        $client = $this->client;
        self::assertInstanceOf(KernelBrowser::class, $client);

        return $client->getContainer()->get('doctrine.orm.default_entity_manager');
    }
}
