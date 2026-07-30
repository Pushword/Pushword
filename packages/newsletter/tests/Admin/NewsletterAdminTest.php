<?php

namespace Pushword\Newsletter\Tests\Admin;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\ContentTrigger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class NewsletterAdminTest extends AbstractAdminTestClass
{
    private ?int $audienceId = null;

    protected function tearDown(): void
    {
        if (null !== $this->audienceId && null !== $this->client) {
            $connection = $this->entityManager()->getConnection();
            foreach ([
                'DELETE FROM newsletter_content_trigger WHERE audience_id = :id',
                'DELETE FROM newsletter_campaign WHERE audience_id = :id',
                'DELETE FROM newsletter_contact WHERE audience_id = :id',
                'DELETE FROM newsletter_audience WHERE id = :id',
            ] as $sql) {
                $connection->executeStatement($sql, ['id' => $this->audienceId]);
            }
        }

        parent::tearDown();
    }

    public function testEveryIndexRenders(): void
    {
        $client = $this->loginUser();
        $this->seed();

        foreach (['audience', 'contact', 'campaign', 'automation', 'content-trigger'] as $section) {
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
        self::assertSame([['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek']], $campaign->getSegment());
    }

    /** Same guarantee for the page grammar: a bad rule is a form error, never a 500. */
    public function testAMalformedPageRuleIsAFormError(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/content-trigger/new');
        $form = $crawler->filter('form[name="ContentTrigger"]')->form();
        $form['ContentTrigger[name]'] = 'Broken rule';
        $form['ContentTrigger[audience]'] = (string) $audience->id;
        $form['ContentTrigger[subjectTemplate]'] = 'New article: {{ page.h1 }}';
        // A contact field: `tag` reads the same on both sides, `confirmedAt` belongs to one.
        $form['ContentTrigger[pageWhenAsJson]'] = '[{"field":"confirmedAt","op":"olderThan","value":"7d"}]';
        $client->submit($form);

        self::assertSame(422, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('unknown field', (string) $client->getResponse()->getContent());
    }

    /** A rule you cannot count is one you will not switch on. */
    public function testTheTriggerPreviewReportsBothSidesOfTheRule(): void
    {
        $client = $this->loginUser();
        $audience = $this->seed();

        $trigger = new ContentTrigger()
            ->setAudience($audience)
            ->setName('Preview me')
            ->setHosts(['localhost.dev'])
            ->setPageWhen([['field' => 'slug', 'op' => 'startsWith', 'value' => 'nothing-matches-this/']])
            ->setSubjectTemplate('New article: {{ page.h1 }}');
        $this->entityManager()->persist($trigger);
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, '/admin/newsletter/content-trigger/'.$trigger->id.'/preview-pages');
        $client->followRedirect();

        self::assertStringContainsString('0 page(s) waiting', (string) $client->getResponse()->getContent());
    }

    /** Contacts are only ever created by an opt-in, never by hand in the admin. */
    public function testContactsCannotBeCreatedByHand(): void
    {
        $client = $this->loginUser();
        $this->seed();

        $client->request(Request::METHOD_GET, '/admin/newsletter/contact/new');

        self::assertNotSame(200, $client->getResponse()->getStatusCode());
    }

    private function seed(): Audience
    {
        $entityManager = $this->entityManager();

        if (null !== $this->audienceId) {
            $audience = $entityManager->getRepository(Audience::class)->find($this->audienceId);
            self::assertInstanceOf(Audience::class, $audience);

            return $audience;
        }

        $audience = new Audience()
            ->setSlug('admin-'.bin2hex(random_bytes(5)))
            ->setName('Admin test')
            ->setMainHost('localhost.dev')
            ->setFromEmail('newsletter@localhost.dev')
            ->setInterests(['AmTrek']);

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
