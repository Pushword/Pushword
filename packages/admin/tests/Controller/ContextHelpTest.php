<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class ContextHelpTest extends AbstractAdminTestClass
{
    public function testPageReferenceHelpDoesNotClassifyPublicationOrSlugControls(): void
    {
        $crawler = $this->loginUser()->request(Request::METHOD_GET, '/admin/page/2/edit');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-pw-help-summary="Characters:"] [name="Page[title]"]'));
        self::assertCount(1, $crawler->filter('[data-pw-help] #titleWidth'));
        self::assertCount(0, $crawler->filter('[data-pw-help] #pw-draft-toggle-switch'));
        self::assertCount(0, $crawler->filter('[data-pw-help] #disabledLinkSlug'));
        self::assertCount(1, $crawler->filter('#pw-draft-toggle-switch'));
        self::assertCount(1, $crawler->filter('script[src*="contextHelp.js"]'));
    }
}
