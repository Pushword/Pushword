<?php

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\Filter\ElseH1;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use ReflectionClass;

class ElseH1Test extends TestCase
{
    private function createManagerStub(): Manager
    {
        return new ReflectionClass(Manager::class)->newInstanceWithoutConstructor();
    }

    public function testNonEmptyValuePassthrough(): void
    {
        $filter = new ElseH1();
        $page = new Page();
        $page->setH1('Fallback Title');

        $result = $filter->apply('Custom Title', $page, $this->createManagerStub());

        self::assertSame('Custom Title', $result);
    }

    public function testEmptyValueFallsBackToH1(): void
    {
        $filter = new ElseH1();
        $page = new Page();
        $page->setH1('Fallback Title');

        $result = $filter->apply('', $page, $this->createManagerStub());

        self::assertSame('Fallback Title', $result);
    }

    public function testEmptyValueWithEmptyH1ReturnsEmptyString(): void
    {
        $filter = new ElseH1();
        $page = new Page();

        $result = $filter->apply('', $page, $this->createManagerStub());

        self::assertSame('', $result);
    }
}
