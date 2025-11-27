<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppPool;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;
use Twig\TemplateWrapper;

class ShowMore
{
    public function __construct(
        public Twig $twig,
        public AppPool $apps
    ) {
    }

    private function getTemplate(): TemplateWrapper
    {
        $app = $this->apps->get();
        $templatePath = $app->getView('/component/show_more.html.twig', '@Pushword');

        return $this->twig->load($templatePath);
    }

    #[AsTwigFunction('startShowMore', isSafe: ['html'], needsEnvironment: false)]
    public function startShowMore(string $id): string
    {
        return $this->getTemplate()->renderBlock('before', ['id' => $id]);
    }

    #[AsTwigFunction('endShowMore', isSafe: ['html'], needsEnvironment: false)]
    public function endShowMore(string $id): string
    {
        return $this->getTemplate()->renderBlock('after', ['id' => $id]);
    }
}
