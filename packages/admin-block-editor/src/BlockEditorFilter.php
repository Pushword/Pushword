<?php

namespace Pushword\AdminBlockEditor;

use Exception;
use LogicException;
use Pushword\AdminBlockEditor\Block\AbstractBlock;
use Pushword\AdminBlockEditor\Block\BlockInterface;
use Pushword\AdminBlockEditor\Block\DefaultBlock;
use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredEntityTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;
use Pushword\Core\Component\EntityFilter\Filter\AbstractFilter;

final class BlockEditorFilter extends AbstractFilter
{
    use RequiredAppTrait;
    use RequiredEntityTrait;
    use RequiredTwigTrait;

    /**
     * @var BlockInterface[]|null
     */
    private ?array $appBlocks = null;

    /**
     * @return mixed|string
     *
     * @noRector
     */
    public function apply($propertyValue)
    {
        if (
            ! \is_string($propertyValue)
            || ! \is_object($json = json_decode($propertyValue))
            || ! property_exists($json, 'blocks')
        ) {
            return $propertyValue;
        }

        $blocks = $json->blocks;

        $renderValue = '';

        foreach ($blocks as $block) {
            $classBlock = $this->getBlockManager($block->type);
            $blockRendered = $classBlock->render($block);
            $renderValue .= $blockRendered."\n";
        }

        return $renderValue;
    }

    private function loadBlockManager(BlockInterface $block): BlockInterface
    {
        $block
            ->setApp($this->app)
            ->setEntity($this->getEntity())
            ->setTwig($this->getTwig())
        ;

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
     * @psalm-suppress NullableReturnStatement
     * @psalm-suppress InvalidNullableReturnType
     *
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

        foreach ($blocks as $block) {
            if (class_exists($block) && ($block = new $block()) instanceof AbstractBlock) {
                $this->appBlocks[$block::NAME] = $this->loadBlockManager($block);

                continue;
            }

            if (\in_array($block, DefaultBlock::AVAILABLE_BLOCKS, true)) {
                $this->appBlocks[$block] = $this->loadBlockManager(new DefaultBlock($block));

                continue;
            }

            $class = '\Pushword\AdminBlockEditor\Block\\'.ucfirst($block).'Block';
            if (class_exists($class) && ($class = new $class()) instanceof BlockInterface) {
                $this->appBlocks[$block] = $this->loadBlockManager($class);

                continue;
            }

            throw new Exception('Block Manager for `'.$block.'` not found.');
        }

        return $this->appBlocks; // @phpstan-ignore-line
    }
}
