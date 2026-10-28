<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Admin;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\EmailTemplate;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class EmailTemplateAdminTest extends AbstractAdminTestClass
{
    /** @var list<string> */
    private array $names = [];

    /** @var list<int> */
    private array $audienceIds = [];

    protected function tearDown(): void
    {
        if ($this->client instanceof KernelBrowser) {
            $connection = $this->entityManager()->getConnection();
            foreach ($this->names as $name) {
                $connection->executeStatement('DELETE FROM newsletter_email_template WHERE name = :name', ['name' => $name]);
            }

            foreach ($this->audienceIds as $id) {
                $connection->executeStatement('DELETE FROM newsletter_campaign WHERE audience_id = :id', ['id' => $id]);
                $connection->executeStatement('DELETE FROM newsletter_audience WHERE id = :id', ['id' => $id]);
            }
        }

        parent::tearDown();
    }

    public function testAnEditorCreatesPreviewsAndDuplicatesATemplateWithoutChangingTheOriginal(): void
    {
        $client = $this->loginUser();
        $name = $this->templateName();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-preview-kind="template"][data-viewport="mobile"] iframe[sandbox=""]');
        $token = $crawler->filter('[data-preview-token]')->attr('data-preview-token');
        $form = $crawler->filter('form[name="EmailTemplate"]')->form();
        self::assertSame(Audience::DEFAULT_EMAIL_HEADER, $crawler->filter('#EmailTemplate_headerMarkdown')->text());
        self::assertSame(Audience::DEFAULT_EMAIL_FOOTER, $crawler->filter('#EmailTemplate_footerMarkdown')->text());

        $client->request(Request::METHOD_POST, '/admin/newsletter/template-preview', [
            '_preview_token' => $token,
            'EmailTemplate' => ['name' => $name, 'headerMarkdown' => '## Preview %siteName%', 'footerMarkdown' => 'Preview footer', 'wrapped' => '1'],
        ]);
        self::assertResponseIsSuccessful();
        $html = $this->previewHtml($client);
        self::assertStringContainsString('Preview Pushword', $html);
        self::assertStringContainsString('Preview footer', $html);
        self::assertStringContainsString('background-color:#f3f4f6', $html);
        self::assertStringContainsString('Unsubscribe', $html);
        self::assertSame(0, $this->entityManager()->getRepository(EmailTemplate::class)->count(['name' => $name]));
        self::assertEmailCount(0);
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control') ?? '');

        $form['EmailTemplate[name]'] = $name;
        $form['EmailTemplate[headerMarkdown]'] = '## My brand';
        $form['EmailTemplate[footerMarkdown]'] = 'My signature';
        $wrapped = $form['EmailTemplate[wrapped]'];
        self::assertInstanceOf(ChoiceFormField::class, $wrapped);
        $wrapped->untick();
        $client->submit($form);
        self::assertResponseRedirects();
        $original = $this->entityManager()->getRepository(EmailTemplate::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(EmailTemplate::class, $original);
        self::assertFalse($original->wrapped);
        $id = $original->id;

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template/new?duplicate='.$id);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="EmailTemplate"]')->form();
        $copyName = $name.' (copy)';
        $this->names[] = $copyName;
        self::assertSame($copyName, $crawler->filter('#EmailTemplate_name')->attr('value'));
        self::assertSame('## My brand', $crawler->filter('#EmailTemplate_headerMarkdown')->text());
        self::assertSame('My signature', $crawler->filter('#EmailTemplate_footerMarkdown')->text());
        $form['EmailTemplate[headerMarkdown]'] = '## The copy';
        $client->submit($form);
        self::assertResponseRedirects();
        $copy = $this->entityManager()->getRepository(EmailTemplate::class)->findOneBy(['name' => $copyName]);
        self::assertInstanceOf(EmailTemplate::class, $copy);
        self::assertNotSame($id, $copy->id);
        self::assertFalse($copy->wrapped);
        $stored = $this->entityManager()->find(EmailTemplate::class, $id);
        self::assertInstanceOf(EmailTemplate::class, $stored);
        self::assertSame('## My brand', $stored->headerMarkdown);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template');
        self::assertSame('Without a frame', $crawler->filter('tr[data-id="'.$id.'"] td[data-column="wrapped"]')->text());

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');
        self::assertCount(1, $crawler->filter('select[name="Campaign[emailLayout]"] option[value="saved:'.$id.'"]'));
        self::assertCount(1, $crawler->filter('select[name="Campaign[emailLayout]"] option[value="saved:'.$copy->id.'"]'));
        $client->request(Request::METHOD_GET, '/admin/newsletter/template-choices');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($copyName, $client->getResponse()->getContent() ?: '');

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template/new?duplicate='.$id);
        $nextName = $copyName.' 2';
        $this->names[] = $nextName;
        self::assertSame($nextName, $crawler->filter('#EmailTemplate_name')->attr('value'));
        $client->submit($crawler->filter('form[name="EmailTemplate"]')->form());
        self::assertResponseRedirects();
    }

    public function testTemplateChoicesRefreshTheAudienceLayoutAfterItChanges(): void
    {
        $client = $this->loginUser();
        $audience = $this->audience();
        $id = $audience->id;
        self::assertIsInt($id);
        $client->request(Request::METHOD_GET, '/admin/newsletter/template-choices');
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['audienceLayouts']);
        self::assertSame('simple', $data['audienceLayouts'][$id]);

        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        $stored->emailLayout = 'twig:pushword_letter';
        $this->entityManager()->flush();
        $client->request(Request::METHOD_GET, '/admin/newsletter/template-choices');
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['audienceLayouts']);
        self::assertSame('twig:pushword_letter', $data['audienceLayouts'][$id]);
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control') ?? '');
    }

    public function testDuplicateNamesFitTheLimitAndPreserveUnicode(): void
    {
        $client = $this->loginUser();
        $template = $this->template();
        $template->name = str_repeat('é', 255);
        $this->names[] = $template->name;
        $this->entityManager()->flush();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template/new?duplicate='.$template->id);
        $name = $crawler->filter('#EmailTemplate_name')->attr('value');
        self::assertIsString($name);
        self::assertSame(255, mb_strlen($name));
        self::assertStringEndsWith(' (copy)', $name);
        self::assertTrue(mb_check_encoding($name));
        $this->names[] = $name;
        $client->submit($crawler->filter('form[name="EmailTemplate"]')->form());
        self::assertResponseRedirects();
    }

    public function testTheLibraryShowsAndPreviewsSiteTemplatesWithoutAllowingArbitraryPaths(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template');
        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('.pw-newsletter-site-templates li'));
        $client->click($crawler->filter('.pw-newsletter-site-templates a')->first()->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-viewport="mobile"] iframe[sandbox=""]');
        self::assertSelectorExists('.pw-newsletter-nav a[aria-current="page"][href$="/template"]');
        self::assertStringContainsString('background-color:#f3f4f6', $client->getCrawler()->filter('iframe')->attr('srcdoc') ?? '');
        $client->request(Request::METHOD_GET, '/admin/newsletter/template/site/unregistered');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertEmailCount(0);
    }

    public function testAutomationPreviewsUnsavedStepsUsingTheAudienceDesignWithoutWrites(): void
    {
        $client = $this->loginUser();
        $audience = $this->audience();
        $template = $this->template();
        $audience->emailLayout = 'saved:'.$template->id;
        $this->entityManager()->flush();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/automation/new');
        $prototype = $crawler->filter('[data-prototype]')->attr('data-prototype');
        self::assertIsString($prototype);
        self::assertStringContainsString('data-preview-kind="automation"', $prototype);
        self::assertStringContainsString('data-preview-step="__stepsname__"', $prototype);
        preg_match('/data-preview-token="([^"]+)"/', $prototype, $matches);
        self::assertArrayHasKey(1, $matches);
        $data = [
            '_preview_token' => html_entity_decode($matches[1]), '_preview_step' => '2',
            'Automation' => ['audience' => (string) $audience->id, 'steps' => [2 => ['subject' => 'Unsaved step', 'bodyMarkdown' => "**Unsaved sequence** for %name%.\n\n![Trip](1.jpg)"]]],
        ];
        $client->request(Request::METHOD_POST, '/admin/newsletter/automation-preview', $data);
        self::assertResponseIsSuccessful();
        $html = $this->previewHtml($client);
        self::assertStringContainsString('<title>Unsaved step</title>', $html);
        self::assertStringContainsString('<strong>Unsaved sequence</strong> for Alex.', $html);
        self::assertStringContainsString('src="https://localhost.dev/media/default/1.jpg" alt="Trip"', $html);
        self::assertStringContainsString('Saved Pushword', $html);
        self::assertStringContainsString('#unsubscribe', $html);
        $data['Automation']['transactional'] = '1';
        $client->request(Request::METHOD_POST, '/admin/newsletter/automation-preview', $data);
        self::assertStringNotContainsString('#unsubscribe', $this->previewHtml($client));
        $data['_preview_step'] = '0';
        $client->request(Request::METHOD_POST, '/admin/newsletter/automation-preview', $data);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data['Automation']['audience'] = '';
        $client->request(Request::METHOD_POST, '/admin/newsletter/automation-preview', $data);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertEmailCount(0);
        self::assertSame(0, $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM newsletter_automation WHERE audience_id = ?', [$audience->id]));
    }

    public function testATemplateNeedsANameAndRejectsAnExistingName(): void
    {
        $client = $this->loginUser();
        $template = $this->template();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template/new');
        $form = $crawler->filter('form[name="EmailTemplate"]')->form();
        $client->submit($form);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorExists('[data-preview-kind="template"]');

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template/new');
        $form = $crawler->filter('form[name="EmailTemplate"]')->form();
        $form['EmailTemplate[name]'] = $template->name;
        $client->submit($form);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(1, $this->entityManager()->getRepository(EmailTemplate::class)->count(['name' => $template->name]));

        $savedName = $template->name;
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template/'.$template->id.'/edit');
        $form = $crawler->filter('form[name="EmailTemplate"]')->form();
        $form['EmailTemplate[name]'] = '';
        $client->submit($form);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $savedValues = json_decode($client->getCrawler()->filter('[data-saved-values]')->attr('data-saved-values') ?? '', true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($savedValues);
        self::assertSame($savedName, $savedValues[0]);
    }

    public function testCampaignPreviewUsesUnsavedContentAndSavedOrTwigTemplatesWithoutWrites(): void
    {
        $client = $this->loginUser();
        $template = $this->template();
        $audience = $this->audience();
        $audienceId = $audience->id;
        $campaignCount = $this->entityManager()->getRepository(Campaign::class)->count([]);
        $contactCount = $this->entityManager()->getRepository(Contact::class)->count([]);
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');
        $token = $crawler->filter('[data-preview-token]')->attr('data-preview-token');
        self::assertSelectorExists('[data-preview-kind="campaign"][data-viewport="mobile"] iframe[sandbox=""]');
        $data = [
            'audience' => (string) $audienceId,
            'emailLayout' => 'saved:'.$template->id,
            'subject' => 'Hello %name%',
            'preheader' => 'Your preview line',
            'bodyMarkdown' => "Latest **unsaved content** for %name%.\n\n![Trip](1.jpg)",
        ];
        $client->request(Request::METHOD_POST, '/admin/newsletter/campaign-preview', ['_preview_token' => $token, 'Campaign' => $data]);
        self::assertResponseIsSuccessful();
        $html = $this->previewHtml($client);
        self::assertStringContainsString('<title>Hello Alex</title>', $html);
        self::assertStringContainsString('Your preview line', $html);
        self::assertStringContainsString('<strong>unsaved content</strong> for Alex.', $html);
        self::assertStringContainsString('src="https://localhost.dev/media/default/1.jpg" alt="Trip"', $html);
        self::assertStringContainsString('Saved Pushword', $html);
        self::assertStringContainsString('Saved signature', $html);
        self::assertStringContainsString('12 Baker Street', $html);
        self::assertStringNotContainsString('Audience header', $html);

        $data['emailLayout'] = 'twig:pushword_bulletin';
        $data['transactional'] = '1';
        $client->request(Request::METHOD_POST, '/admin/newsletter/campaign-preview', ['_preview_token' => $token, 'Campaign' => $data]);
        self::assertResponseIsSuccessful();
        $html = $this->previewHtml($client);
        self::assertStringContainsString('background-color:#243328', $html);
        self::assertStringNotContainsString('Saved signature', $html);
        self::assertStringNotContainsString('#unsubscribe', $html);

        $data['emailLayout'] = 'twig:unregistered';
        $client->request(Request::METHOD_POST, '/admin/newsletter/campaign-preview', ['_preview_token' => $token, 'Campaign' => $data]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data['audience'] = '';
        $client->request(Request::METHOD_POST, '/admin/newsletter/campaign-preview', ['_preview_token' => $token, 'Campaign' => $data]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame($campaignCount, $this->entityManager()->getRepository(Campaign::class)->count([]));
        self::assertSame($contactCount, $this->entityManager()->getRepository(Contact::class)->count([]));
        $stored = $this->entityManager()->find(Audience::class, $audienceId);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame('simple', $stored->emailLayout);
        self::assertSame('Audience header', $stored->headerMarkdown);
        self::assertEmailCount(0);
    }

    public function testASavedTemplateCanBePreviewedAndSavedAsTheAudienceDefault(): void
    {
        $client = $this->loginUser();
        $template = $this->template();
        $audience = $this->audience();
        $id = $audience->id;
        $url = '/admin/newsletter/audience/'.$id.'/email-design';
        $crawler = $client->request(Request::METHOD_GET, $url);
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[emailLayout]'] = 'saved:'.$template->id;
        $values = $form->getPhpValues();
        $client->request(Request::METHOD_POST, $url, [...$values, 'preview' => '1']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Saved Pushword', $this->previewHtml($client));
        $client->request(Request::METHOD_POST, $url, $values);
        self::assertResponseRedirects($url);
        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame('saved:'.$template->id, $stored->emailLayout);
        $client->request(Request::METHOD_GET, $url);
        self::assertSelectorExists('[data-email-presets][open] input:checked');
    }

    public function testPersonalDesignPreviewsAndSavesTheSignatureAndCanBeOverriddenPerCampaign(): void
    {
        $client = $this->loginUser();
        $audience = $this->audience();
        $originalHeader = $audience->headerMarkdown;
        $originalFooter = $audience->footerMarkdown;
        $id = $audience->id;
        $url = '/admin/newsletter/audience/'.$id.'/email-design';
        $crawler = $client->request(Request::METHOD_GET, $url);
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[emailLayout]'] = 'personal';
        $form['email_design[signatureMarkdown]'] = '**Robin** [Contact](/contact)';
        $form['email_design[sampleBody]'] = 'Hello %name%.';
        $values = $form->getPhpValues();
        $client->request(Request::METHOD_POST, $url, $values + ['preview' => '1']);
        self::assertResponseIsSuccessful();
        $html = $this->previewHtml($client);
        self::assertStringContainsString('Hello Alex.', $html);
        self::assertStringContainsString('<strong>Robin</strong>', $html);
        self::assertStringNotContainsString('<table', $html);
        self::assertStringContainsString('#unsubscribe', $html);
        self::assertStringNotContainsString('©', $html);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame('simple', $stored->emailLayout);
        self::assertSame('', $stored->signatureMarkdown);

        $client->request(Request::METHOD_POST, $url, $values);
        self::assertResponseRedirects($url);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame('personal', $stored->emailLayout);
        self::assertSame('**Robin** [Contact](/contact)', $stored->signatureMarkdown);
        self::assertSame($originalFooter, $stored->footerMarkdown);
        self::assertFalse($stored->transactional);
        $crawler = $client->request(Request::METHOD_GET, $url);
        self::assertSelectorExists('[data-email-editable][hidden]');
        self::assertSelectorExists('[data-email-personal][open]');
        self::assertSelectorNotExists('[data-email-presets][open]');
        $saved = json_decode($crawler->filter('[data-saved-values]')->attr('data-saved-values') ?? '', true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($saved);
        self::assertCount(4, $saved);

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');
        self::assertSelectorExists('select[name="Campaign[emailLayout]"] option[value="personal"]');
        $token = $crawler->filter('[data-preview-token]')->attr('data-preview-token');
        foreach (['', 'simple', 'personal'] as $layout) {
            $client->request(Request::METHOD_POST, '/admin/newsletter/campaign-preview', [
                '_preview_token' => $token,
                'Campaign' => ['audience' => (string) $id, 'emailLayout' => $layout, 'subject' => 'Subject', 'bodyMarkdown' => 'Campaign body'],
            ]);
            self::assertResponseIsSuccessful();
            $html = $this->previewHtml($client);
            self::assertSame('simple' !== $layout, str_contains($html, '<strong>Robin</strong>'));
            self::assertStringContainsString('#unsubscribe', $html);
        }

        $crawler = $client->request(Request::METHOD_GET, $url);
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[emailLayout]'] = 'simple';
        $client->submit($form);
        self::assertResponseRedirects($url);
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame('simple', $stored->emailLayout);
        self::assertFalse($stored->emailPersonal);
        self::assertSame('**Robin** [Contact](/contact)', $stored->signatureMarkdown);
        self::assertSame($originalHeader, $stored->headerMarkdown);
        self::assertSame($originalFooter, $stored->footerMarkdown);
        self::assertEmailCount(0);
    }

    public function testAPersonalSignatureCanBeSavedWithoutChangingTheNewsletterDesign(): void
    {
        $client = $this->loginUser();
        $audience = $this->audience();
        $id = $audience->id;
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/audience/'.$id.'/email-design');
        self::assertSelectorExists('[data-email-personal]:not([open]) textarea[name="email_design[signatureMarkdown]"]');
        $form = $crawler->filter('form[name="email_design"]')->form();
        $form['email_design[signatureMarkdown]'] = '**Robin**';
        $client->submit($form);
        self::assertResponseRedirects();
        $this->entityManager()->clear();
        $stored = $this->entityManager()->find(Audience::class, $id);
        self::assertInstanceOf(Audience::class, $stored);
        self::assertSame('simple', $stored->emailLayout);
        self::assertSame('**Robin**', $stored->signatureMarkdown);
        self::assertEmailCount(0);
    }

    public function testPreviewEndpointsRequireAnEditorAndAValidToken(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/admin/newsletter/template/site/pushword');
        self::assertResponseRedirects('/login');
        foreach (['campaign-preview', 'template-preview', 'automation-preview'] as $endpoint) {
            $client->request(Request::METHOD_POST, '/admin/newsletter/'.$endpoint);
            self::assertResponseRedirects('/login');
        }

        $client = $this->loginUser($client);
        foreach (['campaign-preview', 'template-preview', 'automation-preview'] as $endpoint) {
            $client->request(Request::METHOD_POST, '/admin/newsletter/'.$endpoint, ['_preview_token' => 'invalid']);
            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }
    }

    public function testACampaignKeepsItsSavedTemplateAndPreviewsLaterTemplateEdits(): void
    {
        $client = $this->loginUser();
        $template = $this->template();
        $audience = $this->audience();
        $layout = 'saved:'.$template->id;
        $subject = 'Saved design '.$template->name;
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/new');
        $form = $crawler->filter('form[name="Campaign"]')->form();
        $form['Campaign[subject]'] = $subject;
        $form['Campaign[audience]'] = (string) $audience->id;
        $form['Campaign[emailLayout]'] = $layout;
        $form['Campaign[bodyMarkdown]'] = 'Saved campaign content';
        $client->submit($form);
        self::assertResponseRedirects();
        $campaign = $this->entityManager()->getRepository(Campaign::class)->findOneBy(['subject' => $subject]);
        self::assertInstanceOf(Campaign::class, $campaign);
        self::assertSame($layout, $campaign->emailLayout);
        $campaignId = $campaign->id;

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/template/'.$template->id.'/edit');
        $form = $crawler->filter('form[name="EmailTemplate"]')->form();
        $form['EmailTemplate[headerMarkdown]'] = '## Updated reusable header';
        $client->submit($form);
        self::assertResponseRedirects();
        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/campaign/'.$campaignId.'/edit');
        self::assertCount(1, $crawler->filter('option[value="'.$layout.'"][selected]'));
        $values = $crawler->filter('form[name="Campaign"]')->form()->getPhpValues();
        $values['_preview_token'] = $crawler->filter('[data-preview-token]')->attr('data-preview-token');
        $client->request(Request::METHOD_POST, '/admin/newsletter/campaign-preview', $values);
        self::assertResponseIsSuccessful();
        $html = $this->previewHtml($client);
        self::assertStringContainsString('Updated reusable header', $html);
        self::assertStringContainsString('Saved campaign content', $html);
    }

    private function templateName(): string
    {
        $name = 'ui-template-'.bin2hex(random_bytes(6));
        $this->names[] = $name;

        return $name;
    }

    private function template(): EmailTemplate
    {
        $template = new EmailTemplate();
        $template->name = $this->templateName();
        $template->headerMarkdown = '## Saved %siteName%';
        $template->footerMarkdown = 'Saved signature';
        $this->entityManager()->persist($template);
        $this->entityManager()->flush();

        return $template;
    }

    private function audience(): Audience
    {
        $audience = new Audience();
        $audience->slug = 'template-preview-'.bin2hex(random_bytes(6));
        $audience->name = 'Template preview audience';
        $audience->mainHost = 'localhost.dev';
        $audience->fromEmail = 'news@example.tld';
        $audience->headerMarkdown = 'Audience header';
        $audience->postalAddress = '12 Baker Street';
        $this->entityManager()->persist($audience);
        $this->entityManager()->flush();
        self::assertIsInt($audience->id);
        $this->audienceIds[] = $audience->id;

        return $audience;
    }

    private function previewHtml(KernelBrowser $client): string
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsString($data['html']);

        return $data['html'];
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine.orm.default_entity_manager');
    }
}
