<?php

declare(strict_types=1);

namespace Pushword\Repurpose\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Core\Entity\Page;
use Pushword\Repurpose\Entity\SocialPost;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Translation\TranslatableMessage;

/** @extends AbstractField<object> */
final class PageCarouselsField extends AbstractField
{
    public function getEasyAdminField(): FieldInterface
    {
        /** @var Page $page */
        $page = $this->admin->getSubject();
        $posts = $this->entityManager()->getRepository(SocialPost::class)->findBy(['host' => $page->host, 'page' => $page->slug], ['network' => 'ASC']);
        $heading = $this->currentRequest()->attributes->getBoolean('repurpose_sidebar_has_heading')
            ? false
            : new TranslatableMessage('repurposeLinkedCarouselsCount', ['%count%' => \count($posts)]);

        return Field::new('repurposeCarousels', $heading)
            ->setVirtual(true)
            ->setValue($posts)
            ->setFormType(FormType::class)
            ->setFormTypeOptions(['mapped' => false, 'compound' => false, 'disabled' => true, 'data' => $posts, 'block_prefix' => 'repurpose_carousels'])
            ->setRequired(false)
            ->addFormTheme('@PushwordRepurpose/admin/page_carousels_form.html.twig')
            ->onlyWhenUpdating();
    }
}
