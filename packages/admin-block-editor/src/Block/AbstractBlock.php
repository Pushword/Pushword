<?php

namespace Pushword\AdminBlockEditor\Block;

use Exception;
use LogicException;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Entity\Page;
use Twig\Environment as Twig;

abstract class AbstractBlock implements BlockInterface
{
    public AppConfig $app;

    public Page $page;

    public Twig $twig;

    /** @var string */
    final public const NAME = 'NotDefined!';

    protected ?string $name = null;

    public function __construct(string $name)
    {
        if ($name !== $this->name) {
            throw new Exception('Name not concorde');
        }
    }

    public function getName(): string
    {
        return $this->name ?? throw new LogicException();
    }

    public function render(object $block, int $pos = 0): string
    {
        $view = $this->app->getView('/block/'.$this->getName().'.html.twig', '@PushwordAdminBlockEditor');

        return $this->twig->render($view, [
            'pos' => $pos,
            'block' => $block,
            'page' => $this->page,
        ]);
    }
}
