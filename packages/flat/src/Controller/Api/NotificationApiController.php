<?php

namespace Pushword\Flat\Controller\Api;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Flat\Entity\AdminNotification;
use Pushword\Flat\Repository\AdminNotificationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class NotificationApiController extends AbstractApiController
{
    public function __construct(
        private readonly AdminNotificationRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/notification', name: 'pushword_api_notification_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $qb = $this->repository->createQueryBuilder('n');

        if (null !== $request->query->get('host')) {
            $qb->andWhere('n.host = :host')->setParameter('host', $request->query->getString('host'));
        }

        if (null !== $request->query->get('type')) {
            $qb->andWhere('n.type = :type')->setParameter('type', $request->query->getString('type'));
        }

        if ($request->query->has('unread') && $request->query->getBoolean('unread')) {
            $qb->andWhere('n.isRead = false');
        }

        $total = (int) (clone $qb)->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();
        $qb->orderBy('n.createdAt', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        $items = array_map($this->toArray(...), $qb->getQuery()->getResult());

        return $this->respond($this->paginated($items, $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/notification/{id}', name: 'pushword_api_notification_item', requirements: ['id' => '\d+'], methods: ['GET', 'DELETE'])]
    public function item(int $id, Request $request): JsonResponse
    {
        $notification = $this->repository->find($id);
        if (! $notification instanceof AdminNotification) {
            return $this->notFound('Notification not found');
        }

        if ('DELETE' === $request->getMethod()) {
            $this->entityManager->remove($notification);
            $this->entityManager->flush();

            return $this->noContent();
        }

        return $this->respond($this->toArray($notification));
    }

    #[Route('/api/notification/{id}/read', name: 'pushword_api_notification_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(int $id): JsonResponse
    {
        $notification = $this->repository->find($id);
        if (! $notification instanceof AdminNotification) {
            return $this->notFound('Notification not found');
        }

        $notification->markAsRead();
        $this->entityManager->flush();

        return $this->respond($this->toArray($notification), Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(AdminNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'message' => $notification->message,
            'host' => $notification->host,
            'isRead' => $notification->isRead,
            'createdAt' => $notification->createdAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/notification' => [
                    'get' => [
                        'summary' => 'List admin notifications',
                        'parameters' => [
                            ['name' => 'host', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'type', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'unread', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/api/notification/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => ['summary' => 'Get a notification', 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['summary' => 'Delete a notification', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
                '/api/notification/{id}/read' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Mark notification as read', 'responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ];
    }
}
