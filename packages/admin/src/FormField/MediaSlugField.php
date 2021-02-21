<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MediaSlugField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('slugForce', TextType::class, [
            'label' => 'admin.page.slug.label',
            'help_html' => true,
            'required' => false,
            'help' => $this->admin->getSubject() && $this->admin->getSubject()->getSlug()
                ? '<span class="btn btn-link" onclick="toggleDisabled()" id="disabledLinkSlug">
                    <i class="fa fa-unlock"></i></span>
                    <script>function toggleDisabled() {
                        $(".slug_disabled").first().removeAttr("disabled");
                        $(".slug_disabled").first().focus();
                        $("#disabledLinkSlug").first().remove();
                    }</script>'
                    .'<small>Changer le slug change l\'URL de l\'image et peut créer des erreurs.</small>'
                : 'admin.page.slug.help',
            'attr' => [
                'class' => 'slug_disabled',
                ($this->admin->getSubject() ? ($this->admin->getSubject()->getSlug() ? 'disabled' : 't') : 't') => '',
            ],
        ]);
    }
}
