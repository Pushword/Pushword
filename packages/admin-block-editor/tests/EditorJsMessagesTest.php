<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\AdminBlockEditor\Editor\EditorJsMessages;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

final class EditorJsMessagesTest extends TestCase
{
    /**
     * @return array{
     *     ui: array{
     *         blockTunes: array{toggler: array<string, string>},
     *         inlineToolbar: array{converter: array<string, string>},
     *         toolbar: array{toolbox: array<string, string>},
     *         popover: array<string, string>,
     *     },
     *     toolNames: array<string, string>,
     *     tools: array<string, array<string, string>>,
     *     blockTunes: array<string, array<string, string>>,
     * }
     */
    private function messages(): array
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'editorAlignLeft' => 'Aligner à gauche',
            'editorCaption' => 'Légende',
            'editorConvertTo' => 'Convertir en',
            'editorMermaidError' => 'Impossible d’afficher le diagramme Mermaid.',
            'editorObfuscate' => 'Obfusquer',
            'editorWithHeadings' => 'Avec en-têtes',
        ], 'fr');

        return new EditorJsMessages($translator)->getMessages();
    }

    public function testLabelsAreKeyedOnTheStringToolsPass(): void
    {
        $messages = $this->messages();

        self::assertSame('Aligner à gauche', $messages['blockTunes']['textAlign']['Align left']);
        self::assertSame('Avec en-têtes', $messages['tools']['table']['With headings']);
        self::assertSame('Convertir en', $messages['ui']['popover']['Convert to']);
        self::assertSame('Impossible d’afficher le diagramme Mermaid.', $messages['tools']['codeBlock']['Unable to render the Mermaid diagram.']);
    }

    public function testALabelSharedBySeveralToolsIsRepeatedUnderEachOfThem(): void
    {
        $messages = $this->messages();

        // api.i18n.t() resolves in the calling tool's namespace, so the media
        // tools cannot share one entry.
        foreach (['attaches', 'embed', 'gallery', 'image'] as $tool) {
            self::assertSame('Légende', $messages['tools'][$tool]['Caption'], $tool.' misses Caption');
        }
    }

    public function testThePickerMessagesAreDeclaredUnderEachToolThatShowsThem(): void
    {
        $messages = $this->messages();

        // The card list and the quiz reach the media picker without the media
        // tools' base class, so each namespace carries its own copy.
        foreach (['card_list', 'quiz'] as $tool) {
            self::assertArrayHasKey('Media picker not available', $messages['tools'][$tool], $tool);
        }

        self::assertArrayHasKey('Upload failed', $messages['tools']['quiz']);
    }

    public function testConvertToIsDeclaredInBothNamespacesEditorJsReadsItFrom(): void
    {
        $messages = $this->messages();

        self::assertSame('Convertir en', $messages['ui']['popover']['Convert to']);
        self::assertSame('Convertir en', $messages['ui']['inlineToolbar']['converter']['Convert to']);
    }

    public function testAnUntranslatedKeyFallsBackToItsKeyRatherThanEmptyingTheLabel(): void
    {
        $messages = $this->messages();

        // Symfony returns the key itself when a catalogue misses it; what
        // matters is that no label comes out empty.
        foreach ($messages['tools'] as $tool => $labels) {
            foreach ($labels as $label => $translation) {
                self::assertNotSame('', $translation, $tool.' / '.$label.' is empty');
            }
        }
    }

    public function testTheHyperlinkLabelsAreTranslatableRatherThanHardcodedFrench(): void
    {
        $messages = $this->messages();

        self::assertSame('Obfusquer', $messages['tools']['link']['Obfuscate']);
        self::assertArrayHasKey('New tab', $messages['tools']['link']);
        // The link panel's own design labels, the ones the widget declares.
        self::assertArrayHasKey('Button', $messages['tools']['link']);
        self::assertArrayHasKey('Button outline', $messages['tools']['link']);
        self::assertArrayHasKey('Discreet', $messages['tools']['link']);
    }

    public function testTheLinkTuneGetsTheLabelsItSharesWithTheLinkTool(): void
    {
        $messages = $this->messages();

        // api.i18n.t() resolves under blockTunes.linkTune for a tune, so the two
        // switches the tune renders cannot read the link tool's entries.
        self::assertSame('Obfusquer', $messages['blockTunes']['linkTune']['Obfuscate']);
        self::assertArrayHasKey('New tab', $messages['blockTunes']['linkTune']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function shippedLocales(): iterable
    {
        yield 'en' => ['en', 'No page has this slug', 'Heading level'];
        yield 'fr' => ['fr', 'Aucune page ne porte ce slug', 'Niveau de titre'];
    }

    #[DataProvider('shippedLocales')]
    public function testTheShippedCataloguesTranslateEveryLabel(string $locale, string $unknownSlug, string $headingLevel): void
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__.'/../src/translations/messages.'.$locale.'.yaml', $locale);

        $messages = new EditorJsMessages($translator)->getMessages();

        // The namespace is the tool name the widget registers CardList under.
        self::assertSame($unknownSlug, $messages['tools']['card_list']['No page has this slug']);
        // Keyed on the exact string Header.ts passes to api.i18n.t().
        self::assertSame($headingLevel, $messages['tools']['header']['Heading level']);

        // A key missing from the catalogue comes back as itself, e.g. "editorCardListUnknownSlug".
        array_walk_recursive($messages, static function (string $translation, string $label) use ($locale): void {
            self::assertDoesNotMatchRegularExpression('/^editor[A-Z]/', $translation, $locale.' misses the key for "'.$label.'"');
        });
    }

    public function testTheIncompleteStateBlockMessagesAreKeyedOnTheStringsTheToolsShow(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__.'/../src/translations/messages.fr.yaml', 'fr');

        $messages = new EditorJsMessages($translator)->getMessages();

        // StateBlock passes each tool's incompleteMessage to api.i18n.t() verbatim.
        self::assertSame(
            'Ajoutez d\'abord l\'URL de la vidéo, sa miniature et son texte alternatif.',
            $messages['tools']['embed']['Add the video URL, its thumbnail and its alternative text first.'],
        );
        self::assertSame('Indiquez d\'abord quelles pages lister.', $messages['tools']['pages_list']['Say which pages to list first.']);
    }
}
