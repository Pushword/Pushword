<?php

namespace Pushword\AdminBlockEditor\Block;

use Exception;
use Pushword\Core\Component\EntityFilter\Filter\RequiredAppTrait;
use Pushword\Core\Component\EntityFilter\Filter\RequiredEntityTrait;
use Pushword\Core\Component\EntityFilter\Filter\RequiredTwigTrait;

abstract class AbstractBlock implements BlockInterface
{
    use RequiredAppTrait;
    use RequiredEntityTrait;
    use RequiredTwigTrait;

    public string $name;

    public function __construct(string $name)
    {
        if ($name !== $this->name) {
            throw new Exception('Name not concorde');
        }
    }

    public function render($data): string
    {
        $view = $this->getApp()->getView('/block/'.$this->name.'.html.twig', $this->getTwig());

        return $this->getTwig()->render($view, [
            'data' => $data,
            'page' => $this->getEntity(),
        ]);
    }
}
