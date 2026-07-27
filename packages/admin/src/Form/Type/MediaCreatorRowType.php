<?php

namespace Pushword\Admin\Form\Type;

use Override;
use Pushword\Core\Image\License\MediaLicense;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One creator of an image: a name and the kind of entity it is. Rows are edited as a
 * collection (see MediaLicenseField), because schema.org emits one node per creator
 * and each carries its own type — a photographer next to the agency that
 * commissioned the shot are not the same kind of thing.
 *
 * @extends AbstractType<array{name: string, type: string}>
 */
final class MediaCreatorRowType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'adminMediaCreatorNameLabel',
                'required' => false,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'adminMediaCreatorTypeLabel',
                'choices' => array_combine(
                    array_map(static fn (string $type): string => 'adminMediaCreatorType'.$type, MediaLicense::CREATOR_TYPES),
                    MediaLicense::CREATOR_TYPES,
                ),
                'required' => false,
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'empty_data' => ['name' => '', 'type' => MediaLicense::CREATOR_TYPE_PERSON],
        ]);
    }
}
