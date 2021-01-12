<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\Form\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PageSlugField extends AbstractField
{

    protected function getSlugHelp()
    {
        if (! $this->admin->hasSubject() || ! $this->admin->getSubject()->getSlug()) {
            return 'admin.page.slug.help';
        }

        $page = $this->admin->getSubject();

        $url = $this->admin->getRouter()->generate('pushword_page', ['slug' => $page->getRealSlug()]);
        $liveUrl = $page->getHost() ?
            $this->admin->getRouter()->generate(
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

    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('slug', TextType::class, [
            'required' => false,
            'label' => 'admin.page.slug.label',
            'help_html' => true,
            'help' => $this->getSlugHelp(),
            'attr' => [
                'class' => 'slug_disabled',
                ($this->admin->getSubject() ? ($this->admin->getSubject()->getSlug() ? 'disabled' : 't') : 't') => '',
            ],
        ]);
    }
}
