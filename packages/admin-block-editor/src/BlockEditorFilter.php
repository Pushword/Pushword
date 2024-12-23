<?php

namespace Pushword\AdminBlockEditor;

use Exception;
use LogicException;
use Pushword\AdminBlockEditor\Block\AbstractBlock;
use Pushword\AdminBlockEditor\Block\BlockInterface;
use Pushword\AdminBlockEditor\Block\DefaultBlock;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\EntityFilter\Filter\AbstractFilter;
use Pushword\Core\Entity\Page;
use Twig\Environment as Twig;

final class BlockEditorFilter extends AbstractFilter
{
    public AppConfig $app;

    public Page $page;

    public Twig $twig;

    /**
     * @var BlockInterface[]|null
     */
    private ?array $appBlocks = null;

    /**
     * @return ($propertyValue is string ? string : mixed)
     */
    public function apply(mixed $propertyValue): mixed
    {
        if (! \is_string($propertyValue)) {
            return $propertyValue;
        }

        try {
            $json = EditorJsHelper::decode($propertyValue);
        } catch (Exception $exception) {
            if (isset($_GET['showException'])) {
                throw $exception;
            }

            return $propertyValue;
        }

        $blocks = $json->blocks;

        $renderValue = '';

        foreach ($blocks as $pos => $block) {
            $classBlock = $this->getBlockManager($block->type);
            $blockRendered = $classBlock->render($block, (int) $pos + 1);
            $renderValue .= $blockRendered."\n";
        }

        return $renderValue;
    }

    private function loadBlockManager(AbstractBlock $block): AbstractBlock
    {
        $block->app = $this->app;
        $block->page = $this->page;
        $block->twig = $this->twig;

        return $block;
    }

    private function getBlockManager(string $type): BlockInterface
    {
        $blocks = $this->getAppBlocks();

        if (! isset($blocks[$type])) {
            throw new Exception('Block `'.$type.'` not configured to be used.');
        }

        return $blocks[$type];
    }

    /**
     * @noRector
     *
     * @return BlockInterface[]
     */
    private function getAppBlocks(): array
    {
        if (\is_array($this->appBlocks)) {
            return $this->appBlocks;
        }

        $blocks = $this->app->get('admin_block_editor_blocks');
        if (! \is_array($blocks)) {
            throw new LogicException();
        }

        /** @var string[] $blocks */
        foreach ($blocks as $block) {
            if (class_exists($block) && ($blockClass = new $block()) instanceof AbstractBlock) {
                $this->appBlocks[$blockClass->getName()] = $this->loadBlockManager($blockClass);

                continue;
            }

            if (\in_array($block, DefaultBlock::AVAILABLE_BLOCKS, true)) {
                $this->appBlocks[$block] = $this->loadBlockManager(new DefaultBlock($block));

                continue;
            }

            $class = '\Pushword\AdminBlockEditor\Block\\'.ucfirst($block).'Block';
            if (class_exists($class) && ($blockClass = new $class()) instanceof BlockInterface) {
                /** @var AbstractBlock $blockClass */
                $this->appBlocks[$block] = $this->loadBlockManager($blockClass);

                continue;
            }

            throw new Exception('Block Manager for `'.$block.'` not found.');
        }

        return $this->appBlocks; // @phpstan-ignore-line
    }
}
