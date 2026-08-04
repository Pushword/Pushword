<?php

namespace Pushword\AdminBlockEditor\Editor;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The dictionary EditorJS looks its labels up in.
 *
 * Every label a tool renders goes through `api.i18n.t()`, which resolves in the
 * namespace of the tool that asked — so a label shared by several tools has to
 * be repeated under each of them, and the keys are the English source strings
 * the tools pass, not our translation keys.
 */
final readonly class EditorJsMessages
{
    /** Labels emitted by the media tools' shared base class. */
    private const array MEDIA = [
        'Alternative text' => 'editorAlternativeText',
        'An error occurred' => 'editorErrorOccurred',
        'Caption' => 'editorCaption',
        'Select' => 'editorSelect',
        'Upload' => 'editorUpload',
    ];

    /** Labels shared by every table menu. */
    private const array TABLE = [
        'Add column to left' => 'editorAddColumnLeft',
        'Add column to right' => 'editorAddColumnRight',
        'Add row above' => 'editorAddRowAbove',
        'Add row below' => 'editorAddRowBelow',
        'Align center' => 'editorAlignCenter',
        'Align left' => 'editorAlignLeft',
        'Align right' => 'editorAlignRight',
        'Collapse' => 'editorCollapse',
        'Delete column' => 'editorDeleteColumn',
        'Delete row' => 'editorDeleteRow',
        'Sticky heading' => 'editorStickyHeading',
        'Stretch' => 'editorStretch',
        'With headings' => 'editorWithHeadings',
        'Without headings' => 'editorWithoutHeadings',
    ];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessages(): array
    {
        return [
            'ui' => [
                'blockTunes' => [
                    'toggler' => $this->translate([
                        'Click to tune' => 'editorClickToTune',
                        'or drag to move' => 'editorOrDragToMove',
                    ]),
                ],
                'inlineToolbar' => [
                    'converter' => $this->translate(['Convert to' => 'editorConvertTo']),
                ],
                'toolbar' => [
                    'toolbox' => $this->translate(['Add' => 'editorAdd']),
                ],
                'popover' => $this->translate([
                    'Convert to' => 'editorConvertTo',
                    'Filter' => 'editorFilter',
                    'Nothing found' => 'editorNothingFound',
                ]),
            ],
            'toolNames' => $this->translate([
                'Attachment' => 'editorAttachment',
                'Bold' => 'editorBold',
                'Card List' => 'editorCardList',
                'Code' => 'editorCode',
                'Delimiter' => 'editorDelimiter',
                'Embed' => 'editorEmbed',
                'Gallery' => 'editorGallery',
                'Heading' => 'editorHeading',
                'Image' => 'editorImage',
                'Italic' => 'editorItalic',
                'Link' => 'editorLink',
                'List' => 'editorList',
                'Marker' => 'editorMarker',
                'Pages List' => 'editorPagesList',
                'Quiz' => 'editorQuiz',
                'Quote' => 'editorQuote',
                'Raw' => 'editorRaw',
                'Snippet' => 'editorSnippet',
                'Table' => 'editorTable',
                'Text' => 'editorText',
            ]),
            'tools' => [
                'attaches' => $this->translate(self::MEDIA),
                'embed' => $this->translate([...self::MEDIA, 'Style' => 'editorStyle']),
                'gallery' => $this->translate([
                    ...self::MEDIA,
                    'Ce média est déjà présent dans la galerie.' => 'editorMediaAlreadyInGallery',
                ]),
                'header' => $this->translate(['Heading' => 'editorHeading']),
                'image' => $this->translate(self::MEDIA),
                'link' => $this->translate([
                    'Nouvel onglet' => 'editorNewTab',
                    'Obfusquer' => 'editorObfuscate',
                    'Style' => 'editorStyle',
                ]),
                'pages_list' => $this->translate(['No parameters' => 'editorNoParameters']),
                'snippet' => $this->translate([
                    'Choose a snippet first.' => 'editorChooseSnippetFirst',
                    'Choose a snippet…' => 'editorChooseSnippet',
                    'No parameters' => 'editorNoParameters',
                    'Parameters (JSON, optional)' => 'editorSnippetParameters',
                    'Snippet' => 'editorSnippet',
                ]),
                'table' => $this->translate(self::TABLE),
            ],
            'blockTunes' => [
                'anchor' => $this->translate(['Anchor' => 'editorAnchor']),
                'class' => $this->translate(['Class' => 'editorClass']),
                'delete' => $this->translate(['Delete' => 'editorDelete']),
                'moveDown' => $this->translate(['Move down' => 'editorMoveDown']),
                'moveUp' => $this->translate(['Move up' => 'editorMoveUp']),
                'textAlign' => $this->translate([
                    'Align center' => 'editorAlignCenter',
                    'Align left' => 'editorAlignLeft',
                    'Align right' => 'editorAlignRight',
                ]),
            ],
        ];
    }

    /**
     * @param array<string, string> $keys EditorJS label => Pushword translation key
     *
     * @return array<string, string>
     */
    private function translate(array $keys): array
    {
        $translated = [];
        foreach ($keys as $label => $key) {
            $translated[$label] = $this->translator->trans($key);
        }

        return $translated;
    }
}
