<?php

namespace Pushword\Core\Tests\Validator;

use DateTime;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Validator\Constraints\PagePropertiesSchema;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Runs against the dev-app schema: root declares subtitle (Length max 120),
 * localhost.dev declares level (Choice beginner|advanced) and priority
 * (int, Positive); the core bundle declares toc/tocTitle/searchExcerpt.
 */
#[Group('integration')]
final class PagePropertiesSchemaValidatorTest extends KernelTestCase
{
    public function testValidAndUndeclaredValuesPass(): void
    {
        $page = $this->createPage();
        $page->setCustomProperty('level', 'beginner');
        $page->setCustomProperty('priority', 2);
        $page->setCustomProperty('subtitle', 'A short one');
        $page->setCustomProperty('toc', true);
        $page->setCustomProperty('someUndeclaredKey', ['anything' => 'goes']);

        self::assertSame([], $this->schemaViolations($page));
    }

    public function testTypeMismatchIsReportedAtTheTextareaPath(): void
    {
        $page = $this->createPage();
        $page->setCustomProperty('priority', 'high');

        $violations = $this->schemaViolations($page);
        self::assertCount(1, $violations);
        self::assertStringContainsString('priority', (string) $violations[0]->getMessage());
        self::assertStringContainsString('int', (string) $violations[0]->getMessage());
    }

    public function testDeclaredConstraintViolationNamesTheProperty(): void
    {
        $page = $this->createPage();
        $page->setCustomProperty('level', 'expert');

        $violations = $this->schemaViolations($page);
        self::assertCount(1, $violations);
        self::assertStringContainsString('level', (string) $violations[0]->getMessage());
    }

    public function testPendingTextareaYamlIsValidatedBeforeTheMerge(): void
    {
        // Class constraints run before the Assert\Callback that merges the
        // textarea; the validator must still see what the editor just typed.
        $page = $this->createPage();
        $page->setUnmanagedPropertiesFromYaml('priority: -3');

        $violations = $this->schemaViolations($page);
        self::assertCount(1, $violations);
        self::assertStringContainsString('priority', (string) $violations[0]->getMessage());
    }

    public function testDeletingAnInvalidValueThroughTheTextareaPasses(): void
    {
        $page = $this->createPage();
        $page->setCustomProperty('priority', -3);
        $page->setUnmanagedPropertiesFromYaml('');

        self::assertSame([], $this->schemaViolations($page));
    }

    public function testSchemaGroupValidatesTheSchemaAlone(): void
    {
        $page = new Page(); // no slug, no h1: unrelated constraints must stay out
        $page->host = 'localhost.dev';
        $page->setCustomProperty('level', 'expert');

        $violations = self::getContainer()->get(ValidatorInterface::class)
            ->validate($page, null, [PagePropertiesSchema::SCHEMA_GROUP]);

        self::assertCount(1, $violations);
        self::assertStringContainsString('level', (string) $violations->get(0)->getMessage());
    }

    private function createPage(): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setSlug('schema-validator-fixture-'.uniqid());
        $page->setH1('Schema fixture');
        $page->setMainContent('Hello');
        $page->setPublishedAt(new DateTime('-1 day'));

        return $page;
    }

    /** @return list<ConstraintViolationInterface> */
    private function schemaViolations(Page $page): array
    {
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($page);

        $schemaViolations = [];
        foreach ($violations as $violation) {
            if ('unmanagedPropertiesAsYaml' === $violation->getPropertyPath()) {
                $schemaViolations[] = $violation;
            }
        }

        return $schemaViolations;
    }
}
