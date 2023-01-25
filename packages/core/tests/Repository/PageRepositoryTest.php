<?php

namespace Pushword\Core\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PageRepositoryTest extends KernelTestCase
{
    public function testPageRepo()
    {
        self::bootKernel();

        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        $pages = $em->getRepository('App\Entity\Page')->getIndexablePagesQuery('', 'en', 2)
            ->getQuery()->getResult();

        $this->assertSame(2, \count($pages)); // depend on AppFixtures

        $pages = $em->getRepository('App\Entity\Page')->getPublishedPages(
            '',
            [['key' => 'slug', 'operator' => '=', 'value' => 'homepage']],
            ['key' => 'publishedAt', 'direction' => 'DESC'],
            1
        );

        $this->assertSame($pages[0]->getSlug(), 'homepage');
    }
}
