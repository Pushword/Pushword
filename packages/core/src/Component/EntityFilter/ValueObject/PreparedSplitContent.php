<?php

declare(strict_types=1);

namespace Pushword\Core\Component\EntityFilter\ValueObject;

use Knp\Menu\ItemInterface;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\MenuFactory;
use Knp\Menu\Renderer\ListRenderer;
use Pushword\Core\Service\Toc\IndexedUniqueSlugger;
use RuntimeException;
use stdClass;

/** Validated native analysis. PHP retains its exact ICU slug and menu rules. */
final readonly class PreparedSplitContent
{
    public string $chapeau;

    public string $body;

    /** @var list<string> */
    public array $paragraphs;

    /** @var list<string> */
    public array $paragraphsWithChapeau;

    /** @var list<array{id: string, label: string, level: int}> */
    private array $headings;

    private string $toc;

    public function __construct(mixed $data)
    {
        if (! $data instanceof stdClass || ! \is_string($data->chapeau ?? null)
            || ! \is_array($data->headings ?? null) || ! array_is_list($data->headings)) {
            throw new RuntimeException('Invalid native split analysis');
        }

        $this->chapeau = $data->chapeau;
        $segments = $this->strings($data->segments ?? null);
        $this->paragraphs = $this->strings($data->paragraphs ?? null);
        $this->paragraphsWithChapeau = $this->strings($data->paragraphs_with_chapeau ?? null);
        if (\count($segments) !== \count($data->headings) + 1) {
            throw new RuntimeException('Invalid native heading slots');
        }

        $slugger = new IndexedUniqueSlugger();
        $body = '';
        $headings = [];
        foreach ($data->headings as $index => $heading) {
            if (! $heading instanceof stdClass || ! \is_string($heading->seed ?? null)
                || ! \is_string($heading->label ?? null) || ! \is_int($heading->level ?? null)
                || $heading->level < 1 || $heading->level > 6 || ! \is_bool($heading->listed ?? null)) {
                throw new RuntimeException('Invalid native heading');
            }

            $id = $slugger->makeSlug($heading->seed);
            if (ctype_digit(substr($id, 0, 1))) {
                $id = 'toc-'.$id;
            }

            $body .= $segments[$index].' id="'.htmlspecialchars($id, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"';
            if ($heading->listed && $heading->level >= 2) {
                $headings[] = ['id' => $id, 'label' => $heading->label, 'level' => $heading->level - 1];
            }
        }

        $this->body = $body.$segments[array_key_last($segments)];
        $this->headings = $headings;
        $this->toc = new ListRenderer(new Matcher(), ['currentClass' => 'active', 'ancestorClass' => 'active_ancestor'])->render($this->getMenu());
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        if (! \is_array($values) || ! array_is_list($values)) {
            throw new RuntimeException('Invalid native string list');
        }

        foreach ($values as $value) {
            if (! \is_string($value)) {
                throw new RuntimeException('Invalid native string');
            }
        }

        return $values;
    }

    /** Build the same Knp hierarchy as TOC\TocGenerator, without reparsing HTML. */
    public function getMenu(): ItemInterface
    {
        $menu = new MenuFactory()->createItem('TOC');
        $last = $menu;
        foreach ($this->headings as $heading) {
            $level = $heading['level'];
            if (1 === $level) {
                $parent = $menu;
            } elseif ($level > $last->getLevel()) {
                $parent = $last;
                while ($parent->getLevel() < $level - 1) {
                    $parent = $parent->addChild('');
                }
            } else {
                $parent = $last->getParent() ?? $menu;
                while ($parent->getLevel() > $level - 1) {
                    $parent = $parent->getParent() ?? $menu;
                }
            }

            $last = $parent->addChild($heading['id'], ['label' => $heading['label'], 'uri' => '#'.$heading['id']]);
        }

        while (1 === \count($menu->getChildren()) && \in_array($menu->getFirstChild()->getLabel(), ['', '0'], true)) {
            $menu = $menu->getFirstChild();
        }

        return $menu;
    }

    public function getToc(): string
    {
        return $this->toc;
    }
}
