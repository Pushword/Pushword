<?php

declare(strict_types=1);

namespace Pushword\Version\Tests;

use Pushword\Core\Repository\Repository;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VersionTest extends KernelTestCase
{
    public function testIt(): void
    {
        self::bootKernel();
        $pageClass = 'App\Entity\Page';

        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');

        $repo = Repository::getPageRepository($em, $pageClass);

        $page = $repo->findOneBy(['id' => 1]);

        $page->setH1('edited title to test versionning');
        $em->flush();
        $page->setH1('edited title to test versionning the second time');
        $em->flush();

        $versionner = new Versionner(
            self::$kernel->getLogDir(),
            $pageClass,
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            self::$kernel->getContainer()->get('serializer')
        );

        $pageVersions = $versionner->getPageVersions($page);

        $this->assertTrue(\count($pageVersions) >= 1);
    }
}
