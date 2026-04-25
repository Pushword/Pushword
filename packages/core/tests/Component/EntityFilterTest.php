<?php

namespace Pushword\Core\Tests\Component;

use DateTime;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Component\EntityFilter\Filter\HtmlObfuscateLink;
use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\ContentExtension;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[Group('integration')]
final class EntityFilterTest extends KernelTestCase
{
    public function testIt(): void
    {
        $page = $this->getPage();
        $manager = $this->getManagerPool()->getManager($page);

        self::assertSame($page->getH1(), $manager->title()); // @phpstan-ignore-line
        self::assertSame($page->getH1(), $manager->getTitle()); // @phpstan-ignore-line

        $splitContent = $this->getContentExtension()->mainContentSplit($page);
        self::assertSame('', $splitContent->getChapeau());
        self::assertSame('<p>', substr(trim($splitContent->getBody()), 0, 3));
    }

    public function testObfuscateLink(): void
    {
        $apps = self::getContainer()->get(SiteRegistry::class);
        $twig = self::getContainer()->get('twig');
        $router = self::getContainer()->get(PushwordRouteGenerator::class);
        $security = self::getContainer()->get(Security::class);
        $linkProvider = new LinkProvider($router, $apps, $twig, $security);

        $filter = new HtmlObfuscateLink($linkProvider);

        self::assertSame('Lorem <span data-rot="_cvrqjro.pbz/">Test</span> ipsum', $filter->convertHtmlRelObfuscateLink('Lorem <a href="https://piedweb.com/" rel="obfuscate">Test</a> ipsum'));
        self::assertSame('Lorem <span class="link-btn" data-rot="_cvrqjro.pbz/">Test</span> ipsum', $filter->convertHtmlRelObfuscateLink('Lorem <a class="link-btn" href="https://piedweb.com/" rel="obfuscate">Test</a> ipsum'));
        self::assertSame('Lorem <span class="link-btn btn-plus" data-rot="_cvrqjro.pbz/">Test</span> ipsum', $filter->convertHtmlRelObfuscateLink('Lorem <a class="link-btn btn-plus" href="https://piedweb.com/" rel="obfuscate">Test</a> ipsum'));
        self::assertSame('Lorem <span class="link-btn btn-plus" data-rot="&">Test</span> ipsum', $filter->convertHtmlRelObfuscateLink('Lorem <a class="link-btn btn-plus" href="&" rel="obfuscate">Test</a> ipsum'));

        self::assertStringNotContainsString(';', $filter->convertHtmlRelObfuscateLink('Lorem <a class="link-btn btn-plus" href="https://example.tld/?test1=abvc&test=2158" rel="obfuscate">Test</a> ipsum'));

        self::assertSame('Lorem <a href="/a1" class="ninja">Test</a> <span data-rot="_cvrqjro.pbz/">Anchor 2</span>', $filter->convertHtmlRelObfuscateLink('Lorem <a href="/a1" class="ninja">Test</a> <a href="https://piedweb.com/" rel="obfuscate">Anchor 2</a>'));
    }

    private function getManagerPool(): ManagerPool
    {
        self::bootKernel();

        return self::getContainer()->get(ManagerPool::class);
    }

    private function getContentExtension(): ContentExtension
    {
        self::bootKernel();

        return self::getContainer()->get(ContentExtension::class);
    }

    public function testToc(): void
    {
        $page = $this->getPage($this->getContentReadyForToc());

        $splitContent = $this->getContentExtension()->mainContentSplit($page);

        self::assertSame('<p>my intro...</p>', trim($splitContent->getIntro()));
        $toCheck = '<h2 id="first-title">First Title</h2>';
        self::assertSame($toCheck, substr(trim($splitContent->getContent()), 0, \strlen($toCheck)));
    }

    public function testTocWithBlockId(): void
    {
        $content = "my intro...\n\n{#example-1}\n## First Title\n\nfirst paragraph\n\n{#section-2-details}\n## Second Title\n\nsecond paragraph";

        $page = $this->getPage($content);
        $splitContent = $this->getContentExtension()->mainContentSplit($page);

        $body = $splitContent->getContent();
        self::assertStringContainsString('id="example-1"', $body);
        self::assertStringContainsString('id="section-2-details"', $body);
        self::assertStringNotContainsString('id="first-title"', $body);
        self::assertStringNotContainsString('id="second-title"', $body);

        $toc = $splitContent->getToc();
        self::assertIsString($toc);
        self::assertStringContainsString('#example-1', $toc);
        self::assertStringContainsString('#section-2-details', $toc);
    }

