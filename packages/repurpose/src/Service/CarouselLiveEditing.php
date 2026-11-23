<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use Pushword\Core\Collaboration\DraftExchange;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Repository\SocialPostRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CarouselLiveEditing
{
    public function __construct(
        private DraftExchange $exchange,
        private SocialPostRepository $posts,
        private CarouselFactory $factory,
        private ValidatorInterface $validator,
    ) {
    }

    public function sync(Request $request, SocialPost $post): JsonResponse
    {
        return $this->exchange->sync(
            $request,
            'repurpose:'.$post->id,
            $post,
            static fn (int $versionIncrement = 0): string => '"'.$post->id.'-'.($post->version + $versionIncrement).'"',
            static fn (): string => json_encode($post->spec, \JSON_THROW_ON_ERROR),
            fn (string $content): array => $this->apply($post, $content),
        );
    }

    /** @return list<array{path: string, message: string}> */
    private function apply(SocialPost $post, string $content): array
    {
        $spec = json_decode($content, true);
        if (! \is_array($spec) || array_is_list($spec)) {
            return [['path' => '', 'message' => 'Expected a JSON carousel spec.']];
        }

        /** @var array<string, mixed> $spec */
        $spec['network'] = $post->network;
        $spec['page'] = \is_string($spec['page'] ?? null) && '' !== $spec['page'] ? $spec['page'] : $post->page;
        $violations = [];
        foreach ($this->validator->validate($this->factory->fromArray($spec)) as $violation) {
            $violations[] = ['path' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
        }

        if ([] !== $violations) {
            return $violations;
        }

        $existing = $this->posts->findOneByKey($post->host, $spec['page'], $post->network);
        if ($existing instanceof SocialPost && $existing->id !== $post->id) {
            return [['path' => 'page', 'message' => 'A carousel already exists for this page on this network.']];
        }

        if ($post->spec !== $spec) {
            $post->spec = $spec;
        }

        return [];
    }
}
