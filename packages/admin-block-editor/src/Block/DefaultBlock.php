<?php

namespace Pushword\AdminBlockEditor\Block;

use Exception;

class DefaultBlock extends AbstractBlock
{
    /**
     * @var string[]
     */
    final public const array AVAILABLE_BLOCKS = [
        'paragraph',
        'list',
        'header',
        'raw',
        'quote',
        'code',
        'list',
        'delimiter',
        'table',
        'image',
        'embed',
        'attaches',
        'pages_list',
        'gallery',
        'codeBlock',
    ];

    public function __construct(string $name)
    {
        if (! \in_array($name, self::AVAILABLE_BLOCKS, true)) {
            throw new Exception('Not a default block `'.$name.'`');
        }

        $this->name = $name;

        parent::__construct($name);
    }
}
