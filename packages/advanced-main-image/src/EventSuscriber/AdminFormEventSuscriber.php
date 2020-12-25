<?php

namespace Pushword\AdvancedMainImage\EventSuscriber;

use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Admin\FormField\PageMainImageField;
use Pushword\Admin\PageAdmin;
use Pushword\Admin\Utils\FormFieldReplacer;
use Pushword\AdvancedMainImage\PageAdvancedMainImageFormField;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Event\PersistenceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @template T of object
 */
final readonly class AdminFormEventSuscriber implements EventSubscriberInterface
{
    public function __construct(private AppPool $apps)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'pushword.admin.load_field' => 'replaceFields',
            'sonata.admin.event.persistence.pre_update' => 'setAdvancedMainImage',
            'sonata.admin.event.persistence.pre_persist' => 'setAdvancedMainImage',
        ];
    }

    /**
     * @psalm-suppress  InvalidArgument // use only phpstan
     *
     * @param FormEvent<T> $formEvent
     */
    public function replaceFields(FormEvent $formEvent): void
    {
        /** @var Page $page */
        $page = $formEvent->getAdmin()->getSubject();

        if (false === $this->apps->get($page->getHost())->get('advanced_main_image')) {
            return;
        }

        $fields = $formEvent->getFields();
        (new FormFieldReplacer())->run(PageMainImageField::class, PageAdvancedMainImageFormField::class, $fields);

        $formEvent->setFields($fields);
    }

    /**
     * @param PersistenceEvent<T> $persistenceEvent
     *
     * @psalm-suppress RedundantCondition
     */
    public function setAdvancedMainImage(PersistenceEvent $persistenceEvent): void
    {
        if (! $persistenceEvent->getAdmin() instanceof PageAdmin) {
            return;
        }

        $returnValues = $persistenceEvent->getAdmin()->getRequest()->request
            ->all($persistenceEvent->getAdmin()->getRequest()->query->getString('uniqid') ?: null); // @phpstan-ignore-line

        $persistenceEvent->getAdmin()->getSubject()->setCustomProperty(
            'mainImageFormat',
            isset($returnValues['mainImageFormat']) ? (int) ($returnValues['mainImageFormat']) : 0 // @phpstan-ignore-line
        );
    }
}
