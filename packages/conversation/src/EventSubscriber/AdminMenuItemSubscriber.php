<?php

namespace Pushword\Conversation\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\CrudMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Pushword\Admin\Menu\AdminMenuItemsEvent;
use Pushword\Conversation\Controller\Admin\ConversationCrudController;
use Pushword\Conversation\Controller\Admin\ReviewCrudController;
use Pushword\Conversation\Entity\Message;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class AdminMenuItemSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SiteRegistry $apps,
        private AdminContextProviderInterface $adminContextProvider,
        #[Autowire(param: 'pw.conversation.review_enabled')]
        private bool $reviewEnabled,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AdminMenuItemsEvent::NAME => 'onMenuItems',
        ];
    }

    public function onMenuItems(AdminMenuItemsEvent $event): void
    {
        if (! class_exists(Message::class)) {
            return;
        }

        $event->addMenuItem($this->buildConversationMenu(), 600);

        if ($this->reviewEnabled) {
            $event->addMenuItem($this->buildReviewMenu(), 580);
        }
    }

    private function buildConversationMenu(): MenuItemInterface
    {
        $hosts = $this->apps->getHosts();

        if (\count($hosts) <= 1) {
            return MenuItem::linkToCrud('adminLabelConversation', 'fa fa-comments', Message::class)
                ->setController(ConversationCrudController::class);
        }

        $subItems = [
            $this->createHiddenListItem(ConversationCrudController::class),
        ];

        foreach ($hosts as $host) {
            $subItems[] = $this->createHostMenuItem($host, ConversationCrudController::class);
        }

        return MenuItem::subMenu('adminLabelConversation', 'fa fa-comments')
            ->setSubItems($subItems);
    }

    private function buildReviewMenu(): MenuItemInterface
    {
        $hosts = $this->apps->getHosts();

        if (\count($hosts) <= 1) {
            return MenuItem::linkToCrud('adminLabelReview', 'fa fa-star', Message::class)
                ->setController(ReviewCrudController::class);
        }

        $subItems = [
            $this->createHiddenListItem(ReviewCrudController::class),
        ];

        foreach ($hosts as $host) {
            $subItems[] = $this->createHostMenuItem($host, ReviewCrudController::class);
        }

        return MenuItem::subMenu('adminLabelReview', 'fa fa-star')
            ->setSubItems($subItems);
    }

    private function createHiddenListItem(string $controller): CrudMenuItem
    {
        return MenuItem::linkToCrud('adminLabelList', 'fas fa-list', Message::class)
            ->setCssClass('d-none')
            ->setController($controller);
    }

    private function createHostMenuItem(string $host, string $controller): CrudMenuItem
    {
        $menuItem = MenuItem::linkToCrud($host, 'fa fa-globe', Message::class)
            ->setController($controller)
            ->setQueryParameter('filters[host]', [
                'comparison' => '=',
                'value' => $host,
            ]);

        if ($this->isHostActive($host, $controller)) {
            $menuItem->getAsDto()->setSelected(true);
        }

        return $menuItem;
    }

    private function isHostActive(string $host, string $controller): bool
    {
        $context = $this->adminContextProvider->getContext();

        if (null === $context) {
            return false;
        }

        $crud = $context->getCrud();
        if (null === $crud || $crud->getControllerFqcn() !== $controller) {
            return false;
        }

        /** @var array<string, mixed> $filters */
        $filters = $context->getRequest()->query->all(EA::FILTERS);
        $filteredHost = \is_array($filters['host'] ?? null) ? ($filters['host']['value'] ?? null) : null;
        if (\is_string($filteredHost)) {
            return $filteredHost === $host;
        }

        $entity = $context->getEntity()->getInstance();
        if (! \is_object($entity) || ! property_exists($entity, 'host')) {
            return false;
        }

        return $entity->host === $host;
    }
}
