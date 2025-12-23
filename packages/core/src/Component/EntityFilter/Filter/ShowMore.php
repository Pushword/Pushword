<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Twig\Environment;

#[AsFilter]
class ShowMore implements FilterInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly AppPool $apps,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));

        return $this->showMore((string) $propertyValue, $page);
    }

    private function showMore(string $body, Page $page): string
    {
        $afterShowMoreTag = "\n".'<!--end-show-more-->';
        $bodyParts = explode("\n".'<!--start-show-more-->', $body);
        $body = '';
        $app = $this->apps->get($page->getHost());
        $templatePath = $app->getView('/component/show_more.html.twig');
        $template = $this->twig->load($templatePath);
        foreach ($bodyParts as $bodyPart) {
            $bodyPart .= "\n";
            if (! str_contains($bodyPart, $afterShowMoreTag)) {
                $body .= $bodyPart;

                continue;
            }

            $id = 'sh-'.substr(md5('sh'.$bodyPart), 0, 4);
            $replaceWith = "\n".trim($template->renderBlock('after', ['id' => $id]));
            $body .= trim($template->renderBlock('before', ['id' => $id]))."\n\n"
                .str_replace($afterShowMoreTag, $replaceWith, $bodyPart);
        }

        return $body;
    }
}
