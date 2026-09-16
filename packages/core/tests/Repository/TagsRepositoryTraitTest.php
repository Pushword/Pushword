<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Repository;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Repository\TagsRepositoryTrait;

final class TagsRepositoryTraitTest extends TestCase
{
    public function testFlattensAndDeduplicatesInFirstSeenOrder(): void
    {
        self::assertSame(
            ['photo', 'logo', 'print'],
            $this->flatten([
                ['tags' => ['photo', 'logo']],
                ['tags' => ['logo', 'photo']],
                ['tags' => ['print']],
            ]),
        );
    }

    public function testKeepsEmptyResultsEmpty(): void
    {
        self::assertSame([], $this->flatten([]));
        self::assertSame([], $this->flatten([['tags' => []], ['tags' => []]]));
    }

    /**
     * A tag is camelized text, so "2024" is a legitimate one. Deduplicating
     * through array keys coerces it to an int, which would hand the admin
     * filter an int choice where every consumer expects a string.
     */
    public function testKeepsNumericLookingTagsAsStrings(): void
    {
        $tags = $this->flatten([['tags' => ['2024', '01', '1.5', '2024']]]);

        // assertSame, so an int 2024 among the strings fails here.
        self::assertSame(['2024', '01', '1.5'], $tags);
    }

    /**
     * @param array{tags: string[]}[] $tagsResult
     *
     * @return string[]
     */
    private function flatten(array $tagsResult): array
    {
        $flattener = new class {
            use TagsRepositoryTrait;

            /**
             * @param array{tags: string[]}[] $tagsResult
             *
             * @return string[]
             */
            public function flatten(array $tagsResult): array
            {
                return $this->flattenTags($tagsResult);
            }
        };

        return $flattener->flatten($tagsResult);
    }
}
