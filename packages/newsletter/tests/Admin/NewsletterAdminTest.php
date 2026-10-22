<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Admin;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Entity\ClickEvent;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Service\CampaignSender;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;

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
                    'DELETE FROM newsletter_click_event WHERE contact_id IN (SELECT id FROM newsletter_contact WHERE audience_id = :id)',
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

        foreach (['audience', 'contact', 'campaign', 'template', 'campaign-recipient', 'automation', 'automation-delivery'] as $section) {
            $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/'.$section);
            self::assertSame(200, $client->getResponse()->getStatusCode(), $section.' index');
            self::assertCount(7, $crawler->filter('.pw-newsletter-nav a'));
            $viewport = $crawler->filter('meta[name="viewport"]')->attr('content') ?? '';
            self::assertStringNotContainsString('user-scalable=no', $viewport);
            self::assertStringNotContainsString('maximum-scale=1', $viewport);
            $activeSection = 'campaign-recipient' === $section ? 'campaign' : $section;
            self::assertStringEndsWith(
                '/admin/newsletter/'.$activeSection,
                $crawler->filter('.pw-newsletter-nav a[aria-current="page"]')->attr('href') ?? '',
            );
        }
    }

    public function testStatisticsFiltersHaveMatchingLabels(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/statistics');
        self::assertResponseIsSuccessful();
        foreach (['from', 'to', 'audience'] as $field) {
            $id = 'statistics-'.$field;
            self::assertCount(1, $crawler->filter('#'.$id));
            self::assertCount(1, $crawler->filter('label[for="'.$id.'"]'));
        }
    }

    public function testContactIdentityIsCompactAndKeepsPhoneOnlyContactsVisible(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $phone = $this->phoneContact($audience);
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact');
        self::assertResponseIsSuccessful();
        $identities = $crawler->filter('.pw-newsletter-contact-identity');
        self::assertStringContainsString('admin-contact@example.tld', implode(' ', $identities->each(static fn (Crawler $identity): string => $identity->text())));
        self::assertStringContainsString('0612345678', $crawler->filter('article')->text());
        self::assertCount(0, $crawler->filter('td[data-column="phone"], td[data-column="tags"], td[data-column="locale"]'));

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$phone->id);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('0612345678', $crawler->filter('h1')->text());
        self::assertStringNotContainsString('Null', $crawler->filter('article')->text());
        self::assertStringContainsString('Subscribed', $crawler->filter('article')->text());
        $addressed = $this->entityManager()->getRepository(Contact::class)->findOneBy(['email' => 'admin-contact@example.tld', 'audience' => $audience->id]);
        self::assertInstanceOf(Contact::class, $addressed);
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$addressed->id);
        self::assertSame('Subscribed', $crawler->filter('table tbody .badge')->first()->text());
        self::assertCount(1, $crawler->filter('.pw-newsletter-contact-actions form[action$="/unsubscribe"] input[name="_token"]'));
        self::assertCount(1, $crawler->filter('.page-actions > a.action-edit'));
        self::assertCount(0, $crawler->filter('.page-actions > form[action$="/unsubscribe"]'));
    }

    public function testWritingFormsHaveTranslatedGuidanceAndOneCreationPath(): void
    {
        $client = $this->loginUser();
        $this->seed();
        foreach (['audience', 'campaign', 'template', 'automation'] as $section) {
            $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/'.$section.'/new');
            self::assertResponseIsSuccessful();
            self::assertDoesNotMatchRegularExpression('/\bnewsletter\.[a-z][a-zA-Z.]+/', $crawler->filter('article')->text());
            self::assertCount(0, $crawler->filter('button[value="saveAndAddAnother"]'));
            if (\in_array($section, ['campaign', 'automation'], true)) {
                $id = 'newsletter-'.$section.'-audience-label';
                self::assertSame($id, $crawler->filter('select[name="'.ucfirst($section).'[audience]"]')->attr('aria-labelledby'));
                self::assertCount(1, $crawler->filter('label#'.$id));
            }
        }
    }

    public function testEmailDesignCanBePreviewedWithoutSavingAndThenSaved(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $audienceId = $audience->id;
        $url = '/admin/newsletter/audience/'.$audienceId.'/email-design';
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/audience');
        self::assertCount(1, $crawler->filter('a[href$="'.$url.'"]'));

        $crawler = $client->request(Request::METHOD_GET, $url);
        self::assertResponseIsSuccessful();
        self::assertSame('', $crawler->filter('iframe')->attr('sandbox'));
        self::assertSame('mobile', $crawler->filter('[data-pw-email-preview]')->attr('data-viewport'));
        self::assertSame('true', $crawler->filter('[data-preview-width="mobile"]')->attr('aria-pressed'));
        self::assertSame('false', $crawler->filter('[data-preview-width="desktop"]')->attr('aria-pressed'));
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[emailLayout]'] = 'framed';
        $form['email_design[headerMarkdown]'] = '## Our brand';
        $form['email_design[footerMarkdown]'] = 'Our signature';
        $form['email_design[sampleBody]'] = 'Hello **%name%**.';
        $values = $form->getPhpValues();
        $values['preview'] = '1';
        $client->request(Request::METHOD_POST, $url, $values);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsString($data['html']);
        $preview = new Crawler($data['html']);
        self::assertCount(2, $preview->filter('table[role="presentation"]'));
        self::assertStringContainsString('Our brand', $preview->filter('body')->text());
        self::assertStringContainsString('Our signature', $preview->filter('body')->text());
        self::assertStringContainsString('Hello Alex.', $preview->filter('body')->text());
        self::assertEmailCount(0);

        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $audienceId);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertFalse($stored->emailWrapped);
        self::assertSame(Audience::DEFAULT_EMAIL_HEADER, $stored->headerMarkdown);
        self::assertSame(Audience::DEFAULT_EMAIL_FOOTER, $stored->footerMarkdown);

        unset($values['preview']);
        $client->request(Request::METHOD_POST, $url, $values);
        self::assertResponseRedirects($url);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $audienceId);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertTrue($stored->emailWrapped);
        self::assertSame('## Our brand', $stored->headerMarkdown);
        self::assertSame('Our signature', $stored->footerMarkdown);

        $crawler = $client->request(Request::METHOD_GET, $url);
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[emailLayout]'] = 'simple';
        $client->submit($form);
        self::assertResponseRedirects($url);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $audienceId);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertFalse($stored->emailWrapped);
        self::assertSame('## Our brand', $stored->headerMarkdown);

        $crawler = $client->request(Request::METHOD_GET, $url);
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[headerMarkdown]'] = '';
        $form['email_design[footerMarkdown]'] = '';
        $client->submit($form);
        self::assertResponseRedirects($url);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $audienceId);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame('', $stored->headerMarkdown);
        self::assertSame('', $stored->footerMarkdown);
    }

    public function testEmailDesignIsNotAPublicPreviewEndpoint(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_POST, '/admin/newsletter/audience/1/email-design', ['preview' => '1']);
        self::assertResponseRedirects('/login');
    }

    /** @return iterable<string, array{string}> */
    public static function twigPresets(): iterable
    {
        yield 'classic' => ['pushword'];
        yield 'letter' => ['pushword_letter'];
        yield 'bulletin' => ['pushword_bulletin'];
    }

    #[DataProvider('twigPresets')]
    public function testATwigPresetCanBePreviewedAndSavedAndRejectsAnUnregisteredTemplate(string $preset): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $id = $audience->id;
        $url = '/admin/newsletter/audience/'.$id.'/email-design';
        $crawler = $client->request(Request::METHOD_GET, $url);
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[emailLayout]'] = 'twig:'.$preset;
        $form['email_design[headerMarkdown]'] = 'Retained header';
        $form['email_design[sampleBody]'] = 'Live content';
        $values = $form->getPhpValues();
        $client->request(Request::METHOD_POST, $url, [...$values, 'preview' => '1']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsString($data['html']);
        self::assertStringContainsString('Pushword', $data['html']);
        self::assertStringContainsString('Live content', $data['html']);
        self::assertStringNotContainsString('Retained header', $data['html']);
        self::assertEmailCount(0);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertNull($stored->emailTemplate);

        $client->request(Request::METHOD_POST, $url, $values);
        self::assertResponseRedirects($url);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame($preset, $stored->emailTemplate);
        self::assertSame('Retained header', $stored->headerMarkdown);
        $crawler = $client->request(Request::METHOD_GET, $url);
        self::assertCount(1, $crawler->filter('input[value="twig:'.$preset.'"]:checked'));
        self::assertCount(1, $crawler->filter('[data-email-editable][hidden]'));

        $form = $crawler->filter('form[name="email_design"]')->form();
        $values = $form->getPhpValues();
        self::assertIsArray($values['email_design']);
        $values['email_design']['emailLayout'] = 'twig:unregistered';
        $client->request(Request::METHOD_POST, $url, [...$values, 'preview' => '1']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $client->request(Request::METHOD_POST, $url, $values);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame($preset, $stored->emailTemplate);

        $crawler = $client->request(Request::METHOD_GET, $url);
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[emailLayout]'] = 'framed';
        $client->submit($form);
        self::assertResponseRedirects($url);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertNull($stored->emailTemplate);
        self::assertTrue($stored->emailWrapped);
        self::assertSame('Retained header', $stored->headerMarkdown);
    }

    public function testEmailDesignRejectsAnInvalidTokenForPreviewAndSave(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $url = '/admin/newsletter/audience/'.$audience->id.'/email-design';
        $crawler = $client->request(Request::METHOD_GET, $url);
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[headerMarkdown]'] = 'Not saved';
        $form['email_design[_token]'] = 'invalid';
        $values = $form->getPhpValues();
        $values['preview'] = '1';
        $client->request(Request::METHOD_POST, $url, $values);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        unset($values['preview']);
        $client->request(Request::METHOD_POST, $url, $values);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $audience->id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame(Audience::DEFAULT_EMAIL_HEADER, $stored->headerMarkdown);
    }

    public function testAutomationRowsShowTheirStatusAndActualStepCount(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        foreach ([0, 2] as $stepCount) {
            $automation = new Automation();
            $automation->audience = $audience;
            $automation->name = 'Sequence '.$stepCount;
            $automation->enabled = 0 !== $stepCount;

            for ($position = 0; $position < $stepCount; ++$position) {
                $step = new AutomationStep();
                $step->position = $position;
                $step->subject = 'Email '.$position;
                $automation->addStep($step);
            }

            $this->entityManager()->persist($automation);
        }

        $this->entityManager()->flush();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/automation', [
            'query' => 'Sequence',
            'filters' => ['audience' => ['comparison' => '=', 'value' => $audience->id]],
        ]);

        foreach ([0 => 'Paused', 2 => 'Active'] as $stepCount => $status) {
            $row = $crawler->filter('tr')->reduce(static fn (Crawler $row): bool => str_contains($row->text(), 'Sequence '.$stepCount));
            self::assertCount(1, $row);
            self::assertSame((string) $stepCount, $row->filter('td.field-integer')->text());
            self::assertSame($status, $row->filter('td[data-column="enabled"]')->text());
            self::assertSame('Contact event', $row->filter('td[data-column="source"]')->text());
        }

        $crawler = $client->click($crawler->filter('.pw-newsletter-status')->selectLink('Paused')->link());
        self::assertResponseIsSuccessful();
        self::assertSame('Paused', $crawler->filter('.pw-newsletter-status [aria-current="page"]')->text());
        self::assertSelectorTextContains('.datagrid tbody', 'Sequence 0');
        self::assertSelectorTextNotContains('.datagrid tbody', 'Sequence 2');
        self::assertStringContainsString('query=Sequence', $client->getRequest()->getUri());
        self::assertSame(['comparison' => '=', 'value' => (string) $audience->id], $client->getRequest()->query->all('filters')['audience']);

        $client->click($crawler->filter('.pw-newsletter-status')->selectLink('All')->link());
        self::assertSelectorTextContains('.datagrid tbody', 'Sequence 0');
        self::assertSelectorTextContains('.datagrid tbody', 'Sequence 2');
    }

    public function testAnEmptySearchCanBeClearedWithoutLeavingTheNewsletterList(): void
    {
        $client = $this->loginUser();
        $this->seed();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/automation?query=unmatched-automation');

        self::assertCount(0, $crawler->filter('.pw-newsletter-empty'));
        $clearLink = $crawler->filter('.pw-empty-state a')->link();
        self::assertStringNotContainsString('query=', $clearLink->getUri());
        $client->click($clearLink);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.pw-newsletter-nav a[aria-current="page"][href$="/automation"]');
        self::assertSelectorExists('.pw-newsletter-empty');
    }

    public function testTheCampaignListLeadsToReviewBeforeSending(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $campaign = $this->campaign($audience, 'Review this draft');
        $scheduled = $this->campaign($audience, 'Already scheduled')->schedule(new DateTimeImmutable('+1 day'));
        $this->entityManager()->flush();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign');
        $crawler = $client->click($crawler->filter('.pw-newsletter-status')->selectLink('Drafts')->link());
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('tr[data-id="'.$scheduled->id.'"]'));
        $row = $crawler->filter('tr[data-id="'.$campaign->id.'"]');
        self::assertSame('Review and send', $row->filter('a.action-detail')->text());
        self::assertCount(0, $row->filter('form[method="post"]'));

        $client->click($row->filter('a.action-detail')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action*="/send"]');
        self::assertSelectorExists('form[action*="/schedule"]');
    }

    public function testAnAutomationSequenceCanBeCreatedAndReopened(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/automation/new');
        self::assertSame('Add an email', $crawler->filter('.field-collection-add-button')->text());
        $triggerBody = $crawler->filter('input[name="Automation[activeFrom]"]')->closest('.form-fieldset-body');
        self::assertNotNull($triggerBody);
        self::assertStringNotContainsString('collapse', $triggerBody->attr('class') ?? '');
        $advancedBody = $crawler->filter('input[name="Automation[transactional]"]')->closest('.form-fieldset-body');
        self::assertNotNull($advancedBody);
        $advancedClasses = $advancedBody->attr('class') ?? '';
        self::assertStringContainsString('collapse', $advancedClasses);
        self::assertStringNotContainsString('show', $advancedClasses);

        $values = $crawler->filter('form[name="Automation"]')->form()->getPhpValues();
        self::assertIsArray($values['Automation']);
        $values['Automation']['name'] = 'Editable sequence';
        $values['Automation']['audience'] = (string) $audience->id;
        $values['Automation']['steps'] = [
            ['sequenceNumber' => 1, 'delayMinutes' => 0, 'subject' => 'Welcome', 'bodyMarkdown' => 'Hello!'],
            ['sequenceNumber' => 2, 'delayMinutes' => 1440, 'subject' => 'One day later', 'bodyMarkdown' => 'Some news.'],
        ];
        $client->request(Request::METHOD_POST, '/admin/newsletter/automation/new', $values);
        self::assertResponseRedirects();

        $automation = $this->entityManager()->getRepository(Automation::class)->findOneBy(['name' => 'Editable sequence']);
        self::assertInstanceOf(Automation::class, $automation);
        self::assertSame(2, $automation->countSteps());
        self::assertSame(1440, $automation->getStep(1)?->delayMinutes);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/automation/'.$automation->id.'/edit');
        self::assertCount(2, $crawler->filter('.pw-newsletter-sequence .accordion-collapse.show'));
        self::assertSame('Welcome', $crawler->filter('input[name="Automation[steps][0][subject]"]')->attr('value'));
        self::assertCount(1, $crawler->filter('[data-preview-step="0"]'));
        self::assertCount(1, $crawler->filter('[data-preview-step="1"]'));
        self::assertCount(1, $crawler->filter('#Automation_steps_0_bodyMarkdown-preview'));
        self::assertCount(1, $crawler->filter('#Automation_steps_1_bodyMarkdown-preview'));
    }

    public function testTheCampaignIndexShowsItsAudience(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $this->campaign($audience, 'Audience on index');
        $this->entityManager()->flush();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $audienceLink = $crawler->filter('td.field-association a[href$="/admin/newsletter/audience/'.$audience->id.'"]');
        self::assertCount(1, $audienceLink);
        self::assertSame('Admin test', $audienceLink->text());
    }

    public function testTheCampaignIndexShowsTheActualOrScheduledSendDate(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $draft = $this->campaign($audience, 'No send date');
        $scheduled = $this->campaign($audience, 'Scheduled send date')
            ->schedule(new DateTimeImmutable('2030-02-03 10:30:00'));
        $sent = $this->campaign($audience, 'Actual send date')
            ->schedule(new DateTimeImmutable('2020-01-02 09:00:00'))
            ->markSent();
        $this->entityManager()->flush();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        foreach ([
            [$draft, '—'],
            [$scheduled, '03/02/2030 10:30'],
            [$sent, $sent->sentAt?->format('d/m/Y H:i')],
        ] as [$campaign, $expectedDate]) {
            self::assertSame(
                $expectedDate,
                $crawler->filter('tr[data-id="'.$campaign->id.'"] td[data-column="sendDate"]')->text(),
            );
        }
    }

    /**
     * The switch that sends without a way off the list exists at three levels,
     * and it is worth nothing on a form nobody can reach it from.
     */
    public function testTheTransactionalSwitchIsOnAllThreeForms(): void
    {
        $client = $this->loginUser();
        $this->seed();

        foreach (['audience', 'campaign', 'automation'] as $section) {
            $client->request(Request::METHOD_GET, '/admin/newsletter/'.$section.'/new');

            self::assertMatchesRegularExpression(
                '/name="[^"]*\[transactional\]"/',
                (string) $client->getResponse()->getContent(),
                $section.' form',
            );
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
        self::assertMatchesRegularExpression('/name="[^"]*\[translationsAsJson\]"/', $html);
    }

    public function testACampaignChoosesItsOwnDesignAndCanReturnToTheAudienceDefault(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');
        self::assertStringContainsString('Choose the audience and design', $crawler->filter('.form-fieldset')->first()->text());
        foreach (['pushword', 'pushword_letter', 'pushword_bulletin'] as $preset) {
            self::assertCount(1, $crawler->filter('select[name="Campaign[emailLayout]"] option[value="twig:'.$preset.'"]'));
        }

        $form = $crawler->filter('form[name="Campaign"]')->form();
        $form['Campaign[subject]'] = 'Own email design';
        $form['Campaign[audience]'] = (string) $audience->id;
        $form['Campaign[emailLayout]'] = 'twig:pushword_letter';
        $client->submit($form);
        self::assertResponseRedirects();
        $campaign = $this->entityManager()->getRepository(Campaign::class)->findOneBy(['subject' => 'Own email design']);
        self::assertInstanceOf(Campaign::class, $campaign);
        $id = $campaign->id;
        self::assertSame('twig:pushword_letter', $campaign->emailLayout);
        $storedAudience = $this->entityManager()->find(Audience::class, $audience->id);
        self::assertInstanceOf(Audience::class, $storedAudience);
        self::assertSame('simple', $storedAudience->emailLayout);

        $url = '/admin/newsletter/campaign/'.$id.'/edit';
        $crawler = $client->request(Request::METHOD_GET, $url);
        self::assertCount(1, $crawler->filter('select[name="Campaign[emailLayout]"] option[value="twig:pushword_letter"][selected]'));
        $form = $crawler->filter('form[name="Campaign"]')->form();
        $values = $form->getPhpValues();
        self::assertIsArray($values['Campaign']);
        $values['Campaign']['emailLayout'] = 'twig:unregistered';
        $client->request(Request::METHOD_POST, $url, $values);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Campaign::class, $id);
        self::assertInstanceOf(Campaign::class, $stored);
        self::assertSame('twig:pushword_letter', $stored->emailLayout);

        $crawler = $client->request(Request::METHOD_GET, $url);
        $form = $crawler->filter('form[name="Campaign"]')->form();
        $form['Campaign[emailLayout]'] = '';
        $client->submit($form);
        self::assertResponseRedirects();
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Campaign::class, $id);
        self::assertInstanceOf(Campaign::class, $stored);
        self::assertNull($stored->emailLayout);
    }

    public function testTheCampaignTestActionUsesTheSavedCampaignDesign(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $audience->headerMarkdown = 'Audience-only heading';

        $campaign = $this->campaign($audience, 'Preset test email');
        $campaign->emailLayout = 'twig:pushword';
        $this->entityManager()->flush();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/'.$campaign->id.'/send-test');
        $form = $crawler->filter('form[method="post"]')->form();
        $form['emails'] = 'test@example.tld';
        $client->submit($form);
        self::assertResponseRedirects();
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        $html = $message->getHtmlBody();
        self::assertIsString($html);
        self::assertStringContainsString('Pushword', $html);
        self::assertStringNotContainsString('Audience-only heading', $html);
    }

    /** The per-locale bodies edit the way the segment does: JSON, validated before it is stored. */
    public function testACampaignIsWrittenInSeveralLanguages(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');
        $form = $crawler->filter('form[name="Campaign"]')->form();
        $form['Campaign[subject]'] = 'Translated';
        $form['Campaign[audience]'] = (string) $audience->id;
        $form['Campaign[translationsAsJson]'] = '{"DE": {"subject": "Hallo", "bodyMarkdown": "Lies das."}}';
        $client->submit($form);

        $campaign = $this->entityManager()->getRepository(Campaign::class)->findOneBy(['subject' => 'Translated']);
        self::assertInstanceOf(Campaign::class, $campaign);
        self::assertSame(['de' => ['subject' => 'Hallo', 'bodyMarkdown' => 'Lies das.']], $campaign->translations);
        self::assertSame('Hallo', $campaign->contentFor('de-CH')['subject']);
    }

    /** Clearing the field takes the translations back, rather than leaving the last ones. */
    public function testEmptyingTheTranslationsClearsThem(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $campaign = $this->campaign($audience, 'Was translated');
        $campaign->translations = ['de' => ['subject' => 'Hallo']];
        $this->entityManager()->flush();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/'.$campaign->id.'/edit');
        $form = $crawler->filter('form[name="Campaign"]')->form();
        $form['Campaign[translationsAsJson]'] = '';
        $client->submit($form);

        $stored = $this->entityManager()->getRepository(Campaign::class)->findOneBy(['subject' => 'Was translated']);
        self::assertInstanceOf(Campaign::class, $stored);
        self::assertSame([], $stored->translations);
    }

    /** Malformed translations come back as a form error, never as a 500 or an empty mail. */
    public function testMalformedTranslationsAreAFormError(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');
        $form = $crawler->filter('form[name="Campaign"]')->form();
        $form['Campaign[subject]'] = 'Broken translations';
        $form['Campaign[audience]'] = (string) $audience->id;
        $form['Campaign[translationsAsJson]'] = '{"de": "Hallo"}';
        $client->submit($form);

        self::assertSame(422, $client->getResponse()->getStatusCode());
        self::assertNull($this->entityManager()->getRepository(Campaign::class)->findOneBy(['subject' => 'Broken translations']));
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
        $form['Campaign[segmentAsJson]'] = '[{"field":"tag","op":"has","value":"Hiking"}]';
        $client->submit($form);

        $campaign = $this->entityManager()->getRepository(Campaign::class)->findOneBy(['subject' => 'Good segment']);
        self::assertInstanceOf(Campaign::class, $campaign);
        self::assertSame([['field' => 'tag', 'op' => 'has', 'value' => 'Hiking']], $campaign->segment);
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

    public function testCampaignMutationsRequirePostAndAValidToken(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $campaign = $this->campaign($audience, 'Protected mutation');
        $this->entityManager()->flush();
        $id = (int) $campaign->id;
        $url = '/admin/newsletter/campaign/'.$id.'/schedule';
        $token = $this->adminActionToken('schedule', $id);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/'.$id);
        self::assertCount(1, $crawler->filter('form[method="post"][action*="/schedule"]'));

        $client->request(Request::METHOD_GET, $url);
        self::assertContains($client->getResponse()->getStatusCode(), [Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED]);

        $client->request(Request::METHOD_POST, $url, ['_token' => 'invalid']);
        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());

        $client->request(Request::METHOD_POST, $url, [
            '_token' => $token,
        ]);
        self::assertTrue($client->getResponse()->isRedirect());
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

    public function testARecipientDetailShowsTheWholeDeliveryError(): void
    {
        $client = $this->loginUser();
        $campaign = $this->campaign($this->seed(), 'Failed delivery');
        $error = str_repeat('Transport diagnostic. ', 10).'Complete error ends here.';
        $recipient = $this->recipient($campaign, 'failed-detail@example.tld')->markFailed($error);
        $this->entityManager()->flush();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign-recipient');
        $client->click($crawler->filter('tr[data-id="'.$recipient->id.'"] a.action-detail')->link());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($error, $client->getCrawler()->filter('article')->text());
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
        self::assertContains('Hiking', $this->subArray($this->subArray($contactFields, 'tag'), 'suggestions'));

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
            'rule' => '[{"field":"tag","op":"has","value":"Hiking"}]',
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

    /** The pages the custom actions render extend the dashboard layout, so a super admin browsing as an editor keeps the way back. */
    public function testTheCustomActionPagesShowTheImpersonationBanner(): void
    {
        $client = $this->loginUser();
        $campaign = $this->campaign($this->seed(), 'Impersonated test send');
        $this->entityManager()->flush();

        /** @var class-string<User> $userClass */
        $userClass = $client->getContainer()->getParameter('pw.entity_user');
        $editor = new $userClass();
        $editor->email = 'newsletter-editor-'.uniqid().'@example.tld';
        $editor->setRoles(['ROLE_EDITOR']);
        $this->entityManager()->persist($editor);
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, '/admin?_switch_user='.urlencode($editor->email));
        self::assertResponseRedirects('/admin');

        $client->request(Request::METHOD_GET, '/admin/newsletter/contact/opt-in');
        self::assertSelectorExists('form[method=post] [name=audience]');
        self::assertSelectorTextContains('.pw-impersonation-banner', $editor->email);

        $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/'.$campaign->id.'/send-test');
        self::assertSelectorTextContains('h1', 'Impersonated test send');
        self::assertSelectorTextContains('.pw-impersonation-banner', $editor->email);
    }

    /**
     * The consent history, on the screen an opt-in is defended from. Both acts
     * name the editor who performed them, and neither carries the editor's own
     * IP — it would read as the contact's.
     */
    public function testTheConsentHistoryIsShownOnTheContactPage(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/opt-in');
        $form = $crawler->filter('form[method=post]')->form();
        $form['audience'] = (string) $audience->id;
        $form['email'] = 'ledger@example.tld';
        $client->submit($form, ['alreadyConsented' => '1']);

        $contact = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'email' => 'ledger@example.tld']);
        self::assertInstanceOf(Contact::class, $contact);

        $client->request(Request::METHOD_POST, '/admin/newsletter/contact/'.$contact->id.'/unsubscribe', [
            '_token' => $this->adminActionToken('unsubscribe', (int) $contact->id),
        ]);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$contact->id);
        $panel = $crawler->filter('.form-fieldset')->reduce(
            static fn (Crawler $node): bool => str_contains($node->text(), 'Consent history'),
        );

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertCount(1, $panel);
        self::assertCount(2, $panel->filter('tbody tr'));
        self::assertStringContainsString('Opted in', $panel->text());
        self::assertStringContainsString('Unsubscribed', $panel->text());
        self::assertSame(2, substr_count($panel->text(), 'admin:'));
        self::assertStringNotContainsString('127.0.0.1', $panel->text());
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
     * A number another row already holds comes back as a form error — and, since
     * the two are most often one person, as an offer to join them in one click.
     * The address is what survives, so the number lands on the row being edited.
     */
    public function testARefusedNumberIsOfferedAsAMergeAndPerformedInOneClick(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $phoneOnly = $this->phoneContact($audience);
        $absorbedId = $phoneOnly->id;

        $contact = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'email' => 'admin-contact@example.tld']);
        self::assertInstanceOf(Contact::class, $contact);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$contact->id.'/edit');
        $form = $crawler->filter('form[name="Contact"]')->form();
        $form['Contact[phone]'] = '06 12 34 56 78';
        $crawler = $client->submit($form);

        // A refused form is re-rendered, which EasyAdmin answers 422 — the save
        // did not happen, and the page carries the offer.
        self::assertSame(422, $client->getResponse()->getStatusCode());
        $offer = $crawler->filter('form[action*="/merge"]');
        self::assertCount(1, $offer);
        self::assertSame((string) $absorbedId, $offer->filter('input[name="with"]')->attr('value'));

        $client->submit($offer->form());

        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->getRepository(Contact::class)->find($absorbedId));

        $kept = $this->entityManager()->getRepository(Contact::class)->find($contact->id);
        self::assertInstanceOf(Contact::class, $kept);
        self::assertSame('0612345678', $kept->phone);
        self::assertSame('admin-contact@example.tld', $kept->email);
    }

    /**
     * The same offer from the other side — a number gaining an address another
     * row holds. The address is what survives, so the click lands on the *other*
     * contact's page and the row being edited is the one that goes.
     */
    public function testARefusedAddressIsOfferedAsAMergeOntoTheAddressedRow(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $phoneOnly = $this->phoneContact($audience);
        $absorbedId = $phoneOnly->id;

        $addressed = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'email' => 'admin-contact@example.tld']);
        self::assertInstanceOf(Contact::class, $addressed);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$phoneOnly->id.'/edit');
        $form = $crawler->filter('form[name="Contact"]')->form();
        $form['Contact[email]'] = 'admin-contact@example.tld';
        $crawler = $client->submit($form);

        self::assertSame(422, $client->getResponse()->getStatusCode());
        $offer = $crawler->filter('form[action*="/merge"]');
        self::assertCount(1, $offer);
        self::assertStringContainsString(
            'admin-contact@example.tld',
            $crawler->filter('.alert-warning')->text(),
            'the row that stays is named, and it is not the one being edited',
        );

        $client->submit($offer->form());

        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->getRepository(Contact::class)->find($absorbedId));

        $kept = $this->entityManager()->getRepository(Contact::class)->find($addressed->id);
        self::assertInstanceOf(Contact::class, $kept);
        self::assertSame('0612345678', $kept->phone);
    }

    /** A merge deletes a row, so it is never a link somebody else's page can follow. */
    public function testTheMergeRouteRefusesAPostWithoutAToken(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $phoneOnly = $this->phoneContact($audience);

        $addressed = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'email' => 'admin-contact@example.tld']);
        self::assertInstanceOf(Contact::class, $addressed);

        $client->request(Request::METHOD_POST, '/admin/newsletter/contact/'.$addressed->id.'/merge', ['with' => $phoneOnly->id]);

        $this->entityManager()->clear();
        self::assertNotNull(
            $this->entityManager()->getRepository(Contact::class)->find($phoneOnly->id),
            'the row is still there',
        );
    }

    /** Nothing to offer when the save is refused for its own reasons. */
    public function testAContactWithoutAnIdentifierIsRefusedWithNoMergeOffered(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $contact = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'email' => 'admin-contact@example.tld']);
        self::assertInstanceOf(Contact::class, $contact);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$contact->id.'/edit');
        $form = $crawler->filter('form[name="Contact"]')->form();
        $form['Contact[email]'] = '';
        $crawler = $client->submit($form);

        self::assertSame(422, $client->getResponse()->getStatusCode());
        self::assertCount(0, $crawler->filter('form[action*="/merge"]'));
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

    /**
     * A booking taken over the phone: the number is the whole of what the site
     * knows. Nothing to confirm by mail, so the row is subscribed at once, and
     * `source` records who entered it.
     */
    public function testAnAdminOptsInSomebodyByPhoneAlone(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/opt-in');
        $form = $crawler->filter('form[method=post]')->form();
        $form['audience'] = (string) $audience->id;
        $form['email'] = '';
        $form['phone'] = '06 12 34 56 78';
        $form['name'] = 'Called';
        $client->submit($form);

        $contact = $this->entityManager()->getRepository(Contact::class)
            ->findOneBy(['audience' => $audience, 'phone' => '0612345678']);

        self::assertInstanceOf(Contact::class, $contact);
        self::assertNull($contact->email);
        self::assertTrue($contact->isSubscribed());
        self::assertFalse($contact->isMailable());
        self::assertStringStartsWith('admin:', (string) $contact->source);
    }

    /** Neither one nor the other is guessed: nothing is written and the form comes back. */
    public function testAnOptInWithoutAnyIdentifierWritesNothing(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();
        $before = $this->entityManager()->getRepository(Contact::class)->count(['audience' => $audience]);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/opt-in');
        $form = $crawler->filter('form[method=post]')->form();
        $form['audience'] = (string) $audience->id;
        $form['email'] = '';
        $form['phone'] = '';
        $client->submit($form);
        $client->followRedirect();

        self::assertStringContainsString('/admin/newsletter/contact/opt-in', $client->getRequest()->getUri());
        self::assertSame($before, $this->entityManager()->getRepository(Contact::class)->count(['audience' => $audience]));
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
        self::assertIsInt($contactId);
        $unsubscribeToken = $this->adminActionToken('unsubscribe', $contactId);
        $resubscribeToken = $this->adminActionToken('resubscribe', $contactId);

        $unsubscribeUrl = '/admin/newsletter/contact/'.$contactId.'/unsubscribe';
        $client->request(Request::METHOD_GET, $unsubscribeUrl);
        self::assertContains($client->getResponse()->getStatusCode(), [Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED]);
        self::assertSame('subscribed', $this->reloadContact($contactId)->getStatusLabel());

        $client->request(Request::METHOD_POST, $unsubscribeUrl, ['_token' => 'invalid']);
        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        self::assertSame('subscribed', $this->reloadContact($contactId)->getStatusLabel());

        $client->request(Request::METHOD_POST, $unsubscribeUrl, [
            '_token' => $unsubscribeToken,
        ]);
        $client->followRedirect();
        self::assertSame('unsubscribed', $this->reloadContact($contactId)->getStatusLabel());

        $client->request(Request::METHOD_POST, '/admin/newsletter/contact/'.$contactId.'/resubscribe', [
            '_token' => $resubscribeToken,
        ]);
        $client->followRedirect();
        self::assertSame('subscribed', $this->reloadContact($contactId)->getStatusLabel());
    }

    public function testTheAudienceFormOffersTheClickTrackingSwitch(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/audience/'.$audience->id.'/edit');

        self::assertCount(1, $crawler->filter('input[name="Audience[clickTracking]"]'));
    }

    /** The consent field is editable on the form, and clearing it purges the clicks it covered. */
    public function testClearingTheConsentFromTheAdminPurgesTheClicks(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $contact = new Contact($audience, 'tracked@example.tld');
        $contact->optIn(false);
        $contact->clickTrackingConsentAt = new DateTimeImmutable();
        $this->entityManager()->persist($contact);

        $campaign = $this->campaign($audience, 'Tracked');
        $this->entityManager()->persist(ClickEvent::onCampaign($contact, $campaign, 'https://localhost.dev/article'));
        $this->entityManager()->flush();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/contact/'.$contact->id.'/edit');
        $form = $crawler->filter('form[name="Contact"]')->form();
        $form['Contact[clickTrackingConsentAt]'] = '';
        $client->submit($form);

        self::assertTrue($client->getResponse()->isRedirection(), 'the save must go through for the withdrawal to mean anything');
        self::assertNull($this->reloadContact($contact->id)->clickTrackingConsentAt);
        self::assertCount(0, $this->entityManager()->getRepository(ClickEvent::class)->findBy(['contact' => $contact]));
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

        $client->request(Request::METHOD_POST, '/admin/newsletter/contact/'.$contact->id.'/confirm', [
            '_token' => $this->adminActionToken('confirm', (int) $contact->id),
        ]);
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

    private function adminActionToken(string $action, int $id): string
    {
        self::assertInstanceOf(KernelBrowser::class, $this->client);

        return $this->csrfToken($this->client, 'admin_action_'.$action.'_'.$id);
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

    /** Somebody the site can only phone — the half a merge joins to an address. */
    private function phoneContact(Audience $audience, string $phone = '0612345678'): Contact
    {
        $contact = new Contact($audience, null, $phone);
        $contact->name = 'Called';
        $contact->optIn(false);

        $this->entityManager()->persist($contact);
        $this->entityManager()->flush();

        return $contact;
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
        $audience->interests = ['Hiking'];

        $entityManager->persist($audience);

        $contact = new Contact($audience, 'admin-contact@example.tld');
        $contact->setTags(['Hiking'])->optIn(false);
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
