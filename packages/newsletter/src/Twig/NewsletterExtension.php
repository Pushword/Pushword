<?php

namespace Pushword\Newsletter\Twig;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Service\LinkGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

class NewsletterExtension
{
    public function __construct(
        private readonly AudienceRepository $audienceRepository,
        private readonly SiteRegistry $siteRegistry,
        private readonly LinkGenerator $linkGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Twig $twig,
    ) {
    }

    /**
     * Render the subscription form for an audience.
     *
     *     {{ newsletter_form('altimood') }}
     *     {{ newsletter_form('altimood', ['AmTrek']) }}
     *
     * Interests are attached to whoever subscribes through this form; only the
     * values the audience declares survive the endpoint's allow-list.
     *
     * @param string[] $interests
     */
    #[AsTwigFunction('newsletter_form', isSafe: ['html'])]
    public function renderForm(string $audienceSlug, array $interests = [], ?string $source = null): string
    {
        $audience = $this->audienceRepository->findOneBySlug($audienceSlug);

        if (! $audience instanceof Audience) {
            return '';
        }

        $page = $this->siteRegistry->getCurrentPage();

        return $this->twig->render(
            $this->siteRegistry->get($audience->getMainHost())->getView('/newsletter/form.html.twig', '@PushwordNewsletter'),
            [
                'audience' => $audience,
                'interests' => $audience->filterInterests($interests),
                'locale' => $page->locale ?? $this->siteRegistry->getLocale(),
                'source' => $source ?? $page?->getRealSlug(),
                'action' => $this->linkGenerator->base($audience)
                    .$this->urlGenerator->generate('pushword_newsletter_subscribe'),
            ],
        );
    }
}
