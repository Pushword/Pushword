<?php

namespace Pushword\TemplateEditor;

use Symfony\Component\Finder\Finder;

class ElementRepository
{
    protected $templateDir;

    public function __construct($templateDir)
    {
        $this->templateDir = $templateDir;
    }

    public function getAll(): array
    {
        $finder = new Finder();
        $finder->files()->in($this->templateDir);
        $elements = [];

        foreach ($finder as $file) {
            $elements[] = new Element($this->templateDir, $file);
        }

        return $elements;
    }

    public function getOneByEncodedPath($path): ?Element
    {
        foreach ($this->getAll() as $element) {
            if ($element->getEncodedPath() == $path) {
                return $element;
            }
        }

        return null;
    }
}
