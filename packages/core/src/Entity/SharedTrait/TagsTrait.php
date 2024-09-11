<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\String\u;

trait TagsTrait
{
    /** @var string[] */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    protected array $tags = [];

    /** @var string[] */
    protected array $reservedTags = ['children', 'sisters', 'grandchildren', 'related'];

    public function getTags(): string
    {
        $tags = implode(' ', $this->tags);

        return $tags.('' !== $tags ? ', ' : '');
    }

    /** @return string[] */
    public function getTagList(): array
    {
        return $this->tags;
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

        $tags = array_filter(array_map('trim', $tags));
        $tags = array_diff($tags, $this->reservedTags);
        // tag disappear without message for user ➜ if count != exception ?!
        $this->tags = array_values($tags);

        return $this;
    }

    /** @return string[] */
    private function manageTagsString(string $tags): array
    {
        if (! str_contains($tags, ',')) {
            return explode(' ', $tags);
        }

        $tags = explode(',', $tags);
        $tags = array_map(static fn (string $tag): string => (string) u($tag)->camel(), $tags);

        return $this->manageTagsString(implode(' ', $tags));
    }
}
