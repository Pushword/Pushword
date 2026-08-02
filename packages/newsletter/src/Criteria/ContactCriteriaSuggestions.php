<?php

namespace Pushword\Newsletter\Criteria;

use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What the base already offers a segment: the tags carried, the locales written
 * to, and the property keys the site has stored on someone.
 *
 * Hosts are ignored, unlike the page side: an audience commonly spans the locale
 * hosts of one site, and a tag is the same tag on all of them.
 */
#[AutoconfigureTag('pushword.newsletter.criteria_suggestions')]
final readonly class ContactCriteriaSuggestions implements CriteriaSuggestions
{
    public function __construct(private ContactRepository $contactRepository)
    {
    }

    public function criteria(): string
    {
        return SegmentCriteria::class;
    }

    public function suggest(array $hosts): array
    {
        return [
            'tag' => $this->contactRepository->getAllTags(),
            'locale' => $this->contactRepository->getAllLocales(),
            AbstractCriteria::PROP_PREFIX => $this->contactRepository->getAllPropertyKeys(),
        ];
    }
}
