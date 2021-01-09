<?php

namespace Pushword\TemplateEditor;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ElementTest extends KernelTestCase
{
    public function testIt()
    {
        $templateDir = __DIR__.'/../../skeleton/templates';
        $path = 'newTemplateFile.html.twig';
        $newPath = 'newTemplateFile2.html.twig';

        $element = new Element($templateDir);

        $element->setPath($path);
        $element->setCode('<p>test</p>');

        $element->storeElement();

        $this->assertTrue(file_exists($templateDir.'/'.$path));

        $element->setPath($newPath);

        $element->storeElement();

        $this->assertTrue(! file_exists($templateDir.'/'.$path));
        $this->assertTrue(file_exists($templateDir.'/'.$newPath));

        $element->deleteElement();

        $this->assertTrue(! file_exists($templateDir.'/'.$path));
        $this->assertTrue(! file_exists($templateDir.'/'.$newPath));

        $this->assertSame($element->getCode(), '<p>test</p>');
    }

    public function testLoadCode()
    {
        $templateDir = __DIR__.'/../../skeleton/templates';
        $newPath = 'newTemplateFile2.html.twig';

        $element = new Element($templateDir);
        $element->setPath($newPath);
        $element->setCode('<p>test</p>');
        $element->storeElement();
        unset($element);

        $element = new Element($templateDir, $newPath);
        $this->assertSame('<p>test</p>', $element->getCode());

        $element->deleteElement();
    }

    public function testRepository()
    {
        $templateDir = __DIR__.'/../../skeleton/templates';
        $repo = new ElementRepository($templateDir);

        $templates = $repo->getAll();
        $this->assertTrue(\count($templates) > 0);

        $this->assertSame($templates[0]->getPath(), $repo->getOneByEncodedPath($templates[0]->getEncodedPath())->getPath());
    }
}
