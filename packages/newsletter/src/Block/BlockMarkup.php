<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Block;

use InvalidArgumentException;
use JsonException;

/** A fenced block survives Markdown/Editor.js round trips with its reference and edited copy. */
final class BlockMarkup
{
    private const string PATTERN = '/^```newsletter-block\h*\R(.*?)\R```\h*$/ms';

    /** @return list<array{provider: string, content: BlockContent}> */
    public static function blocks(string $markdown): array
    {
        preg_match_all(self::PATTERN, $markdown, $matches);

        return array_map(self::decode(...), $matches[1]);
    }

    public static function expand(string $markdown): string
    {
        $expanded = preg_replace_callback(self::PATTERN, static fn (array $match): string => self::decode($match[1])['content']->markdown, $markdown) ?? $markdown;
        if (str_contains($expanded, '```newsletter-block')) {
            throw new InvalidArgumentException('Malformed or nested newsletter block.');
        }

        return $expanded;
    }

    public static function encode(string $provider, BlockContent $block): string
    {
        return "```newsletter-block\n".json_encode($block->toArray($provider), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)."\n```";
    }

    /** @return array{provider: string, content: BlockContent} */
    private static function decode(string $json): array
    {
        try {
            $data = json_decode($json, true, 32, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException('Invalid newsletter block JSON.', $jsonException->getCode(), previous: $jsonException);
        }

        if (! \is_array($data)) {
            throw new InvalidArgumentException('Invalid newsletter block.');
        }

        foreach (['provider', 'reference', 'label', 'markdown'] as $key) {
            if (! \is_string($data[$key] ?? null)) {
                throw new InvalidArgumentException('Missing newsletter block '.$key.'.');
            }
        }

        $parameters = $data['parameters'] ?? [];
        if (! \is_array($parameters) || array_any(array_keys($parameters), static fn (mixed $key): bool => ! \is_string($key))) {
            throw new InvalidArgumentException('Invalid newsletter block parameters.');
        }

        /** @var array<string, mixed> $parameters */
        return ['provider' => $data['provider'], 'content' => new BlockContent($data['reference'], $data['label'], $data['markdown'], $parameters)];
    }
}
