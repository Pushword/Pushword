<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Admin\FormField\PageRedirectFromField;
use Pushword\Core\Entity\Page;

final class PageRedirectFromFieldTest extends TestCase
{
    /**
     * Rows are plain arrays, so without an explicit stringifier EasyAdmin labels every
     * collapsed row "Array (2 items)".
     */
    #[DataProvider('rowProvider')]
    public function testRowsAreLabelledByTheirPathAndCode(mixed $row, string $expected): void
    {
        self::assertSame($expected, ($this->stringifier())($row));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function rowProvider(): iterable
    {
        yield 'path and code' => [['from' => 'old-slug', 'code' => 301], 'old-slug → 301'];
        yield 'code as a string' => [['from' => 'old-slug', 'code' => '302'], 'old-slug → 302'];
        yield 'code missing falls back to 301' => [['from' => 'old-slug'], 'old-slug → 301'];
        yield 'code not numeric falls back to 301' => [['from' => 'old-slug', 'code' => 'gone'], 'old-slug → 301'];
        yield 'path is trimmed' => [['from' => '  old-slug  ', 'code' => 308], 'old-slug → 308'];

        // A row the editor just added carries no path yet, and the prototype is not an array.
        yield 'empty path' => [['from' => '', 'code' => 301], '…'];
        yield 'path missing' => [['code' => 301], '…'];
        yield 'path not a string' => [['from' => ['nested'], 'code' => 301], '…'];
        yield 'not an array' => [null, '…'];
    }

    private function stringifier(): callable
    {
        /** @var AdminInterface<Page>&Stub $admin */
        $admin = self::createStub(AdminInterface::class);
        $field = new PageRedirectFromField(self::createStub(AdminFormFieldManager::class), $admin);

        $stringifier = $field->getEasyAdminField()
            ->getAsDto()
            ->getCustomOption(CollectionField::OPTION_ENTRY_TO_STRING_METHOD);

        self::assertIsCallable($stringifier, 'The field must configure its own row stringifier');

        return $stringifier;
    }
}
