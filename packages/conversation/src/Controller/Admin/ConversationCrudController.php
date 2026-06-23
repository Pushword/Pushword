<?php

namespace Pushword\Conversation\Controller\Admin;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use LogicException;
use Override;
use Pushword\Admin\Controller\AbstractAdminCrudController;
use Pushword\Admin\FormField\CustomPropertiesField;
use Pushword\Admin\FormField\HostField;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Entity\Review as ReviewEntity;
use Pushword\Conversation\FormField\ConversationTagsField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @extends AbstractAdminCrudController<Message> */
class ConversationCrudController extends AbstractAdminCrudController
{
    private ?AdminUrlGenerator $adminUrlGenerator = null;

    public static function getEntityFqcn(): string
    {
        return Message::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPaginatorPageSize($this->getRequestedPageSize())
            ->setEntityLabelInSingular('adminLabelConversation')
            ->setEntityLabelInPlural('adminLabelConversation')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_EDIT, Action::DELETE);
    }

    #[Override]
    public function configureAssets(Assets $assets): Assets
    {
        $labels = json_encode([
            'on' => $this->translator->trans('adminConversationPublishedToggleOnLabel'),
            'off' => $this->translator->trans('adminConversationPublishedToggleOffLabel'),
        ], \JSON_THROW_ON_ERROR);

        $script = str_replace('__PW_PUBLISH_LABELS__', $labels, <<<'HTML'
            <script>
            (function () {
                function init() {
                    var input = document.querySelector('form input[name$="[publishedAt]"]');
                    if (null === input || input.dataset.pwPublishToggle) {
                        return;
                    }
                    input.dataset.pwPublishToggle = '1';

                    var labels = __PW_PUBLISH_LABELS__;

                    function nowLocal() {
                        var now = new Date();
                        now.setSeconds(0, 0);
                        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                        return now.toISOString().slice(0, 16);
                    }

                    var lastValue = '' !== input.value ? input.value : nowLocal();

                    var wrap = document.createElement('div');
                    wrap.className = 'form-check form-switch mb-2';

                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'form-check-input';
                    checkbox.id = 'pw-publish-switch';
                    checkbox.checked = '' !== input.value;

                    var label = document.createElement('label');
                    label.className = 'form-check-label';
                    label.htmlFor = 'pw-publish-switch';

                    function syncLabel() {
                        label.textContent = checkbox.checked ? labels.on : labels.off;
                    }

                    checkbox.addEventListener('change', function () {
                        if (checkbox.checked) {
                            if ('' === input.value) {
                                input.value = lastValue;
                            }
                        } else {
                            if ('' !== input.value) {
                                lastValue = input.value;
                            }
                            input.value = '';
                        }
                        syncLabel();
                    });

                    input.addEventListener('change', function () {
                        if ('' !== input.value) {
                            lastValue = input.value;
                        }
                        checkbox.checked = '' !== input.value;
                        syncLabel();
                    });

                    syncLabel();
                    wrap.appendChild(checkbox);
                    wrap.appendChild(label);
                    input.parentNode.insertBefore(wrap, input);
                }

                if ('loading' !== document.readyState) {
                    init();
                } else {
                    document.addEventListener('DOMContentLoaded', init);
                }
            })();
            </script>
            HTML);

        return $assets->addHtmlContentToBody($script);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        if ($this->hasMultipleHosts()) {
            $filters->add(
                ChoiceFilter::new('host', 'adminPageHostLabel')
                    ->setChoices($this->getHostChoices()),
            );
        }

        $filters
            ->add(TextFilter::new('tags', 'adminConversationTagsLabel'))
            ->add(TextFilter::new('content', 'adminConversationContentLabel'))
            ->add(TextFilter::new('weight', 'adminConversationWeightLabel'));

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
        yield DateTimeField::new('publishedAt', 'adminConversationLabelPublishedAt')
            ->setSortable(true)
            ->setTemplatePath('@pwAdmin/components/published_toggle.html.twig');

        yield IntegerField::new('weight', 'adminConversationWeightLabel')
            ->setSortable(true)
            ->setTemplatePath('@pwAdmin/components/weight_inline_field.html.twig');

        yield TextField::new('content', 'adminConversationContentLabel')
            ->setSortable(false)
            ->setTemplatePath('@PushwordConversation/admin/messageListTitleField.html.twig');

        yield DateTimeField::new('createdAt', 'adminConversationCreatedAtLabel')
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

        $fields[] = TextareaField::new('content', 'adminConversationContentLabel')
            ->setFormTypeOption('attr', ['rows' => 6]);

        $hostField = new HostField($this->adminFormFieldManager, $this);
        $hostEasyAdminField = $hostField->getEasyAdminField();
        if ($hostEasyAdminField instanceof FieldInterface) {
            $fields[] = $hostEasyAdminField;
        }

        $fields[] = TextField::new('referring', 'adminConversationReferringLabel')
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
        yield FormField::addFieldset('adminConversationLabelAuthor')
            ->setCssClass('pw-settings-accordion pw-settings-open');
        yield TextField::new('authorEmail', 'adminConversationAuthorEmailLabel');
        yield TextField::new('authorName', 'adminConversationAuthorNameLabel')
            ->setFormTypeOption('required', false);
        yield TextField::new('authorIpRaw', 'adminConversationAuthorIpLabel')
            ->setFormTypeOption('disabled', null !== $message->getAuthorIp());

        yield FormField::addFieldset('adminConversationLabelPublishedAt')
            ->setCssClass('pw-settings-accordion pw-settings-open');
        yield DateTimeField::new('publishedAt', 'adminConversationLabelPublishedAt')
            ->setFormTypeOption('html5', true)
            ->setFormTypeOption('widget', 'single_text')
            ->setFormTypeOption('required', false);
        yield IntegerField::new('weight', 'adminConversationWeightLabel')
            ->setFormTypeOption('required', false)
            ->setHelp('adminConversationWeightHelp');

        yield DateTimeField::new('createdAt', 'adminConversationCreatedAtLabel')->setDisabled();

        yield FormField::addFieldset('adminPageCustomPropertiesLabel')
                    ->setCssClass('pw-settings-accordion pw-settings-open');
        $customPropertiesField = new CustomPropertiesField($this->adminFormFieldManager, $this);
        $customPropertiesEasyAdminField = $customPropertiesField->getEasyAdminField();
        if ($customPropertiesEasyAdminField instanceof FieldInterface) {
            yield $customPropertiesEasyAdminField->setLabel(false); // @phpstan-ignore-line
        }
    }

    #[Override]
    protected function getEntityTypeIdentifier(): string
    {
        return 'conversation';
    }

    #[Route(path: '/admin/conversation/{id}/toggle-published', name: 'pushword_conversation_toggle_publish', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function togglePublished(Request $request, Message $message): Response
    {
        $token = (string) $request->request->get('_token');

        if (! $this->isCsrfTokenValid($this->getPublishedToggleTokenId($message), $token)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $shouldPublish = $this->normalizePublishedState((string) $request->request->get('published'));

        $message->setPublishedAt($shouldPublish ? new DateTime() : null);

        $this->getEntityManager()->flush();

        return new Response($this->adminFormFieldManager->twig->render('@pwAdmin/components/published_toggle.html.twig', [
            'entity' => ['instance' => $message],
            'value' => $message->getPublishedAt(),
            'field' => null,
        ]));
    }

    #[Route(path: '/admin/conversation/{id}/inline-update', name: 'pushword_conversation_inline_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function inlineUpdate(Request $request, Message $message): Response
    {
        $token = (string) $request->request->get('_token');

        if (! $this->isCsrfTokenValid($this->getInlineTokenId($message), $token)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $field = trim((string) $request->request->get('field', ''));
        $value = (string) $request->request->get('value', '');

        if ('' === $field || ! $this->applyInlineUpdate($message, $field, $value)) {
            return new Response('Field not editable.', Response::HTTP_BAD_REQUEST);
        }

        $this->applyDefaultReplyAuthor($message);

        $this->getEntityManager()->flush();

        $template = 'weight' === $field
            ? '@pwAdmin/components/weight_inline_field.html.twig'
            : '@PushwordConversation/admin/messageListTitleField.html.twig';

        return new Response($this->adminFormFieldManager->twig->render($template, [
            'entity' => ['instance' => $message],
            'value' => 'weight' === $field ? $message->getWeight() : null,
            'field' => null,
        ]));
    }

    #[Override]
    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->applyDefaultReplyAuthor($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }

    #[Override]
    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->applyDefaultReplyAuthor($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * When a review has a reply but no explicit reply author, fall back to the
     * host's configured default (conversation_review_default_reply_author).
     */
    private function applyDefaultReplyAuthor(object $message): void
    {
        if (! $message instanceof ReviewEntity) {
            return;
        }

        if ('' === $message->getReply() || '' !== $message->getReplyAuthor()) {
            return;
        }

        $default = $this->apps->get($message->host)->getStr('conversation_review_default_reply_author');

        if ('' !== $default) {
            $message->setReplyAuthor($default);
        }
    }

    #[Route(path: '/admin/conversation/{id}/media/{mediaId}/unlink', name: 'pushword_conversation_media_unlink', requirements: ['id' => '\d+', 'mediaId' => '\d+'], methods: ['POST'])]
    public function unlinkMedia(Request $request, Message $message, int $mediaId): Response
    {
        $token = (string) $request->request->get('_token');

        if (! $this->isCsrfTokenValid('conversation_media_unlink_'.($message->id ?? 0), $token)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        foreach ($message->getMediaList() as $media) {
            if ($media->id === $mediaId) {
                $message->removeMedia($media);

                break;
            }
        }

        $this->getEntityManager()->flush();

        return new Response($this->adminFormFieldManager->twig->render('@PushwordConversation/admin/messageListMediaGallery.html.twig', [
            'message' => $message,
        ]));
    }

    private function applyInlineUpdate(Message $message, string $field, string $value): bool
    {
        $trimmed = trim($value);

        return match ($field) {
            'content' => $this->updateContent($message, $value),
            'authorName' => $this->updateNullableString(static fn (?string $val): Message => $message->setAuthorName($val), $trimmed),
            'authorEmail' => $this->updateNullableString(static fn (?string $val): Message => $message->setAuthorEmail($val), $trimmed),
            'referring' => $this->updateNullableString(static fn (?string $val): Message => $message->setReferring($val), $trimmed),
            'host' => $this->updateNullableString(static function (?string $val) use ($message): Message {
                $message->host = $val;

                return $message;
            }, $trimmed),
            'tags' => $this->updateTags($message, $value),
            'weight' => $message->setWeight($value),
            'title' => $this->updateReviewField($message, $trimmed, 'title'),
            'rating' => $this->updateReviewField($message, $trimmed, 'rating'),
            'reply' => $this->updateReviewField($message, $trimmed, 'reply'),
            default => false,
        };
    }

    /**
     * @param callable(?string): Message $callback
     */
    private function updateNullableString(callable $callback, string $value): bool
    {
        $callback('' === $value ? null : $value);

        return true;
    }

    private function updateContent(Message $message, string $value): bool
    {
        $message->setContent($value);

        return true;
    }

    private function updateTags(Message $message, string $value): bool
    {
        $message->setTags($value);

        return true;
    }

    private function updateReviewField(Message $message, string $value, string $field): bool
    {
        if (! $message instanceof ReviewEntity) {
            return false;
        }

        if ('title' === $field) {
            $message->setTitle('' === $value ? null : $value);

            return true;
        }

        if ('reply' === $field) {
            $message->setReply('' === $value ? null : $value);

            return true;
        }

        if ('rating' === $field) {
            if ('' === $value) {
                $message->setRating(null);

                return true;
            }

            if (! \is_numeric($value)) {
                return false;
            }

            $intValue = max(1, min(5, (int) $value));
            $message->setRating($intValue);

            return true;
        }

        return false;
    }

    #[Override]
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        if ($context->getRequest()->query->has('pwInline')) {
            $entityId = $context->getEntity()->getPrimaryKeyValue();
            if (null !== $entityId) {
                $controller = $context->getCrud()?->getControllerFqcn() ?? static::class;

                $url = $this->getAdminUrlGenerator()
                    ->setController($controller)
                    ->setAction(Action::EDIT)
                    ->setEntityId($entityId)
                    ->set('pwInline', 1)
                    ->set('pwInlineSaved', 1)
                    ->generateUrl();

                return new RedirectResponse($url);
            }
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    private function getAdminUrlGenerator(): AdminUrlGenerator
    {
        if (null !== $this->adminUrlGenerator) {
            return $this->adminUrlGenerator;
        }

        if (! isset($this->container)) {
            throw new LogicException('AdminUrlGenerator is not available.');
        }

        /** @var AdminUrlGenerator $generator */
        $generator = $this->container->get(AdminUrlGenerator::class);
        $this->adminUrlGenerator = $generator;

        return $this->adminUrlGenerator;
    }
}
