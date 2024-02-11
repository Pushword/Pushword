<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Utils\F;
use Twig\Environment;

trait PhoneNumberTwigTrait
{
    private Environment $twig;

    abstract public function getApp(): AppConfig;

    public function renderPhoneNumber(string $number, string $class = ''): string
    {
        $template = $this->getApp()->getView('/component/phone_number.html.twig');

        return trim($this->twig->render($template, [
            'number' => str_replace([' ', '&nbsp;', '.'], '', $number),
            'number_readable' => str_replace(' ', '&nbsp;', F::preg_replace_str('#^\+\d{2} ?#', '0', $number)),
            'class' => $class,
        ]));
    }
}
