<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppPool;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;
use Twig\TemplateWrapper;

class ShowMore
{
    private ?string $currentId = null;

    private int $counter = 0;

    public function __construct(
        public Twig $twig,
        public AppPool $apps,
    ) {
    }

    private ?string $pagePrefix = null;

    private function getPagePrefix(): string
    {
        if (null === $this->pagePrefix) {
            $this->pagePrefix = $this->apps->getCurrentPage()?->getSlug() ?? uniqid();
        }

        return $this->pagePrefix;
    }

    private function getTemplate(): TemplateWrapper
    {
        $app = $this->apps->get();
        $templatePath = $app->getView('/component/show_more.html.twig', '@Pushword');

        return $this->twig->load($templatePath);
    }

    #[AsTwigFunction('startShowMore', needsEnvironment: false, isSafe: ['html'])]
    public function startShowMore(?string $id = null, string $showMoreExtraClass = ''): string
    {
        $this->currentId = $id ?? 'sm-'.substr(md5($this->getPagePrefix().'__'.(++$this->counter)), 0, 6);

        return $this->getTemplate()->renderBlock('before', [
            'id' => $this->currentId,
            'showMoreExtraClass' => $showMoreExtraClass,
        ]);
    }

    #[AsTwigFunction('endShowMore', needsEnvironment: false, isSafe: ['html'])]
    public function endShowMore(?string $showMoreBackground = null, ?string $id = null): string
    {
        $id ??= $this->currentId;
        $this->currentId = null;

        return $this->getTemplate()->renderBlock('after', [
            'id' => $id,
            'showMoreBackground' => $showMoreBackground,
        ]);
    }
}
