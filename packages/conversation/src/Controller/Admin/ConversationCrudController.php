<?php

namespace Pushword\Conversation\Controller\Admin;

use DateTime;
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
use Pushword\Admin\FormField\CustomPropertiesField;
use Pushword\Admin\FormField\HostField;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\FormField\ConversationTagsField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
        yield DateTimeField::new('publishedAt', 'admin.conversation.label.publishedAt')
            ->setSortable(true)
            ->setTemplatePath('@pwAdmin/components/published_toggle.html.twig');

        yield TextField::new('referring', 'admin.conversation.referring.label')
            ->setSortable(false);
        yield TextField::new('content', 'admin.conversation.content.label')
            ->setSortable(false);
        yield TextField::new('host', 'admin.page.host.label')
            ->setSortable(false);
        yield TextField::new('tags', 'admin.conversation.tags.label')
            ->setSortable(false)
            ->formatValue(static function (mixed $value, mixed $entity): string {
                if (! \is_object($entity) || ! method_exists($entity, 'getTags')) {
                    return '';
                }

                $tags = $entity->getTags();

                return \is_string($tags) ? $tags : '';
            });
        yield TextField::new('authorEmail', 'admin.conversation.authorEmail.label')
            ->setSortable(false);
        yield TextField::new('authorName', 'admin.conversation.authorName.label')
            ->setSortable(false);
        yield TextField::new('authorIpRaw', 'admin.conversation.authorIpRaw.label')
            ->setSortable(false);
        yield DateTimeField::new('createdAt', 'admin.conversation.createdAt.label')
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

        $fields[] = TextField::new('referring', 'admin.conversation.referring.label')
            ->setFormTypeOption('required', false);

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
        yield DateTimeField::new('publishedAt', 'admin.conversation.label.publishedAt')
            ->setFormTypeOption('html5', true)
            ->setFormTypeOption('widget', 'single_text')
            ->setFormTypeOption('required', false);

        yield DateTimeField::new('createdAt', 'admin.conversation.createdAt.label')->setDisabled();

        yield FormField::addFieldset('admin.page.customProperties.label')
                    ->setCssClass('pw-settings-accordion pw-settings-open');
        $customPropertiesField = new CustomPropertiesField($this->adminFormFieldManager, $this);
        $customPropertiesEasyAdminField = $customPropertiesField->getEasyAdminField();
        if ($customPropertiesEasyAdminField instanceof FieldInterface) {
            yield $customPropertiesEasyAdminField->setLabel(false); // @phpstan-ignore-line
        }
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

    #[Route(path: '/admin/conversation/{id}/toggle-published', name: 'pushword_conversation_toggle_publish', methods: ['POST'])]
    public function togglePublished(Request $request, Message $message): Response
    {
        $token = (string) $request->request->get('_token');

        if (! $this->isCsrfTokenValid($this->getPublishedToggleTokenId($message), $token)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $shouldPublish = $this->normalizePublishedState((string) $request->request->get('published'));

        $message->setPublishedAt($shouldPublish ? new DateTime() : null);

        $this->getEntityManager()->flush();

        // Re-render the toggle after update
        return new Response($this->adminFormFieldManager->twig->render('@pwAdmin/components/published_toggle.html.twig', [
            'entity' => ['instance' => $message],
            'value' => $message->getPublishedAt(),
            'field' => null,
        ]));
    }

    private function getPublishedToggleTokenId(Message $message): string
    {
        return 'conversation_toggle_published_'.$message->getId();
    }
}
