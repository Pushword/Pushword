<?php

namespace Pushword\Admin\Form\Type;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A single redirect_from entry: an old path on this host + the HTTP code used to
 * redirect it to the current page. Rows are edited as a collection (see CollectionField).
 *
 * @extends AbstractType<array{from: string, code: int}>
 */
final class RedirectFromRowType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('from', TextType::class, [
                'label' => 'adminPageRedirectFromPath',
                'required' => false,
                'attr' => ['placeholder' => 'old-slug'],
            ])
            ->add('code', ChoiceType::class, [
                'label' => 'adminPageRedirectFromCode',
                'choices' => [
                    '301 (permanent)' => 301,
                    '302 (found)' => 302,
                    '307 (temporary)' => 307,
                    '308 (permanent)' => 308,
                ],
                'required' => false,
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'empty_data' => ['from' => '', 'code' => 301],
        ]);
    }
}
