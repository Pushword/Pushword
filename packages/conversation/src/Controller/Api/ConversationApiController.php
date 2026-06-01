<?php

namespace Pushword\Conversation\Controller\Api;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Repository\MessageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_EDITOR')]
final class ConversationApiController extends AbstractApiController
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/conversation', name: 'pushword_api_conversation_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $qb = $this->messageRepository->createQueryBuilder('m');

        if (null !== $request->query->get('host')) {
            $qb->andWhere('m.host = :host')->setParameter('host', $request->query->getString('host'));
        }

        if (null !== $request->query->get('q')) {
            $qb->andWhere('m.content LIKE :q OR m.referring LIKE :q')
                ->setParameter('q', '%'.$request->query->getString('q').'%');
        }

        $total = (int) (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
        $qb->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        $items = array_map($this->toArray(...), $qb->getQuery()->getResult());

        return $this->respond($this->paginated($items, $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/conversation', name: 'pushword_api_conversation_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);

        $message = new Message();
        $this->apply($message, $data);

        $violations = $this->validator->validate($message);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $this->respond($this->toArray($message), Response::HTTP_CREATED);
    }

    #[Route('/api/conversation/{id}', name: 'pushword_api_conversation_item', requirements: ['id' => '\d+'], methods: ['GET', 'PATCH', 'DELETE'])]
    public function item(int $id, Request $request): JsonResponse
    {
        $message = $this->messageRepository->find($id);
        if (! $message instanceof Message) {
            return $this->notFound('Message not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->respond($this->toArray($message)),
            'PATCH' => $this->doUpdate($message, $request),
            'DELETE' => $this->doDelete($message),
            default => $this->respond(['error' => 'Method not allowed'], Response::HTTP_METHOD_NOT_ALLOWED),
        };
    }

    private function doUpdate(Message $message, Request $request): JsonResponse
    {
        $this->apply($message, $this->decodeJson($request));
        $violations = $this->validator->validate($message);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->flush();

        return $this->respond($this->toArray($message));
    }

    private function doDelete(Message $message): JsonResponse
    {
        $this->entityManager->remove($message);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(Message $message, array $data): void
    {
        if (\array_key_exists('content', $data) && \is_string($data['content'])) {
            $message->setContent($data['content']);
        }

        if (\array_key_exists('authorName', $data) && \is_string($data['authorName'])) {
            $message->setAuthorName($data['authorName']);
        }

        if (\array_key_exists('authorEmail', $data) && \is_string($data['authorEmail'])) {
            $message->setAuthorEmail($data['authorEmail']);
        }

        if (\array_key_exists('referring', $data) && \is_string($data['referring'])) {
            $message->setReferring($data['referring']);
        }

        if (\array_key_exists('locale', $data) && \is_string($data['locale'])) {
            $message->locale = $data['locale'];
        }

        if (\array_key_exists('host', $data) && \is_string($data['host'])) {
            $message->host = $data['host'];
        }

        if (\array_key_exists('publishedAt', $data) && \is_string($data['publishedAt'])) {
            try {
                $message->setPublishedAt(new DateTimeImmutable($data['publishedAt']));
            } catch (Exception) {
                // ignore unparseable date
            }
        }

        if (\array_key_exists('tags', $data) && \is_array($data['tags'])) {
            /** @var list<string> $tags */
            $tags = array_values(array_filter($data['tags'], is_string(...)));
            $message->setTags($tags);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Message $message): array
    {
        return [
            'id' => $message->id,
            'host' => $message->host,
            'locale' => $message->locale,
            'authorName' => $message->getAuthorName(),
            'authorEmail' => $message->getAuthorEmail(),
            'content' => $message->getContent(),
            'referring' => $message->getReferring(),
            'tags' => $message->getTagList(),
            'publishedAt' => $message->getPublishedAt()?->format(DateTimeInterface::ATOM),
            'createdAt' => $message->createdAt?->format(DateTimeInterface::ATOM),
            'updatedAt' => $message->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'host' => ['type' => 'string'],
                'locale' => ['type' => 'string', 'nullable' => true],
                'authorName' => ['type' => 'string', 'nullable' => true],
                'authorEmail' => ['type' => 'string', 'nullable' => true],
                'content' => ['type' => 'string'],
                'referring' => ['type' => 'string'],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'publishedAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                'updatedAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        return [
            'paths' => [
                '/api/conversation' => [
                    'get' => ['summary' => 'List messages', 'responses' => ['200' => ['description' => 'OK']]],
                    'post' => ['summary' => 'Create a message', 'responses' => ['201' => ['description' => 'Created']]],
                ],
                '/api/conversation/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => ['summary' => 'Get a message', 'responses' => ['200' => ['description' => 'OK'], '404' => ['description' => 'Not found']]],
                    'patch' => ['summary' => 'Update a message', 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['summary' => 'Delete a message', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
            ],
            'components' => ['schemas' => ['Message' => $schema]],
        ];
    }
}
