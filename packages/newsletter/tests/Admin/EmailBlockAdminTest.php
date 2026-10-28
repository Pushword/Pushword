<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Admin;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Repository\UserRepository;
use Pushword\Newsletter\Block\BlockContent;
use Pushword\Newsletter\Block\BlockMarkup;
use Pushword\Newsletter\Entity\EmailBlock;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Validation\CampaignPreflight;

#[Group('integration')]
final class EmailBlockAdminTest extends AbstractNewsletterTestCase
{
    public function testEmptyLibraryExplainsReuseAndOffersCreation(): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
        self::assertNotNull($user);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/admin/newsletter/block');
        self::assertResponseIsSuccessful();
        self::assertSame('Create your first reusable block', $crawler->filter('.pw-newsletter-empty h2')->text());
        self::assertStringContainsString('editable copy', $crawler->filter('.pw-newsletter-empty p')->text());
        self::assertCount(1, $crawler->filter('a[href$="/newsletter/block/new"]'));
    }

    public function testLibrarySearchCopyAndDeletionPreserveTheAuthoredCampaign(): void
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
        self::assertNotNull($user);
        $this->client->loginUser($user);
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.test', customProperties: ['firstName' => 'Alex']);
        $block = new EmailBlock();
        $block->name = 'Reusable test '.uniqid();
        $block->bodyMarkdown = 'Hello **%firstName%**.';

        $this->entityManager->persist($block);
        $this->entityManager->flush();

        $id = $block->id;
        $campaign = $this->createCampaign($audience);
        $campaign->bodyMarkdown = BlockMarkup::encode('library', new BlockContent((string) $id, $block->name, $block->bodyMarkdown."\n\nMy edit."));

        $this->entityManager->flush();
        $saved = $campaign->bodyMarkdown;

        $this->client->request('GET', '/admin/newsletter/block');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/newsletter/block-content', ['audience' => $audience->id, 'provider' => 'library', 'q' => $block->name]);
        self::assertResponseIsSuccessful();
        $result = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        self::assertIsArray($result['items']);
        self::assertCount(1, $result['items']);
        self::assertIsArray($result['items'][0]);
        self::assertSame((string) $id, $result['items'][0]['reference']);
        $this->client->request('POST', '/admin/newsletter/block-copy', ['name' => 'Unauthorized copy', 'markdown' => 'Body', '_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);

        $block = $this->entityManager->find(EmailBlock::class, $id);
        self::assertNotNull($block);
        $this->entityManager->remove($block);
        $this->entityManager->flush();

        $campaign = $this->entityManager->find($campaign::class, $campaign->id);
        self::assertNotNull($campaign);
        self::assertSame([], self::getContainer()->get(CampaignPreflight::class)->check($campaign));
        self::assertSame($saved, $campaign->bodyMarkdown);
        self::assertSame($saved, $campaign->duplicate()->bodyMarkdown);
        $html = self::getContainer()->get(MailRenderer::class)->html($audience, $contact, 'Subject', $saved, null, null, null);
        self::assertStringContainsString('Hello <strong>Alex</strong>', $html);
        self::assertStringContainsString('My edit.', $html);
        self::assertStringNotContainsString('newsletter-block', $html);
        self::assertStringContainsString('Hello **Alex**.', self::getContainer()->get(MailRenderer::class)->text($audience, $contact, $saved, null));
    }
}
