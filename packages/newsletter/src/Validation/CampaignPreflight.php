<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Validation;

use InvalidArgumentException;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Block\BlockContext;
use Pushword\Newsletter\Block\BlockMarkup;
use Pushword\Newsletter\Block\BlockRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Event\CampaignIssue;
use Pushword\Newsletter\Event\CheckCampaign;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Service\SenderProfileRegistry;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

final readonly class CampaignPreflight
{
    private const string UNSUBSCRIBE = 'https://newsletter.invalid/unsubscribe-check';

    public function __construct(private MailRenderer $renderer, private SiteRegistry $sites, private EventDispatcherInterface $events, private SenderProfileRegistry $senderProfiles, private BlockRegistry $blocks)
    {
    }

    /** @return list<CampaignIssue> */
    public function check(Campaign $campaign): array
    {
        $issues = [];
        $contents = ['' => $campaign->contentFor('')];
        foreach ($campaign->translatedLocales() as $locale) {
            $contents[$locale] = $campaign->contentFor($locale);
        }

        $audience = $campaign->audience;
        if (! $audience instanceof Audience) {
            $issues[] = new CampaignIssue('audience.missing', 'newsletter.preflight.audienceMissing', 'audience');
        }

        $context = null;
        if ($audience instanceof Audience) {
            try {
                $context = $this->senderProfiles->resolve($campaign);
            } catch (InvalidArgumentException) {
                $issues[] = new CampaignIssue('sender.invalid', 'newsletter.preflight.senderInvalid', 'senderProfile');
            }
        }

        $html = [];
        foreach ($contents as $locale => $content) {
            foreach (['subject', 'bodyMarkdown'] as $field) {
                if ('' === trim($content[$field])) {
                    $issues[] = new CampaignIssue($field.'.missing', 'newsletter.preflight.'.$field.'Missing', $field, locale: $locale);
                }
            }

            if (mb_strlen($content['subject']) > 80) {
                $issues[] = new CampaignIssue('subject.long', 'newsletter.preflight.subjectLong', 'subject', 'warning', $locale);
            }

            if (! $audience instanceof Audience) {
                continue;
            }

            try {
                if ('' !== trim($content['bodyMarkdown']) && '' === trim(BlockMarkup::expand($content['bodyMarkdown']))) {
                    $issues[] = new CampaignIssue('bodyMarkdown.missing', 'newsletter.preflight.bodyMarkdownMissing', 'bodyMarkdown', locale: $locale);
                }

                $contact = new Contact($audience, 'preview@example.test');
                $contact->name = 'Alex';
                $contact->customProperties = ['firstName' => 'Alex'];
                $contact->locale = '' === $locale ? $this->sites->get($audience->mainHost)->locale : $locale;
                $contextBlock = new BlockContext($audience->mainHost, $contact->locale, $campaign->scheduledAt);
                foreach (BlockMarkup::blocks($content['bodyMarkdown']) as $block) {
                    try {
                        $provider = $this->blocks->get($block['provider']);
                        foreach ($provider->check($block['content'], $contextBlock) as $issue) {
                            $issues[] = new CampaignIssue($issue->code, $issue->message, $issue->field, $issue->severity, $locale, $block['content']->label);
                        }
                    } catch (Throwable) {
                        $issues[] = new CampaignIssue('block.unavailable', 'newsletter.preflight.blockUnavailable', 'bodyMarkdown', locale: $locale, block: $block['content']->label);
                    }
                }

                $html[$locale] = $this->renderer->html($audience, $contact, $content['subject'], $content['bodyMarkdown'], $content['preheader'], $campaign->isTransactional() ? null : self::UNSUBSCRIBE, null, sendContext: $context, locale: $contact->locale, emailLayout: $campaign->emailLayout);
                $crawler = new Crawler($html[$locale]);
                $hasUnsubscribe = false;
                foreach ($crawler->filter('a') as $anchor) {
                    $href = trim(new Crawler($anchor)->attr('href') ?? '');
                    $hasUnsubscribe = $hasUnsubscribe || self::UNSUBSCRIBE === $href;
                    if (! $this->validDestination($href)) {
                        $issues[] = new CampaignIssue('link.invalid', 'newsletter.preflight.linkInvalid', 'bodyMarkdown', locale: $locale);
                    }
                }

                if (! $campaign->isTransactional() && ! $hasUnsubscribe) {
                    $issues[] = new CampaignIssue('unsubscribe.missing', 'newsletter.preflight.unsubscribeMissing', 'emailLayout', locale: $locale);
                }
            } catch (Throwable) {
                $issues[] = new CampaignIssue('render.failed', 'newsletter.preflight.renderFailed', 'bodyMarkdown', locale: $locale);
            }
        }

        $check = new CheckCampaign($campaign, $contents, $html);
        $check->issues = $issues;

        try {
            $this->events->dispatch($check);
        } catch (Throwable) {
            $check->issues[] = new CampaignIssue('check.failed', 'newsletter.preflight.renderFailed', 'bodyMarkdown');
        }

        return $check->issues;
    }

    public function assertCanSend(Campaign $campaign): void
    {
        $errors = array_values(array_filter($this->check($campaign), static fn (CampaignIssue $issue): bool => 'error' === $issue->severity));
        if ([] !== $errors) {
            throw new CampaignValidationFailed($errors);
        }
    }

    private function validDestination(string $href): bool
    {
        if ('' === $href || '#' === $href || 1 === preg_match('/[\x00-\x20\x7f]/', $href)) {
            return false;
        }

        if (str_starts_with($href, '#')) {
            return true;
        }

        if (str_starts_with($href, '/') && ! str_starts_with($href, '//') && ! str_contains($href, '\\')) {
            return true;
        }

        if (str_starts_with($href, 'mailto:')) {
            return false !== filter_var(explode('?', substr($href, 7))[0], \FILTER_VALIDATE_EMAIL);
        }

        if (str_starts_with($href, 'tel:')) {
            return 1 === preg_match('/^tel:\+?[0-9().-]+$/D', $href);
        }

        return \in_array(parse_url($href, \PHP_URL_SCHEME), ['http', 'https'], true) && false !== filter_var($href, \FILTER_VALIDATE_URL);
    }
}
