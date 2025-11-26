<?php

namespace Pushword\Conversation\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Admin\Controller\AbstractAdminCrudController;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\FormField\ConversationTagsField;

/** @extends AbstractAdminCrudController<Message> */
final class ConversationCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Message::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('admin.label.conversation')
            ->setEntityLabelInPlural('admin.label.conversation')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return $this->getIndexFields();
        }

        return $this->getFormFieldsDefinition();
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getIndexFields(): iterable
    {
        yield TextField::new('referring', 'Referring');
        yield TextField::new('content', 'Content');
        yield TextField::new('tags', 'admin.conversation.tags.label')
            ->formatValue(static function (mixed $value, mixed $entity): string {
                if (! \is_object($entity) || ! method_exists($entity, 'getTags')) {
                    return '';
                }

                $tags = $entity->getTags();

                return \is_string($tags) ? $tags : '';
            });
        yield TextField::new('authorEmail', 'Author Email');
        yield TextField::new('authorName', 'Author Name');
        yield TextField::new('authorIpRaw', 'Author Ip Raw');
        yield DateTimeField::new('createdAt', 'Created At')
            ->setSortable(true);
        yield DateTimeField::new('publishedAt', 'Published At')
            ->setSortable(true);
    }

    /**
     * @return iterable<FieldInterface>
     */
    private function getFormFieldsDefinition(): iterable
    {
        $instance = $this->getContext()?->getEntity()?->getInstance();
        $message = $instance instanceof Message ? $instance : null;
        $this->setSubject($message ?? new Message());

        $tagsField = new ConversationTagsField($this->adminFormFieldManager, $this);
        $tagsEasyAdminField = $tagsField->getEasyAdminField();

        yield FormField::addColumn('col-12 col-xl-8 mainFields');
        yield FormField::addFieldset()
            ->setCssClass('pw-settings-accordion pw-settings-open');
        yield TextareaField::new('content', 'admin.conversation.content.label')
            ->setFormTypeOption('attr', ['rows' => 6]);
        if ($tagsEasyAdminField instanceof FieldInterface) {
            yield $tagsEasyAdminField;
        }

        yield TextField::new('referring', 'admin.conversation.referring.label');
        yield DateTimeField::new('createdAt', 'admin.conversation.createdAt.label');

        yield FormField::addColumn('col-12 col-xl-4 columnFields');
        yield FormField::addFieldset('admin.conversation.label.author')
            ->setCssClass('pw-settings-accordion pw-settings-open');
        yield TextField::new('authorEmail', 'admin.conversation.authorEmail.label');
        yield TextField::new('authorName', 'admin.conversation.authorName.label')
            ->setFormTypeOption('required', false);
        yield TextField::new('authorIpRaw', 'admin.conversation.authorIp.label')
            ->setFormTypeOption('disabled', null !== $message?->getAuthorIp());

        yield FormField::addFieldset('admin.conversation.label.publishedAt')
            ->setCssClass('pw-settings-accordion pw-settings-open');
        yield DateTimeField::new('publishedAt', 'admin.conversation.publishedAt.label')
            ->setFormTypeOption('html5', true)
            ->setFormTypeOption('widget', 'single_text')
            ->setFormTypeOption('required', false);
    }
}
