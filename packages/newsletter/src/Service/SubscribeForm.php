<?php

namespace Pushword\Newsletter\Service;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Repository\AudienceRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment as Twig;

/**
 * The subscription form, shared by the placeholder `newsletter_form()` leaves in
 * the page and the endpoint that fills it.
 *
 * Like a conversation form it is fetched from the live host when someone loads
 * the page, not rendered into it: a statically generated page is built once, and
 * anything per-visitor baked into it — a CSRF token above all — would be a single
 * constant sitting in a public file.
 */
final readonly class SubscribeForm
{
    public function __construct(
        private AudienceRepository $audienceRepository,
        private SiteRegistry $siteRegistry,
        private LinkGenerator $linkGenerator,
        private UrlGeneratorInterface $urlGenerator,
        private Twig $twig,
        private SubscribeToken $subscribeToken,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    /**
     * The audiences these slugs name. A slug matching nothing is left out, so an
     * empty result means the form has no list to subscribe to at all.
     *
     * @param string[] $slugs
     *
     * @return list<Audience>
     */
    public function audiences(array $slugs): array
    {
        $audiences = [];

        foreach (array_unique($slugs) as $slug) {
            $audience = $this->audienceRepository->findOneBySlug($slug);

            if ($audience instanceof Audience) {
                $audiences[] = $audience;
            }
        }

        return $audiences;
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
    public function declaredInterests(array $audiences, array $interests): array
    {
        $declared = [];

        foreach ($audiences as $audience) {
            $declared = [...$declared, ...$audience->filterInterests($interests)];
        }

        return array_values(array_unique($declared));
    }

    /**
     * Where the placeholder fetches the form from. The first audience's live host
     * carries it; the endpoint resolves every slug on its own.
     *
     * @param list<Audience> $audiences
     * @param string[]       $interests
     */
    public function url(array $audiences, array $interests, string $locale, ?string $source): string
    {
        return $this->linkGenerator->base($audiences[0])
            .$this->urlGenerator->generate('pushword_newsletter_form').'?'.http_build_query([
                'audiences' => implode(',', array_map(static fn (Audience $audience): string => $audience->slug, $audiences)),
                'interests' => implode(',', $interests),
                'locale' => $locale,
                'source' => $source ?? '',
            ]);
    }

    /**
     * @param list<Audience> $audiences
     * @param string[]       $interests
     */
    public function render(array $audiences, array $interests, string $locale, ?string $source): string
    {
        // The fragment is served by the live host, whose request locale is not
        // the reader's: the page the form will sit in sent its own.
        return $this->localeSwitcher->runWithLocale($locale, fn (): string => $this->twig->render(
            $this->view('form.html.twig', $audiences[0]->mainHost),
            [
                'audiences' => $audiences,
                'interests' => $interests,
                'locale' => $locale,
                'source' => $source,
                'action' => $this->linkGenerator->base($audiences[0])
                    .$this->urlGenerator->generate('pushword_newsletter_subscribe'),
                'csrfToken' => $this->csrfProtected() ? $this->subscribeToken->issue() : null,
            ],
        ));
    }

    public function placeholder(string $url): string
    {
        return $this->twig->render($this->view('form_placeholder.html.twig'), ['url' => $url]);
    }

    /** True when the setting is off, since then nothing was ever issued to check. */
    public function isTokenValid(string $token): bool
    {
        if (! $this->csrfProtected()) {
            return true;
        }

        return $this->subscribeToken->isValid($token);
    }

    /**
     * On by default, and nothing has to turn it off: the token {@see
     * SubscribeToken} issues carries its own proof rather than pointing at a
     * session, so it survives the cross-domain round trip a static build makes.
     */
    public function csrfProtected(): bool
    {
        return $this->siteRegistry->get()->getBoolean('newsletter_csrf_protection', true);
    }

    private function view(string $template, ?string $host = null): string
    {
        return $this->siteRegistry->get($host)->getView('/newsletter/'.$template, '@PushwordNewsletter');
    }
}
