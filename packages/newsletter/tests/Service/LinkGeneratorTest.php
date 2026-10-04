<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Service\LinkGenerator;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;

#[Group('integration')]
final class LinkGeneratorTest extends AbstractNewsletterTestCase
{
    public function testWithoutAnExplicitSystemBaseTheLiveUrlIsPreserved(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        $sites = self::getContainer()->get(SiteRegistry::class);
        $sites->get('localhost.dev')->setCustomProperty('base_live_url', 'https://central.example.test/');
        $sites->get('admin-block-editor.test')->setCustomProperty('base_live_url', 'https://other-backend.example.test/');
        $links = self::getContainer()->get(LinkGenerator::class);

        self::assertSame('https://central.example.test', $links->base($audience));
        self::assertSame('https://central.example.test/newsletter/unsubscribe/'.$contact->token, $links->unsubscribeUrl($contact));
        self::assertSame('https://central.example.test/newsletter/confirm/'.$contact->token, $links->confirmUrl($contact));
        self::assertSame(
            'https://other-backend.example.test/newsletter/unsubscribe/'.$contact->token,
            $links->unsubscribeUrl($contact, 'admin-block-editor.test')
        );
    }

    public function testAnExplicitSystemBaseOverridesTheLiveUrlAndKeepsItsPathPrefix(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        self::getContainer()->get(SiteRegistry::class)->get('localhost.dev')
            ->setCustomProperty('base_live_url', 'https://central.example.test');
        $links = self::getContainer()->get(LinkGenerator::class);

        self::assertSame(
            'https://brand.example.test/mail/newsletter/unsubscribe/'.$contact->token,
            $links->unsubscribeUrl($contact, systemLinkBaseUrl: 'https://brand.example.test/mail/')
        );
    }
}
