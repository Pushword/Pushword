<?php

namespace Pushword\AdminBlockEditor\Tests;

use PHPUnit\Framework\TestCase;
use Pushword\AdminBlockEditor\Editor\EditorJsMessages;
use Symfony\Component\Translation\Loader\ArrayLoader;
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
}
