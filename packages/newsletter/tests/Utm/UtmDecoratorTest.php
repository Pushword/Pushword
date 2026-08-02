<?php

namespace Pushword\Newsletter\Tests\Utm;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Utm\UtmDecorator;
use Pushword\Newsletter\Utm\UtmTag;

#[Group('integration')]
final class UtmDecoratorTest extends AbstractNewsletterTestCase
{
    private const string LINK = '<p><a href="https://localhost.dev/article">Read</a></p>';

    public function testItTagsALinkToOneOfOurSites(): void
    {
        $html = $this->decorate(self::LINK);

        self::assertStringContainsString('utm_source=newsletter', $html);
        self::assertStringContainsString('utm_medium=email', $html);
        self::assertStringContainsString('utm_campaign=janvier', $html);
        self::assertStringNotContainsString('utm_content', $html);
    }

    /** Our campaign labels have no business on somebody else's domain. */
    public function testItLeavesAnExternalDomainAlone(): void
    {
        self::assertStringNotContainsString('utm_', $this->decorate('<p><a href="https://example.com/x">Out</a></p>'));
    }

    public function testItLeavesLinksThatReachNoAnalyticsAlone(): void
    {
        $html = $this->decorate(
            '<a href="mailto:hi@example.tld">Mail</a>'
            .'<a href="tel:+33100000000">Call</a>'
            .'<a href="#top">Top</a>'
            .'<a href="/relative">Rel</a>',
        );

        self::assertStringNotContainsString('utm_', $html);
    }

    public function testItKeepsAnExistingQueryAndFragment(): void
    {
        $html = $this->decorate('<p><a href="https://localhost.dev/a?p=1#part">L</a></p>');

        self::assertStringContainsString('p=1', $html);
        self::assertStringContainsString('utm_campaign=janvier#part', $html);
    }

    /** Markdown writes a two-parameter query as `&amp;`; it has to survive the round trip. */
    public function testItKeepsAnEncodedMultiParameterQuery(): void
    {
        $html = $this->decorate('<p><a href="https://localhost.dev/a?p=1&amp;q=2">L</a></p>');

        self::assertStringContainsString('p=1&amp;q=2&amp;utm_source=newsletter', $html);
        self::assertStringNotContainsString('&amp;amp;', $html);
    }

    public function testTheSourceIsNormalisedIntoTheParameter(): void
    {
        $audience = new Audience();
        $audience->mainHost = 'localhost.dev';
        $audience->utmSource = 'Pied Web';

        self::assertStringContainsString(
            'utm_source=pied-web',
            $this->decorator()->decorate(self::LINK, $audience, new UtmTag('janvier')),
        );
    }

    public function testItKeepsTheOtherAttributes(): void
    {
        $html = $this->decorate('<p><a class="btn" href="https://localhost.dev/a" rel="noopener">L</a></p>');

        self::assertStringContainsString('class="btn"', $html);
        self::assertStringContainsString('rel="noopener"', $html);
        self::assertStringContainsString('utm_source=newsletter', $html);
    }

    public function testItDoesNotArgueWithALinkTheAuthorTagged(): void
    {
        $html = $this->decorate('<p><a href="https://localhost.dev/a?utm_source=partner">L</a></p>');

        self::assertStringContainsString('utm_source=partner', $html);
        self::assertStringNotContainsString('utm_medium', $html);
    }

    public function testAnAudienceWithoutASourceIsLeftUntouched(): void
    {
        $audience = new Audience();
        $audience->mainHost = 'localhost.dev';

        self::assertSame(self::LINK, $this->decorator()->decorate(self::LINK, $audience, new UtmTag('janvier')));
    }

    public function testAStepCarriesItsPositionAsContent(): void
    {
        $html = $this->decorator()->decorate(self::LINK, $this->audience(), new UtmTag('bienvenue', 'step-2'));

        self::assertStringContainsString('utm_campaign=bienvenue', $html);
        self::assertStringContainsString('utm_content=step-2', $html);
    }

    public function testNoTagMeansNoChange(): void
    {
        self::assertSame(self::LINK, $this->decorator()->decorate(self::LINK, $this->audience(), null));
    }

    private function decorate(string $html): string
    {
        return $this->decorator()->decorate($html, $this->audience(), new UtmTag('janvier'));
    }

    private function audience(): Audience
    {
        $audience = new Audience();
        $audience->mainHost = 'localhost.dev';
        $audience->utmSource = 'newsletter';

        return $audience;
    }

    private function decorator(): UtmDecorator
    {
        return self::getContainer()->get(UtmDecorator::class);
    }
}
