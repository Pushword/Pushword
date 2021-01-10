<?php

namespace Pushword\Admin\Page;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\Form\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

trait FormFieldsTrait
{
    protected function configureFormFieldParentPage(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('parentPage', EntityType::class, [
            'class' => $this->pageClass,
            'label' => 'admin.page.parentPage.label',
            'required' => false,
        ]);
    }

    protected function configureFormFieldMetaRobots(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('metaRobots', ChoiceType::class, [
            'choices' => [
                'admin.page.metaRobots.choice.noIndex' => 'noindex',
            ],
            'label' => 'admin.page.metaRobots.label',
            'required' => false,
        ]);
    }

    protected function configureFormFieldName(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('name', TextareaType::class, [
            'label' => 'admin.page.name.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.name.help',
            'attr' => ['class' => 'autosize'],
        ]);
    }

    protected function configureFormFieldSearchExcrept(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('searchExcrept', TextareaType::class, [
            'required' => false,
            'label' => 'admin.page.searchExcrept.label',
            'help_html' => true,
            'help' => 'admin.page.searchExcrept.help',
            'attr' => ['class' => 'autosize'],
        ]);
    }

    protected function configureFormFieldMainContent(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('mainContent', TextareaType::class, [
            'attr' => [
                'style' => 'min-height: 50vh;font-size:125%; max-width:900px',
                'data-editor' => 'markdown',
                'data-gutter' => 0,
            ],
            'required' => false,
            'label' => ' ',
            'help_html' => true,
            'help' => 'admin.page.mainContent.help',
        ]);
    }

    /* TODO : keep it to integrate editorJs
    protected function configureFormFieldMainContentContentType(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('mainContentType', ChoiceType::class, [
            'choices' => [
                'admin.page.mainContentType.choice.defaultAppValue' => '0',
                'admin.page.mainContentType.choice.raw' => '1',
                'admin.page.mainContentType.choice.editorjs' => '2',
            ],
            'label' => 'admin.page.mainContentType.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.markdown.help',
        ]);
    }*/

    protected function getHosts()
    {
        return $this->apps->getHosts();
    }

    protected function configureFormFieldHost(FormMapper $formMapper): FormMapper
    {
        if (null === $this->getSubject()->getHost()) {
            $this->getSubject()->setHost($this->apps->getMainHost());
        }

        return $formMapper->add('host', ChoiceType::class, [
            'choices' => array_combine($this->getHosts(), $this->getHosts()),
            'required' => false,
            'label' => 'admin.page.host.label',
            'empty_data' => $this->getHosts()[0],
        ]);
    }

    protected function configureFormFieldTranslations(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('translations', ModelAutocompleteType::class, [
            'required' => false,
            'multiple' => true,
            'class' => $this->pageClass,
            'property' => 'slug',
            'label' => 'admin.page.translations.label',
            'help_html' => true,
            'help' => 'admin.page.translations.help',
            'btn_add' => false,
            'to_string_callback' => function ($entity) {
                return $entity->getLocale()
                    ? $entity->getLocale().' ('.$entity->getSlug().')'
                    : $entity->getSlug(); // switch for getLocale
                // todo : remove it in next release and leave only get locale
                // todo : add a clickable link to the other admin
            },
        ]);
    }

    protected function configureFormFieldTitle(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('title', TextareaType::class, [
            'label' => 'admin.page.title.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.title.help',
            'attr' => ['class' => 'titleToMeasure autosize textarea-no-newline'],
        ]);
    }

    protected function configureFormFieldH1(FormMapper $formMapper): FormMapper
    {
        $style = 'border-radius: 5px; font-size: 140%; font-weight: 700;'
            .'border: 1px solid #ddd; padding: 10px 10px 0px 10px;margin-top:-23px; margin-bottom:-23px';
        // Todo move style to view
        return $formMapper->add('h1', TextareaType::class, [
            'required' => false,
            'attr' => ['class' => 'autosize textarea-no-newline', 'placeholder' => 'admin.page.title.label', 'style' => $style],
            'label' => ' ',
        ]);
    }

    protected function configureFormFieldMainImage(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('mainImage', \Sonata\AdminBundle\Form\Type\ModelListType::class, [
            'required' => false,
            'class' => $this->mediaClass,
            //'label' => 'admin.page.mainImage.label',
            'btn_edit' => false,
        ]);
    }

    protected function getSlugHelp()
    {
        if (! $this->hasSubject() || ! $this->getSubject()->getSlug()) {
            return 'admin.page.slug.help';
        }

        $page = $this->getSubject();

        $url = $this->router->generate('pushword_page', ['slug' => $page->getRealSlug()]);
        $liveUrl = $page->getHost() ?
            $this->router->generate(
                'custom_host_pushword_page',
                ['host' => $page->getHost(), 'slug' => $page->getSlug()]
            ) : $url;

        return '<span class="btn btn-link" onclick="toggleDisabled()" id="disabledLinkSlug">
                    <i class="fa fa-unlock"></i></span>
                    <script>function toggleDisabled() {
                        $(".slug_disabled").first().removeAttr("disabled");
                        $(".slug_disabled").first().focus();
                        $("#disabledLinkSlug").first().remove();
                    }</script><small>Changer le slug change l\'URL et peut créer des erreurs.</small>'
                    .'<br><small>URL actuelle&nbsp: <a href="'.$liveUrl.'" target=_blank>'.$url.'</a></small>';
    }

    protected function configureFormFieldSlug(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('slug', TextType::class, [
            'required' => false,
            'label' => 'admin.page.slug.label',
            'help_html' => true,
            'help' => $this->getSlugHelp(),
            'attr' => [
                'class' => 'slug_disabled',
                ($this->getSubject() ? ($this->getSubject()->getSlug() ? 'disabled' : 't') : 't') => '',
            ],
        ]);
    }

    protected function configureFormFieldLocale(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('locale', TextType::class, [
            'label' => 'admin.page.locale.label',
            'help_html' => true,
            'help' => 'admin.page.locale.help',
        ]);
    }

    protected function configureFormFieldImages(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add(
            'pageHasMedias',
            CollectionType::class,
            [
                'by_reference' => false,
                'required' => false,
                'label' => ' ',
                'type_options' => [
                    'delete' => true,
                ],
            ],
            [
                'allow_add' => false,
                'allow_delete' => true,
                'btn_add' => false,
                'btn_catalogue' => false,
                'edit' => 'inline',
                'inline' => 'table',
                'sortable' => 'position',
                //'link_parameters' => ['context' => $context],
                'admin_code' => 'pushword.admin.pagehasmedia',
            ]
        );
    }

    abstract protected function exists(string $name): bool;

    /**
     * @return PageInterface
     */
    abstract protected function getSubject();
}
