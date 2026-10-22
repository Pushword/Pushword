<?php

declare(strict_types=1);

namespace Pushword\Admin\Form\Type;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/** @extends AbstractType<string|null> */
final class RedirectionTargetType extends AbstractType
{
    /** @param array<string, mixed> $options */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?string $content): string => str_starts_with($content ?? '', 'Location:') ? trim(substr($content, 9)) : ($content ?? ''),
            static fn (?string $target): string => 'Location: '.trim($target ?? ''),
        ));
    }

    #[Override]
    public function getParent(): string
    {
        return TextType::class;
    }
}
