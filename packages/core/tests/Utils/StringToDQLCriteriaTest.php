<?php

namespace Pushword\Core\Tests\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Parameter;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Utils\StringToDQLCriteria;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class StringToDQLCriteriaTest extends KernelTestCase
{
    public function testIt(): void
    {
        self::assertSame([['mainContent', 'LIKE', '%<!--blog-->%']], new StringToDQLCriteria('comment:blog', null)->retrieve());
        self::assertSame([['slug', 'LIKE', 'blog'], 'OR', ['tags', 'LIKE', '%"a"%']], new StringToDQLCriteria('slug:blog OR a', null)->retrieve());

        self::bootKernel();
        /** @var EntityManager */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $pageRepo = $em->getRepository(Page::class);

        $where = new StringToDQLCriteria('related:comment:blog OR related:comment:story', null)->retrieve();
        $query = $pageRepo->getPublishedPageQueryBuilder(where: $where)->getQuery();
        $sql = $query->getSQL();
        self::assertIsString($sql);
        self::assertStringContainsString('((p0_.main_content LIKE ? AND p0_.id < ?) OR (p0_.main_content LIKE ? AND p0_.id < ?))', $sql);

        /** @var Parameter $parameter */
        foreach ($query->getParameters() as $parameter) {
            if ('%<!--blog-->%' === $parameter->getValue()) {
                $parameterFound = true;
            }
        }

        self::assertTrue($parameterFound ?? false);

        // Test AND operator (was throwing "malformated where params" exception)
        $where = new StringToDQLCriteria('blog AND europe AND hiking', null)->retrieve();
        self::assertSame([['tags', 'LIKE', '%"blog"%'], 'AND', ['tags', 'LIKE', '%"europe"%'], 'AND', ['tags', 'LIKE', '%"hiking"%']], $where);
        $query = $pageRepo->getPublishedPageQueryBuilder(where: $where)->getQuery();
        $sql = $query->getSQL();
        self::assertIsString($sql);
        self::assertStringContainsString('p0_.tags LIKE ?', $sql);
    }
}
