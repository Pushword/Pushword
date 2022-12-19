<?php

namespace Pushword\Admin\Menu;

use Doctrine\Persistence\ManagerRegistry;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Pushword\Conversation\PushwordConversationBundle;
use Pushword\Core\Component\App\AppPool;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PageMenuProvider implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public AppPool $apps;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public ManagerRegistry $doctrine;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public TranslatorInterface $translator;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public RequestStack $requestStack;

    public function __construct(private readonly FactoryInterface $factory)
    {
    }

    public function getMenu(): ItemInterface
    {
        $factory = $this->factory;
        $hosts = $this->apps->getHosts();

        $menu = $factory->createItem('content', [
            'label' => $this->translator->trans('admin.label.content'),
        ])
            ->setCurrent(true) // dirty hack to prevent bug
            ->setExtra('keep_open', true);

        $pageMenu = $menu->addChild(
            $this->translator->trans('admin.label.page'),
            [
                'route' => 'admin_app_page_list',
            ]
        );

        if (\count($hosts) > 1) {
            foreach ($hosts as $host) {
                $hostMenu = $pageMenu->addChild($host, [
                    'route' => 'admin_app_page_list',
                    'routeParameters' => ['filter[host][value][]' => $host],
                ]);
                if ($this->isRequestingPageEdit($host)) {
                    $hostMenu->setCurrent(true);
                }
            }
        } elseif ($this->isRequestingPageEdit()) {
            $pageMenu->setCurrent(true);
        }

        $menu->addChild($this->translator->trans('admin.label.media'), ['route' => 'admin_app_media_list']);

        if (class_exists(PushwordConversationBundle::class)) { // TODO : move it to an event listerner in conversation bundle and create event here
            $menu->addChild($this->translator->trans('admin.label.conversation'), ['route' => 'admin_pushword_conversation_message_list']);
        }

        return $menu;
    }

    private function isRequestingPageEdit(string $host = ''): bool
    {
        if ('' === $host) {
            return null !== $this->apps->getCurrentPage();
        }

        if (null !== $this->apps->getCurrentPage() && $this->apps->getCurrentPage()->getHost() === $host) {
            return true;
        }

        if (($request = $this->requestStack->getCurrentRequest()) === null) {
            return false;
        }

        if (($hostInRequest = $this->extractHostFilter($request->query->all())) === null) {
            return false;
        }

        return $hostInRequest === $host;
    }

    /**
     * @param mixed[] $query
     */
    private function extractHostFilter(array $query): ?string
    {
        if (
            isset($query['filter']) && \is_array($query['filter']) &&
            isset($query['filter']['host']) && \is_array($query['filter']['host']) &&
            isset($query['filter']['host']['value'])) {
            if (\is_array($query['filter']['host']['value']) && isset($query['filter']['host']['value'][0]) && \is_string($query['filter']['host']['value'][0])) {
                return $query['filter']['host']['value'][0];
            }

            if (\is_string($query['value'])) {
                return $query['value'];
            }
        }

        return null;
    }
}
