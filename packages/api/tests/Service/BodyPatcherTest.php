<?php

namespace Pushword\Api\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Api\Service\BodyPatcher;
use Pushword\Api\Service\BodyPatchException;

final class BodyPatcherTest extends TestCase
{
    private BodyPatcher $patcher;

    protected function setUp(): void
    {
        $this->patcher = new BodyPatcher();
    }

    public function testUniqueMatchIsReplaced(): void
    {
        $result = $this->patcher->apply('the price is 90€ today', [
            ['find' => '90€', 'replace' => '120€'],
        ]);

        self::assertSame('the price is 120€ today', $result);
    }

    public function testAmbiguousMatchThrows(): void
    {
        try {
            $this->patcher->apply('90€ here and 90€ there', [['find' => '90€', 'replace' => '120€']]);
            self::fail('Expected BodyPatchException');
        } catch (BodyPatchException $bodyPatchException) {
            self::assertSame(0, $bodyPatchException->index);
            self::assertSame('ambiguous', $bodyPatchException->reason);
            self::assertSame(2, $bodyPatchException->matches);
        }
    }

    public function testNotFoundThrows(): void
    {
        try {
            $this->patcher->apply('hello world', [['find' => 'absent', 'replace' => 'x']]);
            self::fail('Expected BodyPatchException');
        } catch (BodyPatchException $bodyPatchException) {
            self::assertSame('not_found', $bodyPatchException->reason);
            self::assertSame(0, $bodyPatchException->matches);
        }
    }

    public function testReplaceAllReplacesEveryOccurrence(): void
    {
        $result = $this->patcher->apply('a a a', [['find' => 'a', 'replace' => 'b', 'replaceAll' => true]]);

        self::assertSame('b b b', $result);
    }

    public function testReplaceAllStillRequiresAtLeastOneMatch(): void
    {
        $this->expectException(BodyPatchException::class);
        $this->patcher->apply('hello', [['find' => 'absent', 'replace' => 'x', 'replaceAll' => true]]);
    }

    public function testEditsApplySequentially(): void
    {
        // The second edit only becomes matchable after the first one ran.
        $result = $this->patcher->apply('foo', [
            ['find' => 'foo', 'replace' => 'bar'],
            ['find' => 'bar', 'replace' => 'baz'],
        ]);

        self::assertSame('baz', $result);
    }

    public function testFailingEditReportsItsIndex(): void
    {
        try {
            $this->patcher->apply('keep this', [
                ['find' => 'keep', 'replace' => 'KEEP'],
                ['find' => 'missing', 'replace' => 'x'],
            ]);
            self::fail('Expected BodyPatchException');
        } catch (BodyPatchException $bodyPatchException) {
            self::assertSame(1, $bodyPatchException->index);
            self::assertSame('not_found', $bodyPatchException->reason);
        }
    }

    public function testNoEditsReturnsBodyUnchanged(): void
    {
        self::assertSame('untouched', $this->patcher->apply('untouched', []));
    }

    public function testEmptyOrMissingFindIsNotFound(): void
    {
        $this->expectException(BodyPatchException::class);
        $this->patcher->apply('hello', [['replace' => 'x']]);
    }

    public function testMissingReplaceDeletesMatchedText(): void
    {
        // Omitting `replace` defaults to an empty string, i.e. a deletion.
        $result = $this->patcher->apply('keep [drop] keep', [['find' => ' [drop]']]);

        self::assertSame('keep keep', $result);
    }
}
