<?php

namespace Pushword\Core\Tests\Service;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Utils\StringToDQLCriteria;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The deprecated entry point still renders a search into the array form.
 *
 * What each search *means* is covered by
 * {@see \Pushword\Core\Tests\Repository\PageSearchCorpusTest}; what is checked
 * here is only that a caller still holding an array keeps getting one, and that
 * feeding it back to the repository produces the query it always did.
 */
#[Group('integration')]
final class StringToDQLCriteriaTest extends KernelTestCase
{
    public function testItRendersTheArrayForm(): void
    {
        self::assertSame(
            [['mainContent', 'LIKE', '%<!--blog-->%']],
            new StringToDQLCriteria('comment:blog', null)->retrieve(),
        );

        // A term names a registry field and its operator; the pattern a tag needs
        // is built when the field is compiled, not here.
        self::assertSame(
            [['slug', 'LIKE', 'blog'], 'OR', ['tag', 'has', 'a']],
            new StringToDQLCriteria('slug:blog OR a', null)->retrieve(),
        );

        self::assertSame(
            [['prop.productCode', '=', 'NLDLV0019']],
            new StringToDQLCriteria('customProperty:productCode:NLDLV0019', null)->retrieve(),
        );

        // Malformed (no value colon) falls through to a tag search.
        self::assertSame(
            [['tag', 'has', 'customProperty:keyonly']],
            new StringToDQLCriteria('customProperty:keyonly', null)->retrieve(),
        );

        self::assertSame(
            [['prop.type', '=', 'blog'], 'OR', ['tag', 'has', 'travel']],
            new StringToDQLCriteria('customProperty:type:blog OR travel', null)->retrieve(),
        );

        self::assertSame(
            [['tag', 'has', 'blog'], 'AND', ['tag', 'has', 'europe'], 'AND', ['tag', 'has', 'hiking']],
            new StringToDQLCriteria('blog AND europe AND hiking', null)->retrieve(),
        );
    }

    public function testTheArrayFormStillCompiles(): void
    {
        self::bootKernel();
        /** @var EntityManager $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $entityManager->getRepository(Page::class);

        $where = new StringToDQLCriteria('related:comment:blog OR related:comment:story', null)->retrieve();
        $query = $pageRepo->getPublishedPageQueryBuilder(where: $where)->getQuery();
        $sql = $query->getSQL();
        self::assertIsString($sql);
        self::assertStringContainsString('((p0_.main_content LIKE ? AND p0_.id < ?) OR (p0_.main_content LIKE ? AND p0_.id < ?))', $sql);

        $parameterFound = false;
        foreach ($query->getParameters() as $parameter) {
            if ('%<!--blog-->%' === $parameter->getValue()) {
                $parameterFound = true;
            }
        }

        self::assertTrue($parameterFound);

        // A custom property is read as a JSON member, not matched as a substring
        // of the serialised column.
        $where = new StringToDQLCriteria('customProperty:productCode:NLDLV0019', null)->retrieve();
        $sql = $pageRepo->getPublishedPageQueryBuilder(where: $where)->getQuery()->getSQL();
        self::assertIsString($sql);
        if ($entityManager->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertStringContainsString('JSON_EXTRACT_PATH_TEXT(p0_.custom_properties', $sql);
        } else {
            self::assertStringContainsString("JSON_EXTRACT(p0_.custom_properties, '$.productCode')", $sql);
        }

        $where = new StringToDQLCriteria('blog AND europe AND hiking', null)->retrieve();
        $sql = $pageRepo->getPublishedPageQueryBuilder(where: $where)->getQuery()->getSQL();
        self::assertIsString($sql);
        self::assertStringContainsString(
            $entityManager->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform
                ? "CAST(p0_.tags AS TEXT) LIKE ? ESCAPE '!'"
                : "p0_.tags LIKE ? ESCAPE '!'",
            $sql,
        );
    }
}
