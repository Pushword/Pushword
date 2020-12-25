<?php

namespace Pushword\Admin\Page;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

trait FormFieldsOpenGraphTrait
{
    protected function configureFormFieldOgTitle(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('ogTitle', TextType::class, [
            'label' => 'admin.page.ogTitle.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.ogTitle.help',
        ]);
    }

    protected function configureFormFieldOgDescription(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('ogDescription', TextareaType::class, [
            'required' => false,
            'label' => 'admin.page.ogDescription.label',
            'help_html' => true,
            'help' => 'admin.page.ogDescription.help',
        ]);
    }

    protected function configureFormFieldOgImage(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('ogImage', TextType::class, [
            'required' => false,
            'label' => 'admin.page.ogDescription.label',
            'help_html' => true,
            'help' => 'admin.page.ogDescription.help',
        ]);
    }

    protected function configureFormFieldTwitterSite(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('twitterSite', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterSite.label',
            'help_html' => true,
            'help' => 'admin.page.twitterSite.help',
        ]);
    }

    protected function configureFormFieldTwitterCard(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('twitterCard', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterCard.label',
            'help_html' => true,
            'help' => 'admin.page.twitterCard.help',
        ]);
    }

    protected function configureFormFieldTwitterCreator(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('twitterCreator', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterCreator.label',
            'help_html' => true,
            'help' => 'admin.page.twitterCreator.help',
        ]);
    }
}
