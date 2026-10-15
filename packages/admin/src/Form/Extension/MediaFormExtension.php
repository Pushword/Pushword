<?php

declare(strict_types=1);

namespace Pushword\Admin\Form\Extension;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudFormType;
use Pushword\Admin\EventSubscriber\MediaLicenseFormSubscriber;
use Pushword\Core\Entity\Media;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Makes the media form validate what is about to be saved, as every other write path
 * does through MediaUploadValidator::validateMedia(): the license block written back
 * and a new upload named, both ahead of Symfony's ValidationListener (POST_SUBMIT,
 * priority 0) rather than at persist.
 */
final class MediaFormExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly MediaLicenseFormSubscriber $licenseFormSubscriber,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [CrudFormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dataClass = $options['data_class'];
        if (! \is_string($dataClass) || ! is_a($dataClass, Media::class, true)) {
            return;
        }

        $builder->addEventSubscriber($this->licenseFormSubscriber);
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $media = $event->getData();
            if ($media instanceof Media) {
                $media->nameUpload();
            }
        }, 10);
    }
}
