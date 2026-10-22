<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Form\Type;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Admin\Form\Type\RedirectionTargetType;
use Symfony\Component\Form\Forms;

final class RedirectionTargetTypeTest extends TestCase
{
    #[DataProvider('targets')]
    public function testDestinationRoundTripsWithoutLosingTheStatus(string $content, string $target): void
    {
        $form = Forms::createFormFactory()->create(RedirectionTargetType::class, $content);
        self::assertSame($target, $form->createView()->vars['value']);
        $form->submit($target);
        self::assertTrue($form->isSynchronized());
        self::assertSame($content, $form->getData());
    }

    /** @return iterable<string, array{string, string}> */
    public static function targets(): iterable
    {
        yield 'relative' => ['Location: /new-page', '/new-page'];
        yield 'absolute with query' => ['Location: https://example.com/path?a=1&b=2', 'https://example.com/path?a=1&b=2'];
        yield 'temporary' => ['Location: /new-page 302', '/new-page 302'];
        yield 'gone' => ['Location: /removed 410', '/removed 410'];
    }
}
