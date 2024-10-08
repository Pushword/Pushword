<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Admin\FormField\PageH1Field;
use Pushword\Admin\FormField\PageMainContentField;
use Pushword\Admin\Utils\FormFieldReplacer;
use Pushword\AdminBlockEditor\EditorJsPurifier;
use Pushword\AdminBlockEditor\FormField\PageH1FormField;
use Pushword\AdminBlockEditor\FormField\PageImageFormField;
use Pushword\AdminBlockEditor\FormField\PageMainContentFormField;
use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Event\PersistenceEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @template T of object
 */
class AdminFormEventSuscriber extends AbstractEventSuscriber
{
    public function __construct(public bool $editorBlockForNewPage, private readonly RequestStack $requestStack)
    {
        parent::__construct($editorBlockForNewPage);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'pushword.admin.load_field' => 'replaceFields',
            'sonata.admin.event.persistence.pre_update' => 'setMainContent',
            'sonata.admin.event.persistence.pre_persist' => 'setMainContent',
        ];
    }

    /**
     * @param PersistenceEvent<T>|FormEvent<T> $event
     */
    private function getPage(PersistenceEvent|FormEvent $event): ?Page
    {
        $subject = $event->getAdmin()->getSubject();
        if ($subject instanceof Page) { //  $event->getAdmin() instanceof PageAdmin || $event->getAdmin() instanceof PageCheatSheetAdmin
            return $subject;
        }

        return null;
    }

    /**
     * @param PersistenceEvent<T> $persistenceEvent
     */
    public function setMainContent(PersistenceEvent $persistenceEvent): void
    {
        $page = $this->getPage($persistenceEvent);
        if (null === $page) {
            return;
        }

        $requestUniqId = (string) $persistenceEvent->getAdmin()->getRequest()->query->get('uniqid');
        $returnValues = $persistenceEvent->getAdmin()->getRequest()->request->all($requestUniqId);
        if (! isset($returnValues['mainContent'])) {
            return;
        }

        if (! \is_string($returnValues['mainContent'])) {
            return;
        }

        $base = $this->requestStack->getCurrentRequest()?->getSchemeAndHttpHost() ?? '';

        // sanitize with https://github.com/editor-js/editorjs-phpstan
        $returnValues['mainContent'] = (new EditorJsPurifier($page->getLocale() ?: 'fr', $page, $base))($returnValues['mainContent']); // @phpstan-ignore-line

        $page->setMainContent($returnValues['mainContent']);
    }

    /**
     * @psalm-suppress  InvalidArgument // use only phpstan
     *
     * @param FormEvent<T> $formEvent
     */
    public function replaceFields(FormEvent $formEvent): void
    {
        $page = $this->getPage($formEvent);
        if (null === $page) {
            return;
        }

        /** @var FormEvent<Page> */
        $formEventPage = $formEvent;

        if (! $this->mayUseEditorBlock($page, $formEventPage)) {
            return;
        }

        $fields = $formEvent->getFields();

        (new FormFieldReplacer())->run(PageMainContentField::class, PageMainContentFormField::class, $fields);
        (new FormFieldReplacer())->run(PageH1Field::class, PageH1FormField::class, $fields);

        // if (! \is_array($fields[0])) { throw new \LogicException(); }

        $fields[0][PageImageFormField::class] = PageImageFormField::class;

        $formEvent->setFields($fields);
    }
}
