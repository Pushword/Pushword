<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use function Symfony\Component\String\u;

/**
 * @extends AbstractField<Page>
 */
class PageSlugField extends AbstractField
{
    protected function getSlugHelp(): string
    {
        if ('' === $this->admin->getSubject()->getSlug()) {
            return 'admin.page.slug.help';
        }

        /** @param Page $page */
        $page = $this->admin->getSubject();

        $url = $page->getHost().$this->formFieldManager->router->generate('pushword_page', ['slug' => $page->getRealSlug()]);
        $liveUrl = '' !== $page->getHost() ?
            $this->formFieldManager->router->generate(
                'custom_host_pushword_page',
                ['host' => $page->getHost(), 'slug' => $page->getRealSlug()]
            ) : $url;

        return '<div id="disabledLinkSlug">
                    <span class="btn btn-primary" onclick="toggleDisabled()" style="float:right; margin-top:-36px; z-index:100;position:relative"><i class="fa fa-unlock"></i></span>
                    <script>function toggleDisabled() {
                        document.querySelector(".slug_disabled").removeAttribute("disabled");
                        document.querySelector(".slug_disabled").focus();
                        document.querySelector("#disabledLinkSlug").remove();
                    }</script> <small><a href="'.$liveUrl.'"><small><i class="fa fa-link"></i></small> '.u($url)->truncate(30, '…').'</a></small></div>';
    }

    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('slug', TextType::class, [
            'required' => false,
            'label' => 'admin.page.slug.label',
            'help_html' => true,
            'help' => $this->getSlugHelp(),
            'attr' => [
                'class' => 'slug_disabled',
                ('' !== $this->admin->getSubject()->getSlug() ? 'disabled' : 't') => '',
            ],
        ]);
    }
}
