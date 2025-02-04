<?php

namespace Pushword\AdminBlockEditor;

use Exception;
use JoliTypo\Fixer;
use Pushword\Core\Entity\Page;

use function Safe\json_encode;

final readonly class EditorJsPurifier
{
    public function __construct(private string $locale = 'fr_FR', private readonly ?Page $page = null, private readonly string $base = '')
    {
    }

    public function __invoke(string $raw): string
    {
        try {
            $data = EditorJsHelper::decode($raw);
        } catch (Exception) {
            return $raw;
        }

        foreach ($data->blocks as $k => $block) {
            if (\in_array($block->type, ['header', 'paragraph'], true)) {
                if (! property_exists($data->blocks[$k], 'data') || ! \is_object($data->blocks[$k]->data)
                    || ! property_exists($data->blocks[$k]->data, 'text')
                    || ! \is_string($text = $data->blocks[$k]->data->text)) {
                    return $raw;
                }

                $data->blocks[$k]->data->text = self::htmlPurifier($text);
            }

            if ('list' === $block->type) {
                if (! property_exists($data->blocks[$k], 'data') || ! \is_object($data->blocks[$k]->data)
                    || ! property_exists($data->blocks[$k]->data, 'items')
                    || ! \is_array($text = $data->blocks[$k]->data->items)) {
                    return $raw;
                }

                $data->blocks[$k]->data->items = self::htmlPurifierForList($text);
            }
        }

        return json_encode($data);
    }

    private function getFixer(): Fixer
    {
        $fixer = new Fixer(['Ellipsis', 'Dimension', 'Unit', 'Dash', 'SmartQuotes', 'FrenchNoBreakSpace', 'NoSpaceBeforeComma', 'CurlyQuote', 'Trademark']);

        if (\in_array($this->locale, ['fr', 'fr_FR'], true)) {
            $fixer->setLocale('fr_FR');
        } else {
            $fixer->setLocale('en_GB');
        }

        return $fixer;
    }

    public function htmlPurifier(string $text): string
    {
        $text = str_replace("\u{a0}", ' ', $text);
        $text = str_replace('  ', ' ', $text);
        $text = str_replace('&shy;', '', $text);
        $text = preg_replace('# </([a-z]+)>#i', '</$1> ', $text) ?? throw new Exception($text);
        $text = preg_replace('# ?<(b|i)> ?</(b|i)> ?#', ' ', $text) ?? throw new Exception($text);

        if (! str_contains($text, '{{')) { // for now, we skip when there is a twig inside the text because it's convert the quote
            $text = $this->getFixer()->fix($text);
        }

        $this->makeUrlRelative($text);

        return trim($text);
    }

    private function makeUrlRelative(string &$text): void
    {
        if (($host = $this->page?->getHost() ?? '') !== '' && '' !== $this->base) {
        }

        $toReplace = [
            '"'.$this->base.'/'.$host.'/',
            '"'.$this->base.'/',
        ];

        $text = str_replace($toReplace, '"/', $text);
    }

    /**
     * @param array<array-key, mixed> $items
     *
     * @return array<array-key, object{items: array<mixed>, content: string}>
     */
    private function htmlPurifierForList(array $items): array
    {
        $itemsPurified = [];
        foreach ($items as $k => $item) {
            if (! is_object($item) || ! property_exists($item, 'content') || ! is_string($item->content)) {
                throw new Exception();
            }

            $subListExists = property_exists($item, 'items') && is_array($subListItems = $item->items);
            $itemsPurified[$k] = (object) [
                'content' => self::htmlPurifier($item->content),
                'items' => $subListExists ? self::htmlPurifierForList($subListItems) : [],
            ];
        }

        return $itemsPurified;
    }
}
