<?php

namespace Pushword\AdminBlockEditor\Block;

interface BlockInterface
{
    public function __construct(string $name);

    public function getName(): string;

    public function render(object $block, int $pos = 0): string;
}
