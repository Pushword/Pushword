<?php

namespace Pushword\Newsletter\Twig;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Service\SubscribeForm;
use Twig\Attribute\AsTwigFunction;

class NewsletterExtension
{
    public function __construct(
        private readonly SubscribeForm $subscribeForm,
        private readonly SiteRegistry $siteRegistry,
    ) {
    }

    /**
     * Leave a placeholder the live host fills with the subscription form.
     *
     *     {{ newsletter_form('altimood') }}
     *     {{ newsletter_form('altimood', ['AmTrek']) }}
     *     {{ newsletter_form(['altimood', 'altimood-promos']) }}
     *
     * The form itself is fetched at load time rather than rendered here: this
     * call may run at build time on a statically generated site, where a token
     * would be baked once for everyone.
     *
     * The form asks for a name and an email, nothing else: the lists it
     * subscribes to are the ones named here, and travel hidden.
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
        $url = $this->formUrl($audienceSlug, $interests, $source);

        return null === $url ? '' : $this->subscribeForm->placeholder($url);
    }

    /**
     * The same url, for a front end that fetches the form itself instead of
     * leaving the placeholder to do it.
     *
     *     {{ newsletter_form_url('altimood') }}
     *
     * What that buys is when: a modal loading its form as it opens costs the
     * page nothing until someone asks for it, and the token it comes back with
     * is minted at that moment rather than at build time.
     *
     * Null when no slug names an audience, which is what `newsletter_form()`
     * renders as nothing at all.
     *
     * @param string|string[] $audienceSlug
     * @param string[]        $interests
     */
    #[AsTwigFunction('newsletter_form_url')]
    public function formUrl(string|array $audienceSlug, array $interests = [], ?string $source = null): ?string
    {
        $audiences = $this->subscribeForm->audiences((array) $audienceSlug);

        if ([] === $audiences) {
            return null;
        }

        $page = $this->siteRegistry->getCurrentPage();

        return $this->subscribeForm->url(
            $audiences,
            $this->subscribeForm->declaredInterests($audiences, $interests),
            $page->locale ?? $this->siteRegistry->getLocale(),
            $source ?? $page?->getRealSlug(),
        );
    }
}
