<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Preparation labels never authorize delivery. Review is optional. */
enum EditorialStage: string implements TranslatableInterface
{
    case Draft = 'draft';
    case Review = 'review';
    case Ready = 'ready';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('newsletter.editorial.'.$this->value, locale: $locale);
    }
}
