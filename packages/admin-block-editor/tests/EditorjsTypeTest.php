<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Pushword\AdminBlockEditor\Form\EditorjsType;
use Symfony\Component\Form\Test\TypeTestCase;

#[AllowMockObjectsWithoutExpectations]
final class EditorjsTypeTest extends TypeTestCase
{
    public function testDiagnosticVersionIsAvailableToTheWidget(): void
    {
        $view = $this->factory->create(EditorjsType::class)->createView();

        self::assertIsString($view->vars['editorjs_version']);
        self::assertNotSame('', $view->vars['editorjs_version']);
    }
}
