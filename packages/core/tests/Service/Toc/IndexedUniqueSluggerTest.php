<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service\Toc;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Toc\IndexedUniqueSlugger;
use TOC\UniqueSlugger;

final class IndexedUniqueSluggerTest extends TestCase
{
    public function testParityIncludingNumericLooseComparisonsAndReset(): void
    {
        $reference = new UniqueSlugger();
        $indexed = new IndexedUniqueSlugger();
        $inputs = ['', '🦀', '0', '00', '-0', '1', '01', '1e3', '1000', '0e1', '0e2', 'foo', 'foo-1', 'foo', 'foo-2', 'foo',
            'Café', 'cafe', '中文', 'русский', 'Ελληνικά', '123', 'toc-123', 'A & B'];
        for ($round = 0; $round < 2; ++$round) {
            for ($i = 0; $i < 1000; ++$i) {
                $input = $inputs[($i * 17) % \count($inputs)];
                self::assertSame($reference->makeSlug($input), $indexed->makeSlug($input), $input);
            }

            $reference->reset();
            $indexed->reset();
        }
    }
}