    public function testTocWithInlineId(): void
    {
        $content = "my intro...\n\n## First Title {#example-1}\n\nfirst paragraph\n\n## Second Title {#section-2}\n\nsecond paragraph";

        $page = $this->getPage($content);
        $splitContent = $this->getContentExtension()->mainContentSplit($page);

        $body = $splitContent->getContent();
        self::assertStringContainsString('id="example-1"', $body);
        self::assertStringContainsString('id="section-2"', $body);
        self::assertStringNotContainsString('id="first-title"', $body);
        self::assertStringNotContainsString('id="second-title"', $body);

        $toc = $splitContent->getToc();
        self::assertIsString($toc);
        self::assertStringContainsString('#example-1', $toc);
        self::assertStringContainsString('#section-2', $toc);
    }

    public function testMarkdownAnchorNotInterpretedAsTwigComment(): void
    {
        $content = "{#difficulty}\n## Physical Difficulty\n\nSome text\n\n{#reservation}\n## Reservation\n\nMore text";

        $page = $this->getPage($content);
        $splitContent = $this->getContentExtension()->mainContentSplit($page);

        $body = $splitContent->getContent();
        self::assertStringContainsString('id="difficulty"', $body);
        self::assertStringContainsString('id="reservation"', $body);
    }

    public function testInlineHeadingAttributeDoesNotMatchAcrossLines(): void
    {
        // Regression: \s* in regex matched newlines, causing {#nextblock} on a separate line
        // to be incorrectly merged with the preceding heading
        $content = "### Heading\n\n{#nextblock}\n## Next Section\n\ntext";

        $page = $this->getPage($content);
        $body = $this->getContentExtension()->mainContentSplit($page)->getContent();

        self::assertStringContainsString('id="nextblock"', $body);
        self::assertStringContainsString('<h3', $body);
        self::assertStringContainsString('Heading</h3>', $body);
        self::assertStringNotContainsString('id="nextblock">Heading</h3>', $body, 'The {#nextblock} attribute must not leak onto the preceding heading');
        self::assertStringContainsString('<h2', $body);
    }

    public function testTwigInHeadingNotAffectedByInlineIdSupport(): void
    {
        $page = $this->getPage("## Title with {{ \"twig\" }}\n\nparagraph");
        $body = $this->getContentExtension()->mainContentSplit($page)->getContent();

        self::assertStringContainsString('twig', $body);
        self::assertStringContainsString('<h2', $body);
    }

    private function getPage(?string $content = null): Page
    {
        $page = new Page();
        $page->setH1('Demo Page - Kitchen Sink  Markdown + Twig');
        $page->setSlug('kitchen-sink');
        $page->locale = 'en';
        $page->createdAt = new DateTime('1 day ago');
        $page->updatedAt = new DateTime('1 day ago');
        $page->setMainContent($content ?? file_get_contents(__DIR__.'/../../../skeleton/src/DataFixtures/WelcomePage.md'));
        $page->setCustomProperty('toc', true);

        return $page;
    }

    public function testDateShortcode(): void
    {
        $dateFilter = self::getContainer()->get(Date::class);

        $result = $dateFilter->convertDateShortCode('Copyright date(Y)', 'en');
        self::assertStringContainsString(date('Y'), $result);
        self::assertStringNotContainsString('date(Y)', $result);

        $result = $dateFilter->convertDateShortCode('No shortcode here', 'en');
        self::assertSame('No shortcode here', $result);

        $result = $dateFilter->convertDateShortCode('Season date(S) and date(W)', 'fr');
        self::assertStringNotContainsString('date(S)', $result);
        self::assertStringNotContainsString('date(W)', $result);

        $result = $dateFilter->convertDateShortCode('Month date(M)', 'fr');
        self::assertStringNotContainsString('date(M)', $result);
    }

    public function testColspanInPageContent(): void
    {
        $content = "## Services\n\n| Service | Identifiant | -> |\n|---|---|---|\n| Auth | auth.service | auth.provider |";

        $page = $this->getPage($content);
        $body = $this->getContentExtension()->mainContentSplit($page)->getContent();

        self::assertStringContainsString('<th colspan="2">Identifiant</th>', $body);
        self::assertStringNotContainsString('-&gt;', $body);
        self::assertStringContainsString('<td>Auth</td>', $body);
    }

    private function getContentReadyForToc(): string
    {
        return "my intro...\n## First Title\nfirst paragraph\n## Second Title\nsecond paragraph";
    }
}
