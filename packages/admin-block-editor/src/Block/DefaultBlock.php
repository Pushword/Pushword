<?php

namespace Pushword\AdminBlockEditor\Block;

use Exception;
use Pushword\Core\Component\EntityFilter\Filter\RequiredAppTrait;
use Pushword\Core\Component\EntityFilter\Filter\RequiredEntityTrait;
use Pushword\Core\Component\EntityFilter\Filter\RequiredTwigTrait;

class DefaultBlock extends AbstractBlock
{

    const AVAILABLE_BLOCKS = [
        'paragraph',
        'list',
    ];

    public function __construct(string $name)
    {
        if (!in_array($name, self::AVAILABLE_BLOCKS))
            throw new Exception('Not a default block `'.$name.'`');

        $this->name = $name;
    }


}
