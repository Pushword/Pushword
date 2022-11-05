<?php

namespace Pushword\AdminBlockEditor\Block;

class DefaultBlock extends AbstractBlock
{
    /**
     * @var string[]
     */
    public const AVAILABLE_BLOCKS = [
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
    ];

    public function __construct(string $name)
    {
        if (! \in_array($name, self::AVAILABLE_BLOCKS, true)) {
            throw new \Exception('Not a default block `'.$name.'`');
        }

        $this->name = $name;

        parent::__construct($name);
    }
}
