<?php

namespace Pushword\Quiz\Service;

use function Safe\file_get_contents;

/**
 * Serves the published JSON Schema of a quiz payload, shared by the
 * `pw:quiz:schema` command and the `GET /api/quiz/schema` endpoint so an agent
 * can fetch the exact shape (keys, aliases, enums) in one shot instead of
 * reading the model source.
 */
final class QuizSchemaProvider
{
    private const string SCHEMA_PATH = __DIR__.'/../Resources/schema/quiz.schema.json';

    public function json(): string
    {
        return trim(file_get_contents(self::SCHEMA_PATH));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        /** @var array<string, mixed> */
        return json_decode($this->json(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
