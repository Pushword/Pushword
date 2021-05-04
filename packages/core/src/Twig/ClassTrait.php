<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Entity\PageInterface;

trait ClassTrait
{
    private string $defaultContainerClass = 'py-12 px-3';
    private string $defaultProseClass = 'prose';

    abstract public function getApp(): AppConfig;

    public function getClass($page = null, string $containerName = 'container', string $default = ''): string
    {
        if (null !== $page && $page instanceof PageInterface && null !== $page->getCustomProperty($containerName)) {
            return $page->getCustomProperty($containerName);
        }

        if (null !== $this->getApp()->getCustomProperty('page_'.$containerName)) {
            return $this->getApp()->getCustomProperty('page_'.$containerName);
        }

        return '' !== $default ? $default : $this->getDefault($containerName);
    }

    public function getHtmlClass($page = null, string $containerName = 'container', string $default = ''): string
    {
        $class = $this->getClass($page, $containerName, $default);

        return ' class="'.$class.'"';
    }

    private function getDefault(string $containerName): string
    {
        $name = 'default'.ucfirst($containerName).'Class';

        return property_exists($this, $name) ? $this->$name : '';
    }
}
