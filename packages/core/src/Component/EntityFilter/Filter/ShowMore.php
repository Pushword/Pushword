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
        $bodyParts = explode('<!--start-show-more-->', $body);
        $body = '';
        $template = $this->twig->load($this->app->getView('/component/show_more.html.twig'));
        foreach ($bodyParts as $bodyPart) {
            if (! str_contains($bodyPart, '<!--end-show-more-->')) {
                $body .= $bodyPart;

                continue;
            }

            $id = 'sh-'.substr(md5('sh'.$bodyPart), 0, 4);
            $body .= $template->renderBlock('before', ['id' => $id])
                .str_replace('<!--end-show-more-->', $template->renderBlock('after', ['id' => $id]), $bodyPart);
        }

        return $body;
    }
}
