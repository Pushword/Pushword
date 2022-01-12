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

    /** @required */
    public AppPool $apps;

    /** @required */
    public ManagerRegistry $doctrine;

    /** @required */
    public TranslatorInterface $translator;

    /** @required */
    public RequestStack $requestStack;

    private FactoryInterface $factory;

    public function __construct(FactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function getMenu(): ItemInterface
    {
        $factory = $this->factory;
        $hosts = $this->apps->getHosts();

        $menu = $factory->createItem('content', [
            'label' => $this->translator->trans('admin.label.content'),
        ]);

        $pageMenu = $menu->addChild($this->translator->trans('admin.label.page'), ['route' => 'admin_app_page_list']);

        if (\count($hosts) > 1) {
            foreach ($hosts as $host) {
                $pageMenu->addChild($host, [
                    'route' => 'admin_app_page_list',
                    'routeParameters' => ['filter[host][value][]' => $host],
                    'attributes' => $this->isEditing($host) ? ['class' => 'active'] : [],
                ]);
            }
        }

        $menu->addChild($this->translator->trans('admin.label.media'), ['route' => 'admin_app_media_list']);

        if (class_exists(PushwordConversationBundle::class)) { // TODO : move it to an event listerner in conversation bundle and create event here
            $menu->addChild($this->translator->trans('admin.label.conversation'), ['route' => 'admin_pushword_conversation_message_list']);
        }

        return $menu;
    }

    private function isEditing(string $host): bool
    {
        if (($request = $this->requestStack->getCurrentRequest()) !== null
            && ($filter = $request->query->get('filter')) !== null) {
            return $filter['host']['value'][0] === $host;
        }

        return false;
    }
}
