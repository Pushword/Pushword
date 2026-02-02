<?php

declare(strict_types=1);

namespace Pushword\Version\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

#[Group('integration')]
class VersionTest extends KernelTestCase
{
    public function testIt(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $repo = $em->getRepository(Page::class);

        // Find any page to test versioning
        $page = $repo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev'])
            ?? $repo->findOneBy(['slug' => 'homepage'])
            ?? $repo->findOneBy([]);
        self::assertNotNull($page, 'At least one page should exist');

        $page->setH1('edited title to test Versioning');

        $em->flush();
        $page->setH1('edited title to test Versioning the second time');
        $em->flush();

        $versionner = new Versionner(
            self::bootKernel()->getLogDir(),
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            new Serializer([], ['json' => new JsonEncoder()])
        );

        $pageVersions = $versionner->getPageVersions($page);

        self::assertGreaterThanOrEqual(1, \count($pageVersions));
    }
}
