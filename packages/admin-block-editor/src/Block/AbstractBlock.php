<?php

namespace Pushword\AdminBlockEditor\Block;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredEntityTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;

abstract class AbstractBlock implements BlockInterface
{
    use RequiredAppTrait;
    use RequiredEntityTrait;
    use RequiredTwigTrait;

    /**
     * @var string
     */
    public const NAME = 'NotDefined!';

    public string $name;

    public function __construct(string $name)
    {
        if ($name !== $this->name) {
            throw new \Exception('Name not concorde');
        }
    }

    /**
     * @param mixed $block
     */
    public function render($block): string
    {
        $view = $this->getApp()->getView('/block/'.$this->name.'.html.twig', '@PushwordAdminBlockEditor');

        return $this->getTwig()->render($view, [
            'block' => $block,
            'page' => $this->getEntity(),
        ]);
    }
}
