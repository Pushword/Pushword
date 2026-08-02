<?php

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Pushword\Newsletter\Controller\CriteriaController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A rule, as the admin edits it: the textarea it has always been, plus what the
 * condition builder needs to grow over it.
 *
 * The builder is an enhancement and never a replacement — the field it decorates
 * is the same string property, validated by the same constraint, so the rule can
 * be written as rows, as JSON or as a search, and a browser running no JavaScript
 * still edits it the way it always did.
 */
final class CriteriaField
{
    public static function new(
        string $property,
        string $label,
        string $side,
        UrlGeneratorInterface $urlGenerator,
        ?int $automation = null,
    ): TextareaField {
        $attributes = [
            'data-pw-criteria' => $side,
            'data-pw-criteria-vocabulary' => $urlGenerator->generate('pushword_newsletter_criteria_vocabulary'),
            'data-pw-criteria-preview' => $urlGenerator->generate('pushword_newsletter_criteria_preview'),
        ];

        // Only the trigger side needs it: counting what a rule catches means
        // subtracting what this automation has already handled, which an unsaved
        // one has no identity to look up.
        if (CriteriaController::SIDE_TRIGGER === $side && null !== $automation) {
            $attributes['data-pw-criteria-automation'] = (string) $automation;
        }

        return TextareaField::new($property, $label)
            ->hideOnIndex()
            ->setFormTypeOption('attr', $attributes);
    }
}
