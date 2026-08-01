<?php

namespace Pushword\Flat\Tests\Importer;

use DateTime;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\PageImporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The import must report schema findings without ever blocking: the page is
 * imported, the agent JSON carries the diagnosis.
 */
#[Group('integration')]
final class PageImporterSchemaReportTest extends KernelTestCase
{
    /** @var string[] */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $createdFile) {
            @unlink($createdFile);
        }
        parent::tearDown();
    }

    public function testInvalidAndUndeclaredAreReportedButThePageImports(): void
    {
        self::bootKernel();

        $file = $this->writeFixture('localhost.dev', $slug = 'schema-report-fixture-'.uniqid(), <<<'MD'
            ---
            h1: Schema report fixture
            locale: en
            priority: -3
            level: expert
            toc_titel: Sommaire
            ---
            Content.
            MD);

        $pageImporter = $this->importer();
        self::assertTrue($pageImporter->import($file, new DateTime()), 'the page imports despite the violations');

        $report = $pageImporter->getSchemaReport();

        $errors = $report['invalid'][$slug] ?? [];
        self::assertCount(2, $errors);
        $joined = implode(' | ', $errors);
        self::assertStringContainsString('priority', $joined);
        self::assertStringContainsString('level', $joined);

        self::assertSame(['toc_titel' => 1], $report['undeclared'], 'near-miss keys are tallied, declared and managed ones are not');
        self::assertSame([], $report['missing_required']);
    }

    public function testMissingRequiredIsTalliedWithoutInvalidating(): void
    {
        self::bootKernel();

        // admin-block-editor.test declares author as required.
        self::getContainer()->get(SiteRegistry::class)->switchSite('admin-block-editor.test');

        $file = $this->writeFixture('admin-block-editor.test', 'schema-report-required-'.uniqid(), <<<'MD'
            ---
            h1: Schema report required fixture
            locale: en
            ---
            Content.
            MD);

        $pageImporter = $this->importer();
        self::assertTrue($pageImporter->import($file, new DateTime()));

        $report = $pageImporter->getSchemaReport();
        self::assertSame([], $report['invalid'], 'a missing required key never invalidates the page');
        self::assertSame(['author' => 1], $report['missing_required']);
    }

    public function testValidPageProducesAnEmptyReport(): void
    {
        self::bootKernel();

        $file = $this->writeFixture('localhost.dev', 'schema-report-valid-'.uniqid(), <<<'MD'
            ---
            h1: Schema report valid fixture
            locale: en
            priority: 2
            level: beginner
            toc: true
            ---
            Content.
            MD);

        $pageImporter = $this->importer();
        self::assertTrue($pageImporter->import($file, new DateTime()));

        self::assertSame(['invalid' => [], 'undeclared' => [], 'missing_required' => []], $pageImporter->getSchemaReport());
    }

    private function writeFixture(string $host, string $slug, string $markdown): string
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $file = $contentDirFinder->get($host).'/'.$slug.'.md';
        $this->createdFiles[] = $file;
        file_put_contents($file, $markdown);

        return $file;
    }

    private function importer(): PageImporter
    {
        /** @var PageImporter $pageImporter */
        $pageImporter = self::getContainer()->get(PageImporter::class);
        $pageImporter->resetImport();

        return $pageImporter;
    }
}
