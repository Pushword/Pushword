<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\String\u;

/**
 * - A tag is a string without space.
 * - Multiple tags are inlined with a space.
 * - if user set "tag 1, tag two, tag three", it will be inlined and camelized "tag1 tagTwo tagThree".
 * - if user use comma in a previously inlined tags with space, it will try to keep the correctness (eg : tag1 tagTwo, tag 3 => tag1 TagTwo tag3).
 */
trait TagsTrait
{
    /** @var string[] */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    protected array $tags = [];

    /** @var string[] */
    public const array ReservedTags = ['children', 'sisters', 'grandchildren', 'related'];

    public function getTags(): string
    {
        $tags = implode(' ', $this->tags);

        return $tags.('' !== $tags ? ' ' : '');
    }

    /** @return string[] */
    public function getTagList(): array
    {
        return $this->tags;
    }

    public function addTag(string $tag): void
    {
        $this->setTags($this->getTags().' '.$tag);
    }

    /** @param string[]|string|null $tags */
    public function setTags(array|string|null $tags): self
    {
        if (null === $tags) {
            $tags = [];
        }

        if (\is_string($tags)) {
            $tags = $this->manageTagsString($tags);
        }

        $tags = array_filter(array_map(trim(...), $tags), static fn (string $tag): bool => '' !== $tag);
        $tags = array_diff($tags, self::ReservedTags);
        // tag disappear without message for user ➜ if count != exception ?!
        $tags = array_unique($tags);
        sort($tags);
        $this->tags = $tags;

        return $this;
    }

    /** @return string[] */
    private function manageTagsString(string $tags): array
    {
        if (! str_contains($tags, ',')) {
            return explode(' ', $tags);
        }

        $tags = explode(',', $tags);
        $finalTags = [];

        // TRICKY
        // if user use comma in a previously inlined tags, it will be dirty (eg : tag1 tagTwo, tag 3 => tag1TagTwo tag3).
        // test if space exists in tag and test if tag exists previously in $this->tags
        // if true, add tags to finalTags
        // else camelize tag add add it to $tags
        // between, try to create the new tag

        foreach ($tags as $tag) {
            if (! str_contains($tag, ' ')) {
                $finalTags[] = $tag;

                continue;
            }

            $partTags = explode(' ', $tag);
            $finalPartTag = '';
            $newTag = false;
            foreach ($partTags as $partTag) {
                if (false === $newTag && in_array($partTag, $this->tags, true)) {
                    $finalTags[] = $partTag;

                    continue;
                }

                $finalPartTag .= ' '.$partTag;
                $newTag = true;
            }

            $finalTags[] = (string) u($finalPartTag)->camel();
        }

        return $this->manageTagsString(implode(' ', $finalTags));
    }
}
