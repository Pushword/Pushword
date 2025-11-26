<?php

namespace Pushword\AdvancedMainImage\EventSuscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Admin\FormField\PageMainImageField;
use Pushword\Admin\Utils\FormFieldReplacer;
use Pushword\AdvancedMainImage\PageAdvancedMainImageFormField;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @template T of object
 */
final readonly class AdminFormEventSuscriber implements EventSubscriberInterface
{
    public function __construct(
        private AppPool $apps,
        public RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'pushword.admin.load_field' => 'replaceFields',
            BeforeEntityPersistedEvent::class => 'setAdvancedMainImage',
            BeforeEntityUpdatedEvent::class => 'setAdvancedMainImage',
        ];
    }

    /**
     * @param FormEvent<object> $formEvent
     */
    public function replaceFields(FormEvent $formEvent): void
    {
        $page = $formEvent->getAdmin()->getSubject();

        if (! $page instanceof Page) {
            return;
        }

        if (false === $this->apps->get($page->getHost())->get('advanced_main_image')) {
            return;
        }

        $fields = $formEvent->getFields();
        (new FormFieldReplacer())->run(PageMainImageField::class, PageAdvancedMainImageFormField::class, $fields);

        // @phpstan-ignore-next-line
        $formEvent->setFields($fields);
    }

    /**
     * @param BeforeEntityPersistedEvent<object>|BeforeEntityUpdatedEvent<object>|object $event
     */
    public function setAdvancedMainImage(object $event): void
    {
        if (! $event instanceof BeforeEntityPersistedEvent && ! $event instanceof BeforeEntityUpdatedEvent) {
            return;
        }

        $entity = $event->getEntityInstance();

        if (! $entity instanceof Page) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        /** @var array<string, array<mixed>|bool|float|int|string> $returnValues */
        $returnValues = $request->request->all();
        $value = $this->extractSubmittedValue($returnValues, 'mainImageFormat');

        if (null === $value) {
            return;
        }

        $entity->setCustomProperty('mainImageFormat', (int) $value);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function extractSubmittedValue(array $values, string $needle): ?string
    {
        $candidate = $values[$needle] ?? null;

        if (\is_scalar($candidate) && '' !== (string) $candidate) {
            return (string) $candidate;
        }

        foreach ($values as $value) {
            if (! \is_array($value)) {
                continue;
            }

            /** @var array<string, mixed> $value */
            $result = $this->extractSubmittedValue($value, $needle);
            if (null !== $result) {
                return $result;
            }
        }

        return null;
    }
}
