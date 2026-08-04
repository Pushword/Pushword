<?php

namespace Pushword\PageScanner\Tests\Scanner;

use Pushword\Core\Entity\Page;
use Pushword\PageScanner\Scanner\TranslationLocaleScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TranslationLocaleScannerTest extends KernelTestCase
{
    public function testStaysSilentWhenEachTranslationSpeaksItsOwnLanguage(): void
    {
        $page = $this->page('en', 'homepage');
        $page->addTranslation($this->page('fr', 'accueil'));
        $page->addTranslation($this->page('es', 'inicio'));

        self::assertSame([], $this->scan($page));
    }

    public function testReportsATranslationInTheSameLanguageAsThePage(): void
    {
        $page = $this->page('en', 'homepage');
        $page->addTranslation($this->page('en', 'home'));

        $errors = $this->scan($page);

        self::assertCount(1, $errors);
        self::assertStringContainsString('/home', $errors[0]);
        self::assertStringContainsString('same language', $errors[0], 'The message must be translated, not left as its key.');
    }

    public function testReportsTwoTranslationsSharingALanguage(): void
    {
        $page = $this->page('en', 'homepage');
        $page->addTranslation($this->page('fr', 'accueil'));
        $page->addTranslation($this->page('fr', 'bienvenue'));

        $errors = $this->scan($page);

        self::assertCount(1, $errors);
        self::assertStringContainsString('/bienvenue', $errors[0]);
        self::assertStringContainsString('/accueil', $errors[0], 'The message names the translation it collides with.');
    }

    public function testAPageWithoutTranslationsIsFine(): void
    {
        self::assertSame([], $this->scan($this->page('en', 'homepage')));
    }

    /**
     * The first page of a language is the reference; every later one collides with it,
     * so three French translations are two errors, not one and not three.
     */
    public function testEveryExtraPageOfALanguageIsReported(): void
    {
        $page = $this->page('en', 'homepage');
        $page->addTranslation($this->page('fr', 'accueil'));
        $page->addTranslation($this->page('fr', 'bienvenue'));
        $page->addTranslation($this->page('fr', 'demarrer'));

        self::assertCount(2, $this->scan($page));
    }

    /**
     * Multi-host is the normal shape of a translated site, so the message has to say
     * which host the colliding page lives on — the slug alone would be ambiguous.
     */
    public function testTheMessageNamesTheHostWhenThereIsOne(): void
    {
        $page = $this->page('en', 'homepage');
        $page->addTranslation($this->page('en', 'home', 'example.tld'));

        $errors = $this->scan($page);

        self::assertCount(1, $errors);
        self::assertStringContainsString('example.tld/home', $errors[0]);
    }

    private function page(string $locale, string $slug, string $host = ''): Page
    {
        $page = new Page();
        $page->slug = $slug;
        $page->locale = $locale;
        $page->host = $host;

        return $page;
    }

    /** @return string[] */
    private function scan(Page $page): array
    {
        self::bootKernel();

        /** @var TranslationLocaleScanner $scanner */
        $scanner = self::getContainer()->get(TranslationLocaleScanner::class);

        return array_column($scanner->scan($page, ''), 'message');
    }
}
