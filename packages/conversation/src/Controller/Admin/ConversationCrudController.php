<?php

namespace Pushword\Conversation\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Override;
use Pushword\Admin\Controller\AbstractAdminCrudController;
use Pushword\Admin\FormField\HostField;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\FormField\ConversationTagsField;

/** @extends AbstractAdminCrudController<Message> */
class ConversationCrudController extends AbstractAdminCrudController
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
    public function configureFilters(Filters $filters): Filters
    {
        if ($this->hasMultipleHosts()) {
            $filters->add(
                ChoiceFilter::new('host', 'admin.page.host.label')
                    ->setChoices($this->getHostChoices()),
            );
        }

        return $filters;
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
    protected function getIndexFields(): iterable
    {
        yield TextField::new('referring', 'Referring');
        yield TextField::new('content', 'Content');
        yield TextField::new('host', 'admin.page.host.label');
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
     * @return array<FieldInterface>
     */
    protected function getMainFields(): array
    {
        $fields = [];

        $tagsField = new ConversationTagsField($this->adminFormFieldManager, $this);
        $tagsEasyAdminField = $tagsField->getEasyAdminField();
        if ($tagsEasyAdminField instanceof FieldInterface) {
            $fields[] = $tagsEasyAdminField;
        }

        $fields[] = TextareaField::new('content', 'admin.conversation.content.label')
            ->setFormTypeOption('attr', ['rows' => 6]);

        $hostField = new HostField($this->adminFormFieldManager, $this);
        $hostEasyAdminField = $hostField->getEasyAdminField();
        if ($hostEasyAdminField instanceof FieldInterface) {
            $fields[] = $hostEasyAdminField;
        }

        $fields[] = TextField::new('referring', 'admin.conversation.referring.label');

        return $fields;
    }

    #[Override]
    public function setSubject(?object $subject = null): object
    {
        if (! $subject instanceof Message) {
            $class = $this->getModelClass();
            $subject = new $class();
        }

        return parent::setSubject($subject);
    }

    /**
     * @return iterable<FieldInterface>
     */
    protected function getFormFieldsDefinition(): iterable
    {
        $instance = $this->getContext()?->getEntity()?->getInstance();
        $message = $instance instanceof Message ? $instance : null;
        if (null === $message) {
            $class = $this->getModelClass();
            $message = new $class();
        }

        /** @var Message $message */
        $this->setSubject($message);

        yield FormField::addColumn('col-12 col-xl-8 mainFields');
        yield FormField::addFieldset()
            ->setCssClass('pw-settings-accordion pw-settings-open');
        foreach ($this->getMainFields() as $field) {
            yield $field;
        }

        yield FormField::addColumn('col-12 col-xl-4 columnFields');
        yield FormField::addFieldset('admin.conversation.label.author')
            ->setCssClass('pw-settings-accordion pw-settings-open');
        yield TextField::new('authorEmail', 'admin.conversation.authorEmail.label');
        yield TextField::new('authorName', 'admin.conversation.authorName.label')
            ->setFormTypeOption('required', false);
        yield TextField::new('authorIpRaw', 'admin.conversation.authorIp.label')
            ->setFormTypeOption('disabled', null !== $message->getAuthorIp());

        yield FormField::addFieldset('admin.conversation.label.publishedAt')
            ->setCssClass('pw-settings-accordion pw-settings-open');
        yield DateTimeField::new('publishedAt', 'admin.conversation.publishedAt.label')
            ->setFormTypeOption('html5', true)
            ->setFormTypeOption('widget', 'single_text')
            ->setFormTypeOption('required', false);

        yield DateTimeField::new('createdAt', 'admin.conversation.createdAt.label')->setDisabled();
    }

    protected function hasMultipleHosts(): bool
    {
        return \count($this->apps->getHosts()) > 1;
    }

    /**
     * @return array<string, string>
     */
    protected function getHostChoices(): array
    {
        $hosts = $this->apps->getHosts();

        return [] === $hosts ? [] : array_combine($hosts, $hosts);
    }
}
