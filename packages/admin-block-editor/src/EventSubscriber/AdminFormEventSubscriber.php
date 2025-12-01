<?php

namespace Pushword\AdminBlockEditor\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Admin\FormField\PageMainContentField;
use Pushword\Admin\Utils\FormFieldReplacer;
use Pushword\AdminBlockEditor\FormField\PageInlineMediaField;
use Pushword\AdminBlockEditor\FormField\PageMainContentEditorJsField;
use Pushword\Core\Entity\Page;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @template-covariant T of Page
 */
class AdminFormEventSubscriber extends AbstractEventSubscriber
{
    public function __construct(
        public bool $editorBlockForNewPage,
        public readonly RequestStack $requestStack,
    ) {
        parent::__construct($editorBlockForNewPage);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'pushword.admin.load_field' => 'replaceFields',
            BeforeEntityPersistedEvent::class => 'setMainContent',
            BeforeEntityUpdatedEvent::class => 'setMainContent',
        ];
    }

    /**
     * @param BeforeEntityPersistedEvent<object>|BeforeEntityUpdatedEvent<object>|FormEvent<object> $event
     */
    private function getPage(BeforeEntityPersistedEvent|BeforeEntityUpdatedEvent|FormEvent $event): ?Page
    {
        $subject = $event instanceof FormEvent ? $event->getAdmin()->getSubject() : $event->getEntityInstance();
        if ($subject instanceof Page) {
            return $subject;
        }

        return null;
    }

    /**
     * @param BeforeEntityPersistedEvent<object>|BeforeEntityUpdatedEvent<object> $event
     */
    public function setMainContent(BeforeEntityPersistedEvent|BeforeEntityUpdatedEvent $event): void
    {
        $page = $this->getPage($event);

        if (null === $page) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        /** @var array<string, array<mixed>|bool|float|int|string> $payload */
        $payload = $request->request->all();

        $mainContent = $this->extractMainContentValue($payload);

        if (null === $mainContent) {
            return;
        }

        $page->setMainContent($mainContent);
    }

    /**
     * @param FormEvent<object> $formEvent
     */
    public function replaceFields(FormEvent $formEvent): void
    {
        $page = $this->getPage($formEvent);

        if (null === $page) {
            return;
        }

        if (! $formEvent->getAdmin() instanceof PageCrudController) {
            return;
        }

        if (! $this->mayUseEditorBlock($page, $formEvent)) {
            return;
        }

        if (isset($_GET['disableEditorJs'])) {
            return;
        }

        $fields = $formEvent->getFields();
        $replacer = new FormFieldReplacer();
        $replacer->run(PageMainContentField::class, PageMainContentEditorJsField::class, $fields);

        if (isset($fields[0]) && \is_array($fields[0])) {
            $fields[0][] = PageInlineMediaField::class;
        }

        // @phpstan-ignore-next-line
        $formEvent->setFields($fields);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractMainContentValue(array $payload): ?string
    {
        if (isset($payload['mainContent']) && \is_string($payload['mainContent'])) {
            return $payload['mainContent'];
        }

        foreach ($payload as $value) {
            if (! \is_array($value)) {
                continue;
            }

            /** @var array<string, mixed> $value */
            $mainContent = $this->extractMainContentValue($value);

            if (null !== $mainContent) {
                return $mainContent;
            }
        }

        return null;
    }
}
