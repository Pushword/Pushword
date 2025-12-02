<?php

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\Utils\MarkdownUtils;

class MarkdownUtilsTest extends TestCase
{
    public function testAddAnchorToHeader(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');
        $page->setMainContent("# Mon titre\n\nUn paragraphe.");

        MarkdownUtils::addAnchor($page, 'mon-titre', '/^# Mon titre/');

        $expected = "{#mon-titre}\n# Mon titre\n\nUn paragraphe.";
        self::assertSame($expected, $page->getMainContent());
    }

    public function testAddAnchorToParagraph(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');
        $page->setMainContent("# Titre\n\nUn paragraphe important.");

        MarkdownUtils::addAnchor($page, 'paragraphe-important', '/paragraphe important/', ['paragraph']);

        $expected = "# Titre\n\n{#paragraphe-important}\nUn paragraphe important.";
        self::assertSame($expected, $page->getMainContent());
    }

    public function testAddAnchorWithExistingAttribute(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');
        $page->setMainContent("{.class}\n# Mon titre\n\nUn paragraphe.");

        MarkdownUtils::addAnchor($page, 'mon-titre', '/^# Mon titre/');

        $expected = "{.class #mon-titre}\n# Mon titre\n\nUn paragraphe.";
        self::assertSame($expected, $page->getMainContent());
    }

    public function testAddAnchorSkipIfAlreadyHasAnchor(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');

        $originalContent = "{#existing-anchor}\n# Mon titre\n\nUn paragraphe.";
        $page->setMainContent($originalContent);

        MarkdownUtils::addAnchor($page, 'new-anchor', '/^# Mon titre/');

        // Le contenu ne doit pas être modifié car l'ancre existe déjà
        self::assertSame($originalContent, $page->getMainContent());
    }

    public function testAddAnchorWithMultipleAnchorsInAttribute(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');

        $originalContent = "{.class #anchor1}\n# Mon titre\n\nUn paragraphe.";
        $page->setMainContent($originalContent);

        MarkdownUtils::addAnchor($page, 'new-anchor', '/^# Mon titre/');

        // Ne doit pas modifier car l'attribut contient déjà une ancre
        self::assertSame($originalContent, $page->getMainContent());
    }

    public function testAddAnchorNoMatch(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');

        $originalContent = "# Mon titre\n\nUn paragraphe.";
        $page->setMainContent($originalContent);

        MarkdownUtils::addAnchor($page, 'mon-titre', '/^# Autre titre/');

        // Le contenu ne doit pas être modifié car aucun bloc ne correspond
        self::assertSame($originalContent, $page->getMainContent());
    }

    public function testAddAnchorOnlyFirstMatch(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');
        $page->setMainContent("# Mon titre\n\nUn paragraphe.\n\n# Mon titre\n\nAutre paragraphe.");

        MarkdownUtils::addAnchor($page, 'mon-titre', '/^# Mon titre/');

        // Seul le premier header correspondant doit être modifié
        $expected = "{#mon-titre}\n# Mon titre\n\nUn paragraphe.\n\n# Mon titre\n\nAutre paragraphe.";
        self::assertSame($expected, $page->getMainContent());
    }

    public function testAddAnchorWithCallback(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');
        $page->setMainContent("# Mon titre\n\nUn paragraphe.");

        $output = [];
        $callback = function (string $message) use (&$output): void {
            $output[] = $message;
        };

        MarkdownUtils::addAnchor($page, 'mon-titre', '/^# Mon titre/', ['header'], $callback);

        self::assertCount(1, $output);
        self::assertStringContainsString('example.com/test-page', $output[0]);
        self::assertStringContainsString('header updated with anchor', $output[0]);
        self::assertStringContainsString('`mon-titre`', $output[0]);
    }

    public function testAddAnchorWithCodeBlock(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');
        $page->setMainContent("# Mon titre\n\n```php\ncode here\n```\n\nUn paragraphe.");

        MarkdownUtils::addAnchor($page, 'mon-titre', '/^# Mon titre/');

        // Les blocs de code doivent être préservés
        $expected = "{#mon-titre}\n# Mon titre\n\n```php\ncode here\n```\n\nUn paragraphe.";
        self::assertSame($expected, $page->getMainContent());
    }

    public function testAddAnchorWithMultipleBlockTypes(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');
        $page->setMainContent("Autre texte.\n\nUn paragraphe important.\n\n# Titre");

        MarkdownUtils::addAnchor($page, 'paragraphe-important', '/paragraphe important/', ['header', 'paragraph']);

        // Le premier bloc qui correspond est le paragraphe "Un paragraphe important."
        // Note: les blocs précédents non modifiés ne sont pas inclus dans le résultat
        $expected = "Autre texte.\n\n{#paragraphe-important}\nUn paragraphe important.\n\n# Titre";
        self::assertSame($expected, $page->getMainContent());
    }

    public function testAddAnchorWithWindowsLineEndings(): void
    {
        $page = new Page();
        $page->setHost('example.com');
        $page->setSlug('test-page');
        $page->setMainContent("# Mon titre\r\n\r\nUn paragraphe.");

        MarkdownUtils::addAnchor($page, 'mon-titre', '/^# Mon titre/');

        // Les fins de ligne doivent être normalisées
        $expected = "{#mon-titre}\n# Mon titre\n\nUn paragraphe.";
        self::assertSame($expected, $page->getMainContent());
    }
}
