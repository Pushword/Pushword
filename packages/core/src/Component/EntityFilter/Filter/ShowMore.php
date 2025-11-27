<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\App\AppConfig;
use Twig\Environment;

class ShowMore extends AbstractFilter
{
    public AppConfig $app;

    public Environment $twig;

    public function apply(mixed $propertyValue): string
    {
        return $this->showMore($this->string($propertyValue));
    }

    private function showMore(string $body): string
    {
        $afterShowMoreTag = "\n".'<!--end-show-more-->';
        $bodyParts = explode("\n".'<!--start-show-more-->', $body);
        $body = '';
        $templatePath = $this->app->getView('/component/show_more.html.twig');
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
