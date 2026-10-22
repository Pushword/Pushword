<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin\Form;

use Override;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Service\EmailTemplateRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @extends AbstractType<Audience> */
final class EmailDesignType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EmailTemplateRegistry $templates,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emailLayout', ChoiceType::class, [
                'label' => 'newsletter.design.layout',
                'choices' => ['newsletter.design.simple' => 'simple', 'newsletter.design.wrapped' => 'framed'] + $this->templates->choices(),
                'expanded' => true,
                'help' => 'newsletter.design.layoutHelp',
            ])
            ->add('headerMarkdown', TextareaType::class, [
                'label' => 'newsletter.design.header',
                'required' => false,
                'attr' => ['rows' => 4],
                'help' => 'newsletter.design.headerHelp',
            ])
            ->add('footerMarkdown', TextareaType::class, [
                'label' => 'newsletter.design.footer',
                'required' => false,
                'attr' => ['rows' => 4],
                'help' => 'newsletter.design.footerHelp',
            ])
            ->add('sampleBody', TextareaType::class, [
                'label' => 'newsletter.design.sample',
                'mapped' => false,
                'required' => false,
                'data' => $this->translator->trans('newsletter.design.sampleBody'),
                'attr' => ['rows' => 6],
                'help' => 'newsletter.design.sampleHelp',
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Audience::class, 'translation_domain' => 'messages']);
    }
}
