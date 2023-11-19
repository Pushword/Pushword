<?php

namespace Pushword\Core\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Parameter;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Twig\StringToSearch;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AppExtensionTest extends KernelTestCase
{
    public function testStringToSearch()
    {
        $this->assertSame([['mainContent', 'LIKE', '%<!--blog-->%']], (new StringToSearch('comment:blog', null))->retrieve());
        $this->assertSame(
            [['slug', 'LIKE', 'blog'], 'OR', ['mainContent', 'LIKE', '%a%']],
            (new StringToSearch('slug:blog OR a', null))->retrieve()
        );

        self::bootKernel();
        /** @var EntityManagerInterface */
        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        /** @var PageRepository */
        $pageRepo = $em->getRepository('App\Entity\Page');

        $where = (new StringToSearch('related:comment:blog OR related:comment:story', null))->retrieve();
        $query = $pageRepo->getPublishedPageQueryBuilder(where: $where)->getQuery();
        $this->assertStringContainsString('((p0_.main_content LIKE ? AND p0_.id < ?) OR (p0_.main_content LIKE ? AND p0_.id < ?))', $query->getSQL());

        /** @var Parameter $parameter */
        foreach ($query->getParameters() as $parameter) {
            if ('%<!--blog-->%' === $parameter->getValue()) {
                $parameterFound = true;
            }
        }
        $this->assertTrue($parameterFound ?? false);
    }
}
