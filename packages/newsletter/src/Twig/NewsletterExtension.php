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
     * Render the subscription form for an audience, or for a choice of them.
     *
     *     {{ newsletter_form('altimood') }}
     *     {{ newsletter_form('altimood', ['AmTrek']) }}
     *     {{ newsletter_form(['altimood', 'altimood-promos']) }}
     *
     * Several audiences render one ticked checkbox each: subscribing to a list
     * is its own consent, and ticking one never signs anyone up for the next.
     *
     * Interests are attached to whoever subscribes through this form; only the
     * values an audience declares survive its allow-list.
     *
     * @param string|string[] $audienceSlug
     * @param string[]        $interests
     */
    #[AsTwigFunction('newsletter_form', isSafe: ['html'])]
    public function renderForm(string|array $audienceSlug, array $interests = [], ?string $source = null): string
    {
        $audiences = [];

        foreach ((array) $audienceSlug as $slug) {
            $audience = $this->audienceRepository->findOneBySlug($slug);

            if ($audience instanceof Audience) {
                $audiences[] = $audience;
            }
        }

        if ([] === $audiences) {
            return '';
        }

        $page = $this->siteRegistry->getCurrentPage();

        // The form posts to one origin, so the first audience's host carries it;
        // the endpoint resolves every slug on its own.
        return $this->twig->render(
            $this->siteRegistry->get($audiences[0]->getMainHost())->getView('/newsletter/form.html.twig', '@PushwordNewsletter'),
            [
                'audiences' => $audiences,
                'interests' => $this->declaredInterests($audiences, $interests),
                'locale' => $page->locale ?? $this->siteRegistry->getLocale(),
                'source' => $source ?? $page?->getRealSlug(),
                'action' => $this->linkGenerator->base($audiences[0])
                    .$this->urlGenerator->generate('pushword_newsletter_subscribe'),
            ],
        );
    }

    /**
     * What at least one of the audiences knows about. Each one filters again on
     * arrival, so a tag declared by a single list stays that list's.
     *
     * @param list<Audience> $audiences
     * @param string[]       $interests
     *
     * @return string[]
     */
    private function declaredInterests(array $audiences, array $interests): array
    {
        $declared = [];

        foreach ($audiences as $audience) {
            $declared = [...$declared, ...$audience->filterInterests($interests)];
        }

        return array_values(array_unique($declared));
    }
}
