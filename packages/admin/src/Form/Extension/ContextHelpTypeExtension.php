<?php

declare(strict_types=1);

namespace Pushword\Admin\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Moves a field's help into a popover opened from its label when the field sets the
 * `context_help` option: `true`, or the translation key of a short line that stays
 * under the field (a title or description counter is appended to it). Opt in
 * reference copy only: help holding controls, warnings or history must stay visible.
 */
final class ContextHelpTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('context_help', false)
            ->setAllowedTypes('context_help', ['bool', 'string']);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $contextHelp = $options['context_help'];
        if (false === $contextHelp) {
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

        if (\is_string($contextHelp)) {
            $attributes['data-pw-help-summary'] = $this->translator->trans($contextHelp);
        }

        $view->vars[$attribute] = $attributes;
    }
}
