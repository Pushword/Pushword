<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Exception;
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
 * @template-covariant T of Page
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
     * @param PersistenceEvent<object>|FormEvent<object>|PersistenceEvent<Page>|FormEvent<Page> $event
     */
    private function getPage(PersistenceEvent|FormEvent $event): ?Page
    {
        $subject = $event->getAdmin()->getSubject();
        if ($subject instanceof Page) {
            return $subject;
        }

        return null;
    }

    /**
     * @param PersistenceEvent<object> $persistenceEvent
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
     * @param FormEvent<Page> $formEvent
     */
    public function replaceFields(FormEvent $formEvent): void
    {
        $page = $this->getPage($formEvent);

        if (null === $page) {
            return;
        }

        if (! $this->mayUseEditorBlock($page, $formEvent)) {
            return;
        }

        $fields = $formEvent->getFields();

        // @phpstan-ignore-next-line
        (new FormFieldReplacer())->run(PageMainContentField::class, PageMainContentFormField::class, $fields);
        (new FormFieldReplacer())->run(PageH1Field::class, PageH1FormField::class, $fields);

        if (! isset($fields[0]) || ! is_array($fields[0])) {
            throw new Exception();
        }

        $fields[0][PageImageFormField::class] = PageImageFormField::class;

        // @phpstan-ignore-next-line
        $formEvent->setFields($fields);
    }
}
