<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppConfig;

trait PhoneNumberTwigTrait
{
    abstract public function getApp(): AppConfig;

    public function renderPhoneNumber($number, $class = '')
    {
        $template = $this->getApp()->getView('/component/phone_number.html.twig', $this->twig);

        return trim($this->twig->render($template, [
            'number' => str_replace([' ', '&nbsp;', '.'], '', $number),
            'number_readable' => str_replace(' ', '&nbsp;', preg_replace('#^\+[0-9]{2} ?#', '0', $number)),
            'class' => $class,
        ]));
    }
}
