<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Media;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Media>
 */
class MediaSlugField extends AbstractField
{
    /**
     * @param FormMapper<Media> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('slugForce', TextType::class, [
            'label' => 'admin.page.slug.label',
            'help_html' => true,
            'required' => false,
            'help' => '' !== $this->admin->getSubject()->getSlug()
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
                ('' !== $this->admin->getSubject()->getSlug() ? 'disabled' : 't') => '',
            ],
        ]);
    }
}
