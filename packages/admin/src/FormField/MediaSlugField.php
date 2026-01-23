<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Media;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Media>
 */
class MediaSlugField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('slugForce', TextType::class, [
            'label' => 'adminPageSlugLabel',
            'help_html' => true,
            'required' => false,
            'help' => '' !== $this->admin->getSubject()->getSlug()
                ? '<span class="btn btn-link" onclick="toggleDisabled()" id="disabledLinkSlug">
                    <i class="fa fa-unlock"></i></span>
                    <script>function toggleDisabled() {
                        const slugField = document.querySelector(".slug_disabled");
                        slugField.removeAttribute("disabled");
                        slugField.focus();
                        document.getElementById("disabledLinkSlug").remove();
                    }</script>'
                    .'<small>Changer le slug change l\'URL de l\'image et peut créer des erreurs.</small>'
                : 'adminPageSlugHelp',
            'attr' => [
                'class' => 'slug_disabled',
                ('' !== $this->admin->getSubject()->getSlug() ? 'disabled' : 't') => '',
            ],
        ]);
    }
}
