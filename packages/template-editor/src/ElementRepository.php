<?php

namespace Pushword\TemplateEditor;

use Symfony\Component\Finder\Finder;

final class ElementRepository
{
    /**
     * @param string[] $canBeEditedList
     */
    public function __construct(
        private readonly string $templateDir,
        private readonly array $canBeEditedList,  // template_editor_can_be_edited_list
        private readonly bool $disableCreation, // template_editor_disable_creation
    ) {
    }

    /**
     * @return array<int, Element>
     */
    public function getAll(): array
    {
        $templateFileList = (new Finder())->files()->in($this->templateDir);
        $elements = [];

        foreach ($templateFileList as $templateFile) {
            $templateFilePath = substr((string) $templateFile, \strlen($this->templateDir));
            if ([] !== $this->canBeEditedList && ! \in_array($templateFilePath, $this->canBeEditedList, true)) {
                continue;
            }

            $elements[] = new Element(
                $this->templateDir,
                $templateFilePath,
                $this->disableCreation
            );
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
