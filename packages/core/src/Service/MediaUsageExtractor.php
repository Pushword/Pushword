<?php

namespace Pushword\Core\Service;

use Pushword\Core\Entity\MediaUsage;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;

use function Safe\json_encode;

/**
 * Which media a page references, and through what.
 *
 * Inverted against the loop it replaces: `pw:ai-index` used to ask, for every page,
 * whether each of the site's media appeared in it — O(pages × media) string scans.
 * Here the content is tokenized once (one `preg_match_all` over it) and each token
 * that looks like a filename is answered by a hash lookup, so the cost follows the
 * length of the content and stops following how many media the site holds.
 *
 * The token is also stricter than the `str_contains()` it replaces: matching whole
 * filename-shaped runs means `myphoto.jpg` no longer counts as a use of `photo.jpg`.
 *
 * Not covered, and not coverable this way: a media used only from a Twig template.
 * See {@see MediaUsage} for what that costs the callers.
 */
final readonly class MediaUsageExtractor
{
    /**
     * A filename-shaped run: what a media reference looks like once
     * {@see \Pushword\Core\Utils\MediaFileName} has slugified it. Anchored on a
     * character a filename can start with and greedy, so `/media/md/photo.jpg`
     * yields `photo.jpg` while `myphoto.jpg` yields itself.
     */
    private const string CANDIDATE_PATTERN = '/[A-Za-z0-9][A-Za-z0-9._-]*\.[A-Za-z0-9]{2,5}/';

    public function __construct(private MediaRepository $mediaRepository)
    {
    }

    /** @return list<array{mediaId: int, source: string}> */
    public function extractFromPage(Page $page): array
    {
        return $this->extract($page->mainContent, $page->customProperties, $page->mainImage?->id);
    }

    /**
     * @param array<mixed> $customProperties
     *
     * @return list<array{mediaId: int, source: string}>
     */
    public function extract(string $mainContent, array $customProperties, ?int $mainImageId): array
    {
        $index = $this->mediaRepository->getFileNameToIdMap();

        $usages = [];

        if (null !== $mainImageId) {
            $usages[] = ['mediaId' => $mainImageId, 'source' => MediaUsage::SOURCE_MAIN_IMAGE];
        }

        foreach ($this->resolve($mainContent, $index) as $mediaId) {
            $usages[] = ['mediaId' => $mediaId, 'source' => MediaUsage::SOURCE_CONTENT];
        }

        // Serialized rather than walked: the values are an arbitrary tree, and the
        // tokenizer does not care about the shape it reads a filename out of.
        if ([] !== $customProperties) {
            $serialized = json_encode($customProperties, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

            foreach ($this->resolve($serialized, $index) as $mediaId) {
                $usages[] = ['mediaId' => $mediaId, 'source' => MediaUsage::SOURCE_PROPERTY];
            }
        }

        return $usages;
    }

    /**
     * @param array<string, int> $index
     *
     * @return list<int>
     */
    private function resolve(string $haystack, array $index): array
    {
        if ('' === $haystack) {
            return [];
        }

        if (false === preg_match_all(self::CANDIDATE_PATTERN, $haystack, $matches)) {
            return [];
        }

        $mediaIds = [];
        foreach ($matches[0] as $candidate) {
            $mediaId = $index[$candidate] ?? null;
            if (null !== $mediaId) {
                $mediaIds[$mediaId] = true;
            }
        }

        return array_keys($mediaIds);
    }
}
