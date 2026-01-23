<?php

namespace Pushword\Admin\FormField;

use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

/**
 * @extends AbstractField<Page>
 */
class PagePublishedAtField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('publishedAt', DateTimeType::class, [
            'widget' => 'single_text',
            'with_seconds' => false,
            'html5' => true,
            'label' => 'adminPagePublishedAtLabel',
            'help' => $this->getHelp(),
            'help_html' => true,
            'attr' => ['data-selector' => 'publishedAtToDraft'],
        ]);
    }

    private function getHelp(): string
    {
        $page = $this->getSubject();
        $publishedAt = $page->getPublishedAt();
        $draft = null === $publishedAt || $publishedAt > new DateTime('now');

        return $this->formFieldManager->twig->render('@pwAdmin/page/page_draft.html.twig', ['page' => $page, 'draft' => $draft]);
    }

    private function getSubject(): Page
    {
        return $this->admin->getSubject();
    }
}
