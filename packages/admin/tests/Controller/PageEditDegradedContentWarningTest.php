<?php

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Core\Service\EditorNotice\TwigErrorMarker;
use Pushword\Core\Service\Markdown\BrokenImageComment;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * A Twig error in editor content degrades to an invisible marker (the page
 * renders 200, no longer 500), so refreshPreview() can no longer rely on a
 * thrown exception. It must scan the rendered body and flash a warning listing
 * every Twig-error marker, so saving a broken page still tells the editor what
 * silently broke. Broken images are left to their own persistent banner.
 */
#[Group('integration')]
final class PageEditDegradedContentWarningTest extends KernelTestCase
{
    /**
     * @return string[] the 'warning' flashes raised for a page rendered to $html
     */
    private function warningsFor(string $html): array
    {
        self::bootKernel();

        $controller = self::getContainer()->get(PageCrudController::class);

        $flashBag = new FlashBag();
        new ReflectionMethod($controller, 'warnAboutDegradedContent')->invoke($controller, $flashBag, $html);

        return array_values(array_filter($flashBag->peek('warning'), is_string(...)));
    }

    public function testFlashesAWarningForATwigErrorMarker(): void
    {
        $warnings = $this->warningsFor('<main>'.TwigErrorMarker::for('Unknown "foo" function.').'</main>');

        self::assertCount(1, $warnings);
        self::assertStringContainsString('<code>', $warnings[0]);
        self::assertStringContainsString('foo', $warnings[0]);
    }

    public function testListsEveryTwigErrorInASingleWarning(): void
    {
        $warnings = $this->warningsFor(TwigErrorMarker::for('first boom').TwigErrorMarker::for('second boom'));

        self::assertCount(1, $warnings);
        self::assertStringContainsString('first boom', $warnings[0]);
        self::assertStringContainsString('second boom', $warnings[0]);
    }

    public function testEscapesHtmlInTheErrorMessage(): void
    {
        // Flashes render as raw HTML, so a message carrying markup an editor typed
        // must be escaped, never injected verbatim.
        $warnings = $this->warningsFor(TwigErrorMarker::for('<script>alert(1)</script>'));

        self::assertCount(1, $warnings);
        self::assertStringNotContainsString('<script>', $warnings[0]);
        self::assertStringContainsString('&lt;script&gt;', $warnings[0]);
    }

    public function testStaysSilentForABrokenImageOnly(): void
    {
        // Broken images are surfaced by the persistent broken-image banner, not
        // this flash — so the two never double-warn for the same issue.
        self::assertSame([], $this->warningsFor('<main>'.BrokenImageComment::for('gone.jpg').'</main>'));
    }

    public function testStaysSilentWhenTheRenderedPageHasNoMarker(): void
    {
        self::assertSame([], $this->warningsFor('<main><p>All good.</p></main>'));
    }
}
