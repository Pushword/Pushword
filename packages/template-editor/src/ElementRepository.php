<?php

namespace Pushword\TemplateEditor;

use Symfony\Component\Finder\Finder;

class ElementRepository
{
    public function __construct(private readonly string $templateDir)
    {
    }

    /**
     * @return array<int, Element>
     */
    public function getAll(): array
    {
        $finder = new Finder();
        $finder->files()->in($this->templateDir);
        $elements = [];

        foreach ($finder as $singleFinder) {
            $elements[] = new Element($this->templateDir, \Safe\substr((string) $singleFinder, \strlen($this->templateDir)));
        }

        return $elements;
    }

    public function getOneByEncodedPath(string $path): ?Element
    {
        foreach ($this->getAll() as $element) {
            if ($element->getEncodedPath() === $path) {
                return $element;
            }
        }

        return null;
    }
}
