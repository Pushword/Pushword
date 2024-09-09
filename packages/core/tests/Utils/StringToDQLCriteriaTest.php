<?php

namespace Pushword\Core\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Parameter;
use Pushword\Core\Entity\Page;
use Pushword\Core\Utils\StringToDQLCriteria;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StringToDQLCriteriaTest extends KernelTestCase
{
    public function testIt(): void
    {
        self::assertSame([['mainContent', 'LIKE', '%<!--blog-->%']], (new StringToDQLCriteria('comment:blog', null))->retrieve());
        self::assertSame([['slug', 'LIKE', 'blog'], 'OR', ['tags', 'LIKE', '%"a"%']], (new StringToDQLCriteria('slug:blog OR a', null))->retrieve());

        self::bootKernel();
        /** @var EntityManagerInterface */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $pageRepo = $em->getRepository(Page::class);

        $where = (new StringToDQLCriteria('related:comment:blog OR related:comment:story', null))->retrieve();
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
    }
}
