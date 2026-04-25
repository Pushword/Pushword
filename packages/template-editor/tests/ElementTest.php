<?php

declare(strict_types=1);

namespace Pushword\TemplateEditor;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ElementTest extends KernelTestCase
{
    public function testIt(): void
    {
        $templateDir = __DIR__.'/../../skeleton/templates';
        $path = 'newTemplateFile.html.twig';
        $newPath = 'newTemplateFile2.html.twig';

        $element = new Element($templateDir);

        $element->setPath($path);
        $element->setCode('<p>test</p>');

        $element->storeElement();

        self::assertFileExists($templateDir.'/'.$path);

        $element->setPath($newPath);

        $element->storeElement();

        self::assertFileDoesNotExist($templateDir.'/'.$path);
        self::assertFileExists($templateDir.'/'.$newPath);

        $element->deleteElement();

        self::assertFileDoesNotExist($templateDir.'/'.$path);
        self::assertFileDoesNotExist($templateDir.'/'.$newPath);

        self::assertSame('<p>test</p>', $element->getCode());
    }

    public function testLoadCode(): void
    {
        $templateDir = __DIR__.'/../../skeleton/templates';
        $newPath = 'newTemplateFile2.html.twig';

        $element = new Element($templateDir);
        $element->setPath($newPath);
        $element->setCode('<p>test</p>');
        $element->storeElement();
        unset($element);

        $element = new Element($templateDir, $newPath);
        self::assertSame('<p>test</p>', $element->getCode());

        $element->deleteElement();
    }

    public function testRepository(): void
    {
        $templateDir = __DIR__.'/../../skeleton/templates';
        $repo = new ElementRepository($templateDir, [], false);

        $templates = $repo->getAll();
        self::assertGreaterThan(0, \count($templates));

        self::assertSame($templates[0]->getPath(), $repo->getOneByEncodedPath($templates[0]->getEncodedPath())?->getPath());

        $templateDir = __DIR__.'/../../skeleton/templates';
        $repo = new ElementRepository($templateDir, [$templates[0]->getPath()], true);

        $templates = $repo->getAll();
        self::assertCount(1, $templates);
        self::assertSame($templates[0]->getPath(), $repo->getOneByEncodedPath($templates[0]->getEncodedPath())?->getPath());
        self::assertTrue($templates[0]->movingIsDisabled());
    }
}
