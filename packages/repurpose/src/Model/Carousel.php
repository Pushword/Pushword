<?php

namespace Pushword\Repurpose\Model;

use DateTimeImmutable;
use Pushword\Repurpose\Service\FontPairingRegistry;
use Pushword\Repurpose\Service\FormatRegistry;
use Pushword\Repurpose\Service\NetworkRegistry;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Throwable;

/**
 * A whole carousel, as authored in one `{host}/social-post/{slug}/{network}.json`
 * file (or over the API). Plain value object: hydrated from decoded JSON by
 * {@see \Pushword\Repurpose\Service\CarouselFactory} and validated by Symfony
 * Validator — the single source of truth shared by the renderer, the CLI lint and
 * the API endpoint.
 *
 * `page` and `network` are authoritative here (never parsed from the filename).
 * Colours and fonts left null fall back to the site's own design tokens.
 */
class Carousel
{
    public const array STATUSES = ['draft', 'planned', 'posted'];

    public const array TEMPLATES = ['editorial'];

    public const array CREATOR_ORIENTATIONS = ['horizontal', 'vertical'];

    public const array CREATOR_ON_SLIDES = ['all', 'intro-outro', 'first', 'none'];

    /**
     * @param string[] $hashtags
     * @param Slide[]  $slides
     */
    public function __construct(
        #[Assert\NotBlank(message: 'repurpose.page.empty')]
        public string $page = '',
        #[Assert\NotBlank(message: 'repurpose.network.empty')]
        #[Assert\Choice(callback: [NetworkRegistry::class, 'keys'], message: 'repurpose.network.invalid')]
        public string $network = '',
        #[Assert\NotBlank(message: 'repurpose.format.empty')]
        #[Assert\Choice(callback: [FormatRegistry::class, 'ids'], message: 'repurpose.format.invalid')]
        public string $format = '',
        #[Assert\Choice(choices: self::TEMPLATES, message: 'repurpose.template.invalid')]
        public string $template = 'editorial',
        #[Assert\Choice(choices: self::STATUSES, message: 'repurpose.status.invalid')]
        public string $status = 'draft',
        public ?string $plannedAt = null,
        public ?string $caption = null,
        public array $hashtags = [],
        #[Assert\Valid]
        public ?Palette $palette = null,
        #[Assert\Choice(callback: [FontPairingRegistry::class, 'keys'], message: 'repurpose.fontPairing.invalid')]
        public ?string $fontPairing = null,
        #[Assert\Valid]
        public ?Counter $counter = null,
        public ?string $creator = null,
        #[Assert\Choice(choices: self::CREATOR_ORIENTATIONS, message: 'repurpose.creator.orientation.invalid')]
        public string $creatorOrientation = 'horizontal',
        #[Assert\Choice(choices: self::CREATOR_ON_SLIDES, message: 'repurpose.creator.onSlides.invalid')]
        public string $creatorOnSlides = 'intro-outro',
        #[Assert\Count(min: 1, minMessage: 'repurpose.slides.min')]
        #[Assert\Valid]
        public array $slides = [],
    ) {
    }

    /**
     * The chosen format must be one the network actually accepts.
     */
    #[Assert\Callback]
    public function validateFormatForNetwork(ExecutionContextInterface $context): void
    {
        if ('' === $this->network || '' === $this->format) {
            return;
        }

        $allowed = NetworkRegistry::formatsFor($this->network);
        if ([] === $allowed || \in_array($this->format, $allowed, true)) {
            return;
        }

        $context->buildViolation('repurpose.format.notAllowedForNetwork')
            ->setParameter('%network%', $this->network)
            ->setParameter('%formats%', implode(', ', $allowed))
            ->atPath('format')
            ->addViolation();
    }

    /**
     * Slide count against the network's platform-enforced cap (a hard limit, so an
     * error — not the "ideally under N" engagement guidance, which never fails).
     */
    #[Assert\Callback]
    public function validateSlideCount(ExecutionContextInterface $context): void
    {
        $limits = NetworkRegistry::NETWORKS[$this->network]['limits'] ?? [];
        $max = $limits['maxSlides'] ?? $limits['maxPages'] ?? null;
        if (null === $max || \count($this->slides) <= $max) {
            return;
        }

        $context->buildViolation('repurpose.slides.max')
            ->setParameter('%max%', (string) $max)
            ->setParameter('%network%', $this->network)
            ->atPath('slides')
            ->addViolation();
    }

    /**
     * Caption length against the network's hard character cap.
     */
    #[Assert\Callback]
    public function validateCaption(ExecutionContextInterface $context): void
    {
        $max = NetworkRegistry::NETWORKS[$this->network]['limits']['caption'] ?? null;
        if (null === $max || null === $this->caption || mb_strlen($this->caption) <= $max) {
            return;
        }

        $context->buildViolation('repurpose.caption.max')
            ->setParameter('%max%', (string) $max)
            ->atPath('caption')
            ->addViolation();
    }

    /**
     * `plannedAt`, when set, must be a parseable date-time.
     */
    #[Assert\Callback]
    public function validatePlannedAt(ExecutionContextInterface $context): void
    {
        if (null === $this->plannedAt || '' === $this->plannedAt) {
            return;
        }

        try {
            new DateTimeImmutable($this->plannedAt);
        } catch (Throwable) {
            $context->buildViolation('repurpose.plannedAt.invalid')
                ->atPath('plannedAt')
                ->addViolation();
        }
    }
}
