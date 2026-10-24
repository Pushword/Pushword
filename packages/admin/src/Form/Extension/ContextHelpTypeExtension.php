<?php

declare(strict_types=1);

namespace Pushword\Admin\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Reference copy only: raw HTML help may contain controls, counters or history. */
final class ContextHelpTypeExtension extends AbstractTypeExtension
{
    private const array HELP = [
        'adminMediaAcquireLicensePageHelp' => null,
        'adminMediaAltHelp' => null,
        'adminMediaAltsHelp' => 'adminHelpMediaTranslations',
        'adminMediaCreatorHelp' => null,
        'adminMediaDigitalSourceTypeHelp' => null,
        'adminPageCacheHelp' => null,
        'adminPageHoldPublicationHelp' => 'adminHelpHoldPublication',
        'adminPageLocaleHelp' => null,
        'adminPageNameHelp' => null,
        'adminPageRedirectFromHelp' => null,
        'adminPageRedirectionTargetHelp' => null,
        'adminPageSearchExcreptHelp' => null,
        'adminPageTitleHelp' => null,
        'adminPageTranslationsHelp' => null,
        'adminReviewRatingHelp' => null,
        'adminReviewReplyAuthorHelp' => null,
        'adminUserApiTokenHelp' => null,
        'newsletter.audience.field.clickTracking.help' => 'newsletterHelpClickTracking',
        'newsletter.audience.field.doubleOptIn.help' => 'newsletterHelpDoubleOptIn',
        'newsletter.audience.field.interests.help' => null,
        'newsletter.audience.field.mainHost.help' => null,
        'newsletter.audience.field.postalAddress.help' => null,
        'newsletter.audience.field.rateSeconds.help' => null,
        'newsletter.audience.field.slug.help' => null,
        'newsletter.audience.field.transactional.help' => 'newsletterHelpTransactional',
        'newsletter.audience.field.utmSource.help' => null,
        'newsletter.automation.field.enabled.help' => null,
        'newsletter.automation.field.hosts.help' => null,
        'newsletter.automation.field.recipientWhen.help' => null,
        'newsletter.automation.field.source.help' => null,
        'newsletter.automation.field.stopWhen.help' => null,
        'newsletter.automation.field.transactional.help' => 'newsletterHelpTransactional',
        'newsletter.automation.field.triggerWhen.help' => null,
        'newsletter.automation.fieldset.recipientsHelp' => null,
        'newsletter.campaign.field.preheader.help' => null,
        'newsletter.campaign.field.rateSeconds.help' => null,
        'newsletter.campaign.field.scheduledAt.help' => 'newsletterHelpSchedule',
        'newsletter.campaign.field.segment.help' => null,
        'newsletter.campaign.field.slug.help' => null,
        'newsletter.campaign.field.transactional.help' => 'newsletterHelpTransactional',
        'newsletter.campaign.field.translations.help' => null,
        'newsletter.campaign.fieldset.scheduleHelp' => null,
        'newsletter.contact.field.audience.help' => 'newsletterHelpMoveSubscription',
        'newsletter.contact.field.clickTrackingConsentAt.help' => 'newsletterHelpConsent',
        'newsletter.contact.field.customProperties.help' => 'adminHelpYaml',
        'newsletter.contact.field.phone.help' => 'newsletterHelpPhone',
        'newsletter.design.footerHelp' => null,
        'newsletter.design.headerHelp' => null,
        'newsletter.design.signatureHelp' => null,
        'newsletter.step.field.delay.help' => null,
        'newsletter.step.field.position.help' => null,
        'newsletter.template.footerHelp' => null,
        'newsletter.template.headerHelp' => null,
        'newsletter.template.help' => 'newsletterHelpSharedTemplate',
        'snippet.field.host.help' => null,
        'snippet.field.slug.help' => 'adminHelpSnippetReference',
    ];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $help = $view->vars['ea_help'] ?? $view->vars['help'];
        $key = $help instanceof TranslatableMessage ? $help->getMessage() : $help;
        if (! \is_string($key) || ! \array_key_exists($key, self::HELP)) {
            return;
        }

        $label = $view->vars['label'];
        if (! \is_string($label) && ! $label instanceof TranslatableInterface) {
            return;
        }

        $attribute = 'ea_form_fieldset_open' === $form->getConfig()->getType()->getInnerType()->getBlockPrefix() ? 'attr' : 'row_attr';
        $attributes = $view->vars[$attribute];
        $id = $view->vars['id'];
        if (! \is_array($attributes) || ! \is_string($id)) {
            return;
        }

        $label = $label instanceof TranslatableInterface ? $label->trans($this->translator) : $this->translator->trans($label);
        $attributes['data-pw-help'] = $this->translator->trans('contextHelpFor', ['%label%' => strip_tags($label)]);
        $attributes['data-pw-help-id'] = $id.'_reference_help';

        if (null !== $summary = self::HELP[$key]) {
            $attributes['data-pw-help-summary'] = $this->translator->trans($summary);
        }

        if (\in_array($key, ['adminPageTitleHelp', 'adminPageSearchExcreptHelp'], true)) {
            $attributes['data-pw-help-counter-label'] = $this->translator->trans('adminHelpCharacters');
        }

        $view->vars[$attribute] = $attributes;
    }
}
