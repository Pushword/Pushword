<?php

namespace Pushword\Newsletter\Tests\Admin;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Service\CampaignSender;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class NewsletterAdminTest extends AbstractAdminTestClass
{
    private ?int $audienceId = null;

    /** @var list<int> */
    private array $extraAudienceIds = [];

    /** @var list<int|null> child first: the parent cannot go while it is one */
    private array $sectionPageIds = [];

    protected function tearDown(): void
    {
        if (null !== $this->client) {
            $connection = $this->entityManager()->getConnection();

            $pageIds = array_filter($this->sectionPageIds, static fn (?int $pageId): bool => null !== $pageId);

            foreach ($pageIds as $pageId) {
                $connection->executeStatement('DELETE FROM page WHERE id = :id', ['id' => $pageId]);
            }

            $audienceIds = array_filter(
                [$this->audienceId, ...$this->extraAudienceIds],
                static fn (?int $audienceId): bool => null !== $audienceId,
            );

            foreach ($audienceIds as $audienceId) {
                foreach ([
                    // SQLite does not enforce the `ON DELETE CASCADE` the schema
                    // declares, so the ledger is cleared by hand and first.
                    'DELETE FROM newsletter_campaign_recipient WHERE campaign_id IN (SELECT id FROM newsletter_campaign WHERE audience_id = :id)',
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
        $this->sectionPageIds = [];

        parent::tearDown();
    }

    public function testEveryIndexRenders(): void
    {
        $client = $this->loginUser();
        $this->seed();

        foreach (['audience', 'contact', 'campaign', 'campaign-recipient', 'automation', 'automation-delivery'] as $section) {
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

    /** The counters say how many; the ledger says which, and why a mail did not leave. */
    public function testTheLedgerListsWhoTheNewsletterWentTo(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $campaign = $this->campaign($audience, 'Ledger');
        $this->recipient($campaign, 'sent@example.tld')->markSent();
        $this->recipient($campaign, 'failed@example.tld')->markFailed('Mailbox unavailable');
        $campaign->markSending(2);
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, $this->ledgerUrl($client, $campaign));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('sent@example.tld', $html);
        self::assertStringContainsString('failed@example.tld', $html);
        // Why it failed is only ever legible here: `failedCount` sums the rows
        // and loses the transport's answer.
        self::assertStringContainsString('Mailbox unavailable', $html);
    }

    /** One campaign's rows, not every campaign's — the link carries the filter. */
    public function testTheLedgerLinkNarrowsToOneCampaign(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $campaign = $this->campaign($audience, 'Mine');
        $this->recipient($campaign, 'mine@example.tld')->markSent();
        $campaign->markSending(1);

        $other = $this->campaign($audience, 'Someone else');
        $this->recipient($other, 'theirs@example.tld')->markSent();
        $other->markSending(1);
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, $this->ledgerUrl($client, $campaign));

        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('mine@example.tld', $html);
        self::assertStringNotContainsString('theirs@example.tld', $html);
    }

    /**
     * "Which ones failed" is the question the ledger exists to answer, and the
     * state filter is the only thing that answers it — a filter whose choices go
     * missing throws at render time rather than degrading.
     */
    public function testTheLedgerNarrowsToOneState(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $campaign = $this->campaign($audience, 'Mixed states');
        $this->recipient($campaign, 'delivered@example.tld')->markSent();
        $this->recipient($campaign, 'refused@example.tld')->markFailed('550 user unknown');
        $campaign->markSending(2);
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, $this->ledgerUrl($client, $campaign).'&'.http_build_query([
            'filters' => ['state' => ['comparison' => '=', 'value' => 'failed']],
        ]));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('refused@example.tld', $html);
        self::assertStringNotContainsString('delivered@example.tld', $html);
    }

    /** Searching the ledger reads through to the contact, which is a join and not a column. */
    public function testTheLedgerIsSearchedByAddress(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $campaign = $this->campaign($audience, 'Searchable');
        $this->recipient($campaign, 'needle@example.tld')->markSent();
        $this->recipient($campaign, 'haystack@example.tld')->markSent();
        $campaign->markSending(2);
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, $this->ledgerUrl($client, $campaign).'&'.http_build_query(['query' => 'needle@example.tld']));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('needle@example.tld', $html);
        self::assertStringNotContainsString('haystack@example.tld', $html);
    }

    /** A campaign nobody has been armed for has no rows to open. */
    public function testADraftCampaignOffersNoLedger(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $campaign = $this->campaign($audience, 'Still a draft');
        $this->entityManager()->flush();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/'.$campaign->id);

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertCount(0, $crawler->filter('a[href*="campaign-recipient"]'));
    }

    /** A row records a send that happened: editing it would make the ledger lie. */
    public function testTheLedgerIsReadOnly(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $campaign = $this->campaign($audience, 'Read only');
        $recipient = $this->recipient($campaign, 'row@example.tld');
        $recipient->markSent();

        $campaign->markSending(1);
        $this->entityManager()->flush();

        foreach ([
            [Request::METHOD_GET, '/admin/newsletter/campaign-recipient/new'],
            [Request::METHOD_GET, '/admin/newsletter/campaign-recipient/'.$recipient->id.'/edit'],
            [Request::METHOD_POST, '/admin/newsletter/campaign-recipient/'.$recipient->id.'/delete'],
        ] as [$method, $url]) {
            $client->request($method, $url);
            self::assertSame(403, $client->getResponse()->getStatusCode(), $method.' '.$url);
        }
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

    /**
     * The builder grows over the textarea, so what the form has to carry is the
     * side the rule is written on and where to ask about it. A wrongly-named
     * attribute renders and does nothing.
     */
    public function testTheAutomationFormWiresEveryRuleToTheBuilder(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/automation/new');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('textarea[name="Automation[triggerWhenAsJson]"][data-pw-criteria="trigger"]'));
        self::assertCount(2, $crawler->filter('textarea[data-pw-criteria="contact"]'));
        self::assertCount(3, $crawler->filter('textarea[data-pw-criteria-vocabulary][data-pw-criteria-preview]'));
    }

    /** The editor offers what the picked source speaks, and nothing else. */
    public function testTheVocabularyFollowsTheSource(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $page = $this->vocabulary($client, 'trigger', 'page');
        $pageFields = $this->subArray($page, 'fields');
        self::assertArrayHasKey('template', $pageFields);
        self::assertArrayHasKey('prop.', $pageFields);
        self::assertArrayNotHasKey('confirmedAt', $pageFields);
        // A page rule may be written as a pages_list search, and the editor has
        // to say so rather than let it look like malformed JSON.
        self::assertTrue($page['acceptsSearch']);

        $contact = $this->vocabulary($client, 'contact', '');
        $contactFields = $this->subArray($contact, 'fields');
        self::assertArrayHasKey('confirmedAt', $contactFields);
        self::assertArrayNotHasKey('template', $contactFields);
        self::assertFalse($contact['acceptsSearch']);
    }

    /**
     * An amount and a unit, not "7d" from memory; and no value box at all for
     * the operators that carry none.
     */
    public function testTheVocabularyMarksWhatAnOperatorTakes(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $fields = $this->subArray($this->vocabulary($client, 'contact', ''), 'fields');

        self::assertContains(
            ['name' => 'olderThan', 'valueless' => false, 'duration' => true],
            $this->subArray($this->subArray($fields, 'createdAt'), 'operators'),
        );
        self::assertContains(
            ['name' => 'isSet', 'valueless' => true, 'duration' => false],
            $this->subArray($this->subArray($fields, 'prop.'), 'operators'),
        );
    }

    /**
     * A rule is picked from what the site holds rather than remembered — the
     * tags carried, the slugs that already name a section.
     */
    public function testTheVocabularyOffersWhatTheSiteAlreadyHas(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $contactFields = $this->subArray($this->vocabulary($client, 'contact', ''), 'fields');
        self::assertContains('AmTrek', $this->subArray($this->subArray($contactFields, 'tag'), 'suggestions'));

        // `parent` and `ancestor` take a slug that has pages under it, which is
        // the only set of slugs short enough to offer. The section is created
        // here rather than read off the fixtures: what the fixture tree looks
        // like by the time this runs is up to every other test in the worker,
        // and one of them reparents the demo pages.
        $section = $this->seedSection();

        $pageFields = $this->subArray($this->vocabulary($client, 'trigger', 'page'), 'fields');
        self::assertContains($section, $this->subArray($this->subArray($pageFields, 'ancestor'), 'suggestions'));
    }

    public function testAnUnknownSourceHasNoVocabulary(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $client->request(Request::METHOD_GET, '/admin/newsletter/criteria/vocabulary?side=trigger&source=nope');

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('No trigger source is named', (string) $client->getResponse()->getContent());
    }

    /** Neither side named means no language to read the rule in, and no guess. */
    public function testAnUnknownSideHasNoVocabulary(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $client->request(Request::METHOD_GET, '/admin/newsletter/criteria/vocabulary?side=whatever');

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Unknown criteria side', (string) $client->getResponse()->getContent());
    }

    /** Counting a rule is asking a question, so it must not answer by storing it. */
    public function testThePreviewCountsAnUnsavedRuleWithoutStoringIt(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $automation = new Automation();
        $automation->audience = $audience;
        $automation->name = 'Count me';
        $automation->source = 'page';
        $automation->triggerWhen = [['field' => 'slug', 'op' => 'startsWith', 'value' => 'stored/']];
        $this->entityManager()->persist($automation);
        $this->entityManager()->flush();
        $automationId = $automation->id;

        $preview = $this->preview($client, [
            'side' => 'trigger',
            'source' => 'page',
            'automation' => $automationId,
            'hosts' => ['localhost.dev'],
            'rule' => '[{"field":"slug","op":"startsWith","value":"previewed-only/"}]',
            'sinceAll' => true,
        ]);

        self::assertIsInt($preview['count']);

        $stored = $this->entityManager()->getRepository(Automation::class)->find($automationId);
        self::assertInstanceOf(Automation::class, $stored);
        self::assertSame([['field' => 'slug', 'op' => 'startsWith', 'value' => 'stored/']], $stored->triggerWhen);
    }

    /**
     * A fresh automation counts zero by construction — its start date is its
     * creation, and nothing has happened since. Lifting it is what answers
     * "would this rule ever catch anything" while the rule is being written.
     */
    public function testTheStartDateGuardCanBeLiftedForTheCount(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $automation = new Automation();
        $automation->audience = $audience;
        $automation->name = 'Since me';
        $automation->source = 'page';
        $this->entityManager()->persist($automation);
        $this->entityManager()->flush();

        $payload = [
            'side' => 'trigger',
            'source' => 'page',
            'automation' => $automation->id,
            'rule' => '[{"field":"slug","op":"startsWith","value":"kitchen"}]',
        ];

        self::assertSame(0, $this->preview($client, $payload)['count'], 'nothing has been published since it was created');
        self::assertGreaterThan(0, $this->preview($client, [...$payload, 'sinceAll' => true])['count']);
    }

    /** The other side of the rule: who a broadcast would reach, as it is typed. */
    public function testThePreviewCountsTheContactsASegmentReaches(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $preview = $this->preview($client, [
            'side' => 'contact',
            'audience' => $audience->id,
            'rule' => '[{"field":"tag","op":"has","value":"AmTrek"}]',
        ]);

        self::assertSame(1, $preview['count']);
        self::assertSame(['admin-contact@example.tld'], $preview['samples']);
    }

    /** A trigger rule cannot be counted before there is an automation to scope it. */
    public function testThePreviewSaysWhenThereIsNothingToCountAgainstYet(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $preview = $this->preview($client, ['side' => 'trigger', 'source' => 'page', 'rule' => '']);

        self::assertNull($preview['count']);
        self::assertTrue($preview['saveFirst']);

        // The same on the contact side, where a count is over one audience and
        // no audience is picked yet.
        $noAudience = $this->preview($client, ['side' => 'contact', 'rule' => '']);
        self::assertNull($noAudience['count']);
        self::assertTrue($noAudience['needsAudience']);
    }

    /** While typing, a broken rule is an answer — the one the save would give. */
    public function testAMalformedRuleIsPreviewedAsTheMessageTheValidatorWouldGive(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $preview = $this->preview($client, [
            'side' => 'contact',
            'audience' => $audience->id,
            'rule' => '[{"field":"nope","op":"=","value":"x"}]',
        ]);

        $error = $preview['error'] ?? null;
        self::assertIsString($error);
        self::assertStringContainsString('unknown field', $error);
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

    /** @return array<mixed> */
    private function vocabulary(KernelBrowser $client, string $side, string $source): array
    {
        $client->request(Request::METHOD_GET, '/admin/newsletter/criteria/vocabulary?side='.$side.'&source='.$source);
        self::assertSame(200, $client->getResponse()->getStatusCode());

        return $this->decode($client);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<mixed>
     */
    private function preview(KernelBrowser $client, array $payload): array
    {
        $client->request(
            Request::METHOD_POST,
            '/admin/newsletter/criteria/preview',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertSame(200, $client->getResponse()->getStatusCode(), substr(strip_tags((string) $client->getResponse()->getContent()), 0, 900));

        return $this->decode($client);
    }

    /** @return array<mixed> */
    private function decode(KernelBrowser $client): array
    {
        $decoded = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function subArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        self::assertIsArray($value);

        return $value;
    }

    /**
     * The URL the campaign's own "Recipients" button points at — reading it off
     * the page is what keeps the filter and the ledger tested together.
     */
    private function ledgerUrl(KernelBrowser $client, Campaign $campaign): string
    {
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/'.$campaign->id);
        $link = $crawler->filter('a[href*="campaign-recipient"]');

        self::assertGreaterThan(0, $link->count(), 'the campaign detail page links to its ledger');

        return (string) $link->attr('href');
    }

    private function campaign(Audience $audience, string $subject): Campaign
    {
        $campaign = new Campaign();
        $campaign->audience = $audience;
        $campaign->subject = $subject;
        $campaign->bodyMarkdown = 'Body';

        $this->entityManager()->persist($campaign);

        return $campaign;
    }

    /** A row of the send ledger, in the state {@see CampaignSender::arm()} leaves it in. */
    private function recipient(Campaign $campaign, string $email): CampaignRecipient
    {
        $entityManager = $this->entityManager();
        $audience = $campaign->audience;
        self::assertInstanceOf(Audience::class, $audience);

        $contact = new Contact($audience, $email);
        $contact->optIn(false);

        $entityManager->persist($contact);

        $recipient = new CampaignRecipient($campaign, $contact);
        $entityManager->persist($recipient);

        return $recipient;
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

    /**
     * A parent page with one page under it, which is what makes its slug worth
     * suggesting. Returns the parent's slug.
     */
    private function seedSection(): string
    {
        $entityManager = $this->entityManager();
        $slug = 'section-'.bin2hex(random_bytes(5));

        $parent = new Page();
        $parent->slug = $slug;
        $parent->h1 = $slug;
        $parent->locale = 'en';
        $parent->host = 'localhost.dev';

        $child = new Page();
        $child->slug = $slug.'/child';
        $child->h1 = 'Child';
        $child->locale = 'en';
        $child->host = 'localhost.dev';
        $child->parentPage = $parent;

        $entityManager->persist($parent);
        $entityManager->persist($child);
        $entityManager->flush();

        $this->sectionPageIds = [$child->id, $parent->id];

        return $slug;
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
