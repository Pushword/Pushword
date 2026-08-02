<?php

namespace Pushword\Newsletter\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The form is markup PHP builds per visitor, which is what a statically
 * generated page cannot do for itself.
 */
#[Group('integration')]
final class FormEndpointTest extends AbstractNewsletterTestCase
{
    /** @param array<string, string> $query */
    private function fetch(array $query): string
    {
        $this->client->request(Request::METHOD_POST, '/newsletter/form?'.http_build_query($query));

        return (string) $this->client->getResponse()->getContent();
    }

    private function enableCsrf(bool $enabled = true): void
    {
        self::getContainer()->get(SiteRegistry::class)->get()->setCustomProperty('newsletter_csrf_protection', $enabled);
    }

    protected function tearDown(): void
    {
        $this->enableCsrf();
        parent::tearDown();
    }

    /**
     * The utilities on each element are a `pwNewsletter*Class` default a site may
     * redefine as a twig global, so restyling never means forking the template.
     */
    public function testAClassGlobalOverridesTheDefaultUtilities(): void
    {
        $audience = $this->createAudience();
        self::getContainer()->get(Environment::class)->addGlobal('pwNewsletterSubmitClass', 'my-own-button');

        $html = $this->fetch(['audiences' => $audience->getSlug()]);

        self::assertStringContainsString('class="my-own-button"', $html);
        self::assertStringNotContainsString('class="link-btn"', $html);
    }

    /** Nothing HTML-escapable may sit in a default: `{{ }}` would mangle it into a class that matches no rule. */
    public function testNoDefaultCarriesACharacterTwigWouldEscape(): void
    {
        $templates = glob(__DIR__.'/../../src/templates/newsletter/*.twig');
        self::assertIsArray($templates);

        $defaults = [];
        foreach ($templates as $template) {
            preg_match_all("#pwNewsletter\w+Class\|default\('([^']*)'\)#", (string) file_get_contents($template), $matches);
            $defaults = [...$defaults, ...$matches[1]];
        }

        self::assertNotEmpty($defaults, 'The class defaults were renamed or removed.');
        foreach ($defaults as $default) {
            self::assertSame($default, htmlspecialchars($default, \ENT_QUOTES), 'A default utility string must survive Twig escaping: '.$default);
        }
    }

    public function testItRendersAFormPostingToTheLiveHost(): void
    {
        $audience = $this->createAudience();

        $html = $this->fetch(['audiences' => $audience->getSlug()]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('action="https://localhost.dev/newsletter/subscribe"', $html);
        self::assertStringContainsString('name="audience" value="'.$audience->getSlug().'"', $html);
        self::assertStringContainsString('name="email"', $html);
        self::assertStringContainsString('name="name"', $html);
    }

    /**
     * js-helper binds `.live-form` on every DOMChanged, which is how the form
     * gets bound once it has replaced the placeholder. An inline <script> would
     * not: one injected through innerHTML never runs.
     */
    public function testTheFormIsALiveFormRatherThanCarryingItsOwnScript(): void
    {
        $audience = $this->createAudience();

        $html = $this->fetch(['audiences' => $audience->getSlug()]);

        self::assertStringContainsString('class="live-form"', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function testTheHoneypotIsShippedAndHidden(): void
    {
        $audience = $this->createAudience();

        $html = $this->fetch(['audiences' => $audience->getSlug()]);

        self::assertStringContainsString('name="website"', $html);
        self::assertMatchesRegularExpression('/aria-hidden="true"[^>]*style="[^"]*-9999px/', $html);
    }

    public function testSeveralAudiencesTravelHidden(): void
    {
        $letter = $this->createAudience();
        $promos = $this->createAudience();

        $html = $this->fetch(['audiences' => $letter->getSlug().','.$promos->getSlug()]);

        self::assertStringContainsString('type="hidden" name="audiences[]" value="'.$letter->getSlug().'"', $html);
        self::assertStringContainsString('type="hidden" name="audiences[]" value="'.$promos->getSlug().'"', $html);
        self::assertStringNotContainsString('name="audience"', $html, 'the single-audience field would post one list out of the two');
    }

    public function testOnlyDeclaredInterestsBecomeHiddenFields(): void
    {
        $audience = $this->createAudience(interests: ['AmTrek']);

        $html = $this->fetch(['audiences' => $audience->getSlug(), 'interests' => 'AmTrek,Undeclared']);

        self::assertStringContainsString('name="interests[]" value="AmTrek"', $html);
        self::assertStringNotContainsString('Undeclared', $html);
    }

    /** The fragment is served by the live host; its labels must follow the page that asked. */
    public function testTheFormSpeaksTheLocaleItWasAskedIn(): void
    {
        $audience = $this->createAudience();

        self::assertStringContainsString('First name', $this->fetch(['audiences' => $audience->getSlug()]));
        self::assertStringContainsString('Prénom', $this->fetch(['audiences' => $audience->getSlug(), 'locale' => 'fr']));
    }

    public function testAnUnknownAudienceIsNotFound(): void
    {
        $this->client->request(Request::METHOD_POST, '/newsletter/form?audiences=does-not-exist');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testATokenIsIssuedByDefault(): void
    {
        $audience = $this->createAudience();

        self::assertStringContainsString('name="_token"', $this->fetch(['audiences' => $audience->getSlug()]));
    }

    public function testTurningTheSettingOffIssuesNoToken(): void
    {
        $audience = $this->createAudience();
        $this->enableCsrf(false);

        self::assertStringNotContainsString('name="_token"', $this->fetch(['audiences' => $audience->getSlug()]));
    }
}
