<?php

namespace Pushword\Admin\FormField;

use Pushword\Version\PushwordVersionBundle;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class PagePublishedAtField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('publishedAt', DateTimePickerType::class, [
            'format' => DateTimeType::HTML5_FORMAT,
            'dp_side_by_side' => true,
            'dp_use_current' => true,
            'dp_use_seconds' => false,
            'dp_collapse' => true,
            'dp_calendar_weeks' => false,
            'dp_view_mode' => 'days',
            'dp_min_view_mode' => 'days',
            'label' => $this->admin->getMessagePrefix().'.publishedAt.label',
            'help' => $this->getHelp(),
            'help_html' => true,
        ]);
    }

    private function getHelp(): string
    {
        // TODO: translate
        return $this->getSubject() && $this->getSubject()->getSlug() ?
            'Dernière édition le '.$this->getSubject()->getUpdatedAt()->format('d/m à H:m')
            .(class_exists(PushwordVersionBundle::class)
                ? ' - <a href="'
                    .$this->admin->getRouter()->generate('pushword_version_list', ['id' => $this->getSubject()->getId()])
                    .'">Voir l\'historique</a>' : '')
            : '';
    }

    private function getSubject()
    {
        return $this->admin->getSubject();
    }
}
