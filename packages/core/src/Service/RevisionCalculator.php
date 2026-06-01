<?php

namespace Pushword\Core\Service;

use Doctrine\ORM\Mapping\Column;
use Pushword\Core\Utils\Entity;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Computes the canonical revision token for an entity.
 *
 * Used both as the API's ETag / `If-Match` value and as the hash suffix in
 * Versionner filenames, so the three views of a page (HTTP, flat .md, version
 * storage) speak the same revision identifier.
 *
 * The hash covers every `#[Column]`-mapped property — same set the Versionner
 * serializes. Association changes that don't touch a column (and don't bump
 * `updatedAt`) won't shift the hash.
 */
final readonly class RevisionCalculator
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public function compute(object $entity): string
    {
        return sha1($this->serialize($entity));
    }

    /**
     * Canonical JSON payload that backs the hash. Exposed so callers that
     * need both the hash and the serialized form (e.g. the Versionner writing
     * a snapshot file) avoid serializing twice.
     */
    public function serialize(object $entity): string
    {
        $properties = Entity::getProperties($entity, [Column::class]);

        return $this->serializer->serialize($entity, 'json', [
            AbstractNormalizer::ATTRIBUTES => $properties,
        ]);
    }
}
