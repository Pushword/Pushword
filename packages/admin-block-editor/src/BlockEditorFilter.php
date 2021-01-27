<?php

namespace Pushword\AdminBlockEditor;

use Exception;
use Pushword\AdminBlockEditor\Block\BlockInterface;
use Pushword\AdminBlockEditor\Block\DefaultBlock;
use Pushword\Core\Component\EntityFilter\Filter\AbstractFilter;
use Pushword\Core\Component\EntityFilter\Filter\RequiredAppTrait;
use Pushword\Core\Component\EntityFilter\Filter\RequiredEntityTrait;
use Pushword\Core\Component\EntityFilter\Filter\RequiredTwigTrait;
use Pushword\Core\Twig\ClassTrait;

final class BlockEditorFilter extends AbstractFilter
{
    use ClassTrait;
    use RequiredAppTrait;
    use RequiredEntityTrait;
    use RequiredTwigTrait;

    /** @var array */
    private $appBlocks;

    private bool $proseOpen = false;

    /**
     * @return string
     */
    public function apply($propertyValue)
    {
        $json = json_decode($propertyValue);

        if (false === $json || null === $json) {
            return $propertyValue;
        }

        $blocks = $json->blocks;

        $renderValue = '';

        foreach ($blocks as $block) {
            $classBlock = $this->getBlockManager($block->type);
            $blockRendered = $classBlock->render($block->data);
            $renderValue .= $this->mayProse($block->type).$blockRendered."\n";
        }

        return $renderValue.($this->proseOpen ? "\n".'</div>'."\n" : '');
    }

    private function mayProse(string $type): string
    {
        if ($this->proseOpen && ! \in_array($type, $this->app->get('admin_block_editor_type_to_prose'))) {
            $this->proseOpen = false;

            return "\n".'</div>'."\n";
        }

        if (! $this->proseOpen && \in_array($type, $this->app->get('admin_block_editor_type_to_prose'))) {
            $this->proseOpen = true;

            return "\n".'<div'.$this->getHtmlClass($this->getEntity(), 'prose').'>'."\n";
        }

        return '';
    }

    private function loadBlockManager(BlockInterface $blockManager): BlockInterface
    {
        return $blockManager
            ->setApp($this->app)
            ->setEntity($this->getEntity())
            ->setTwig($this->getTwig())
        ;
    }

    private function getBlockManager(string $type): BlockInterface
    {
        $blocks = $this->getAppBlocks();

        if (! isset($blocks[$type])) {
            throw new Exception('Block `'.$type.'` not configured to be used.');
        }

        return $blocks[$type];
    }

    private function getAppBlocks(): array
    {
        if (\is_array($this->appBlocks)) {
            return $this->appBlocks;
        }

        $blocks = $this->app->get('admin_block_editor_blocks');

        foreach ($blocks as $block) {
            if (class_exists($block)) {
                $this->appBlocks[$block::NAME] = $this->loadBlockManager(new $block());

                continue;
            }

            if (\in_array($block, DefaultBlock::AVAILABLE_BLOCKS)) {
                $this->appBlocks[$block] = $this->loadBlockManager(new DefaultBlock($block));

                continue;
            }

            $class = '\Pushword\AdminBlockEditor\Block\\'.ucfirst($block).'Block';
            if (class_exists($class)) {
                $this->appBlocks[$block] = $this->loadBlockManager(new $class());

                continue;
            }

            throw new Exception('Block Manager for `'.$block.'` not found.');
        }

        return $this->appBlocks;
    }
}
