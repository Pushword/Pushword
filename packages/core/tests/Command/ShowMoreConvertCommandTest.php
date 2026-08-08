<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class ShowMoreConvertCommandTest extends KernelTestCase
{
    /** @var string[] */
    private array $createdSlugs = [];

    /** The command reads every page of the host, so a leftover skews the next count. */
    protected function tearDown(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pageRepo = self::getContainer()->get(PageRepository::class);

        foreach ($this->createdSlugs as $slug) {
            $page = $pageRepo->findOneBy(['slug' => $slug]);
            if (null !== $page) {
                $em->remove($page);
            }
        }

        $em->flush();
        $this->createdSlugs = [];

        parent::tearDown();
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('pw:show-more:convert'));
    }

    private function createPage(string $slug, string $mainContent): Page
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $page = new Page();
        $page->slug = $slug;
        $page->h1 = $slug;
        $page->host = 'localhost';
        $page->locale = 'en';
        $page->mainContent = $mainContent;

        $em->persist($page);
        $em->flush();
        $this->createdSlugs[] = $slug;

        return $page;
    }

    private function contentOf(string $slug): string
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $page = self::getContainer()->get(PageRepository::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);

        return $page->mainContent;
    }

    public function testItRewritesAPairAsTheTwigCall(): void
    {
        $commandTester = $this->tester();
        $this->createPage(
            'show-more-convert-pair',
            "Intro\n\n<!--start-show-more-->\n\nHidden\n\n<!--end-show-more-->\n\nRest"
        );

        $commandTester->execute(['--format' => 'text']);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertSame(
            "Intro\n\n{{ startShowMore() }}\n\nHidden\n\n{{ endShowMore() }}\n\nRest",
            $this->contentOf('show-more-convert-pair'),
        );
    }

    /** The editor sees a marker as a block only when blank lines set it apart. */
    public function testItPutsAGluedMarkerOnALineOfItsOwn(): void
    {
        $commandTester = $this->tester();
        $this->createPage(
            'show-more-convert-glued',
            "Intro\n<!--start-show-more-->\nHidden\n\n<!--end-show-more-->"
        );

        $commandTester->execute(['--format' => 'text']);

        self::assertSame(
            "Intro\n\n{{ startShowMore() }}\n\nHidden\n\n{{ endShowMore() }}",
            $this->contentOf('show-more-convert-glued'),
        );
    }

    public function testDryRunWritesNothingAndReportsWhatItWould(): void
    {
        $commandTester = $this->tester();
        $body = "<!--start-show-more-->\n\nHidden\n\n<!--end-show-more-->";
        $this->createPage('show-more-convert-dry', $body);

        $commandTester->execute(['--dry-run' => true, '--format' => 'text']);

        self::assertStringContainsString('dry-run', $commandTester->getDisplay());
        self::assertSame($body, $this->contentOf('show-more-convert-dry'));
    }

    /** A marker with no partner is content we do not understand: leave it. */
    public function testAnOrphanMarkerIsLeftAloneAndReported(): void
    {
        $commandTester = $this->tester();
        $body = "Intro\n\n<!--end-show-more-->\n\nRest";
        $this->createPage('show-more-convert-orphan', $body);

        $commandTester->execute(['--format' => 'text']);

        self::assertStringContainsString('nothing to pair', $commandTester->getDisplay());
        self::assertSame($body, $this->contentOf('show-more-convert-orphan'));
    }

    public function testMarkersInAFencedBlockAreNotConverted(): void
    {
        $commandTester = $this->tester();
        $body = "```markdown\n<!--start-show-more-->\n\nHidden\n\n<!--end-show-more-->\n```";
        $this->createPage('show-more-convert-fenced', $body);

        $commandTester->execute(['--format' => 'text']);

        self::assertSame($body, $this->contentOf('show-more-convert-fenced'));
    }

    /** The rewrite walks the same stack the filter does: inner pair first. */
    public function testNestedPairsAreBothRewritten(): void
    {
        $commandTester = $this->tester();
        $this->createPage(
            'show-more-convert-nested',
            "<!--start-show-more-->\n\nA\n\n<!--start-show-more-->\n\nB\n\n<!--end-show-more-->\n\n<!--end-show-more-->"
        );

        $commandTester->execute(['--format' => 'text']);

        self::assertSame(
            "{{ startShowMore() }}\n\nA\n\n{{ startShowMore() }}\n\nB\n\n{{ endShowMore() }}\n\n{{ endShowMore() }}",
            $this->contentOf('show-more-convert-nested'),
        );
    }

    /** A marker an author disabled with a Twig comment is not an orphan. */
    public function testADisabledMarkerIsNotCountedAsAnOrphan(): void
    {
        $commandTester = $this->tester();
        $this->createPage(
            'show-more-convert-disabled',
            "{# <!--start-show-more--> #}\n\nRest\n\n{# <!--end-show-more--> #}"
        );

        $commandTester->execute(['--host' => 'localhost', '--format' => 'agent']);

        $reported = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($reported);
        self::assertSame(0, $reported['orphanMarkers']);
    }

    public function testAgentFormatReportsWhatItDid(): void
    {
        $commandTester = $this->tester();
        $this->createPage(
            'show-more-convert-agent',
            "<!--start-show-more-->\n\nHidden\n\n<!--end-show-more-->"
        );

        $commandTester->execute(['--host' => 'localhost', '--format' => 'agent']);

        $reported = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($reported);
        self::assertSame('pw:show-more:convert', $reported['tool']);
        self::assertSame('done', $reported['result']);

        $slugs = $reported['slugs'];
        self::assertIsArray($slugs);
        self::assertContains('localhost/show-more-convert-agent', $slugs);
    }
}
