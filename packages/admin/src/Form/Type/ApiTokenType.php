<?php

namespace Pushword\Admin\Form\Type;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * API token field with generate/regenerate button support.
 *
 * @extends AbstractType<string|null>
 */
final class ApiTokenType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var array<string, mixed> $existingAttr */
        $existingAttr = $view->vars['attr'] ?? [];
        $view->vars['attr'] = array_merge($existingAttr, [
            'readonly' => true,
            'class' => 'form-control font-monospace api-token-input',
            'placeholder' => $options['placeholder'],
            'data-api-token-field' => '1',
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'placeholder' => '',
        ]);
    }

    #[Override]
    public function getParent(): string
    {
        return TextType::class;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'api_token';
    }
}
