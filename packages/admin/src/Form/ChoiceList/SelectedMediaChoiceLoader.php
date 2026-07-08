<?php

namespace Pushword\Admin\Form\ChoiceList;

use Override;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;

/**
 * Lazy choice loader for the media picker <select>.
 *
 * The picker UI (modal + JS) already browses/searches the whole library, so the
 * underlying <select> must NOT enumerate every Media. On a media-heavy site that
 * is O(all media) hydrated entities + <option> tags — the dominant cost of the
 * page-edit form (tens of thousands of options ≈ hundreds of MB and seconds of
 * render). Instead we render only the currently-selected media as a choice and
 * resolve whatever id the picker submits with a direct id lookup.
 *
 * The render list and the submit lookup are intentionally decoupled: a restricted
 * query_builder cannot do this because {@see \Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader::getEntitiesByIds()}
 * AND-s the submitted id onto that same restricted WHERE, which would reject a
 * freshly-picked media.
 */
final class SelectedMediaChoiceLoader extends AbstractChoiceLoader
{
    /** @param list<Media> $seed currently-selected media, rendered as the only <option>(s) */
    public function __construct(
        private readonly MediaRepository $mediaRepo,
        private array $seed = [],
    ) {
    }

    /** @param list<Media> $seed */
    public function setSeed(array $seed): void
    {
        $this->seed = $seed;
    }

    /**
     * @return list<Media>
     */
    protected function loadChoices(): iterable
    {
        return $this->seed;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<Media>
     */
    #[Override]
    protected function doLoadChoicesForValues(array $values, ?callable $value): array
    {
        $ids = [];
        foreach ($values as $submitted) {
            if (! \is_string($submitted) && ! \is_int($submitted)) {
                continue;
            }

            $id = (int) $submitted;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return [] === $ids ? [] : $this->mediaRepo->findBy(['id' => $ids]);
    }
}
