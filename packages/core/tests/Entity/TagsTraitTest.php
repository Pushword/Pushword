<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Entity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use ReflectionProperty;

final class TagsTraitTest extends TestCase
{
    /** @return iterable<string, array{string[]|string|null, string, string[]}> */
    public static function tagStrings(): iterable
    {
        yield 'blog type first' => ['blog blog-incontournables france type:blog vercors', 'type:blog blog blog-incontournables france vercors ', ['blog', 'blog-incontournables', 'france', 'type:blog', 'vercors']];
        yield 'ordinary tags' => ['vercors france blog', 'blog france vercors ', ['blog', 'france', 'vercors']];
        yield 'empty tags' => ['', '', []];
        yield 'null tags' => [null, '', []];
        yield 'empty tag array' => [[], '', []];
        yield 'tag array' => [['vercors', 'type:blog', 'blog'], 'type:blog blog vercors ', ['blog', 'type:blog', 'vercors']];
        yield 'type alone' => ['type:blog', 'type:blog ', ['type:blog']];
        yield 'multiple types stay visible' => ['france type:listing blog type:blog', 'type:blog type:listing blog france ', ['blog', 'france', 'type:blog', 'type:listing']];
        yield 'prefix must match exactly' => ['archetype:blog typewriter france', 'archetype:blog france typewriter ', ['archetype:blog', 'france', 'typewriter']];
    }

    /**
     * @param string[]|string|null $tags
     * @param string[]             $storedTags
     */
    #[DataProvider('tagStrings')]
    public function testTagStringsPutTypesFirstWithoutChangingStoredOrder(array|string|null $tags, string $expected, array $storedTags): void
    {
        foreach ([new Page(), new Media()] as $entity) {
            $entity->setTags($tags);

            self::assertSame($expected, $entity->getTags());
            self::assertSame($storedTags, $entity->getTagList());

            $entity->setTags($entity->getTags());
            self::assertSame($expected, $entity->getTags());
            self::assertSame($storedTags, $entity->getTagList());
        }
    }

    public function testExistingStoredTagsDisplayTypesFirstWithoutBeingRewritten(): void
    {
        $storedTags = ['vercors', 'type:listing', 'france', 'type:blog', 'blog'];

        foreach ([new Page(), new Media()] as $entity) {
            new ReflectionProperty($entity, 'tags')->setValue($entity, $storedTags);

            self::assertSame('type:listing type:blog vercors france blog ', $entity->getTags());
            self::assertSame($storedTags, $entity->getTagList());
        }
    }
}
