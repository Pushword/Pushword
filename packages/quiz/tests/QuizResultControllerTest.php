<?php

namespace Pushword\Quiz\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class QuizResultControllerTest extends WebTestCase
{
    public function testRecordsAttemptsAndComputesPercentile(): void
    {
        $client = self::createClient();
        $quiz = 'controller-test-quiz';

        // First attempt: no prior participant → percentile 0.
        $first = $this->post($client, ['quiz' => $quiz, 'score' => 90]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertSame(0, $first['percentile']);

        // A lower score is recorded too.
        $this->post($client, ['quiz' => $quiz, 'score' => 10]);

        // Beats both priors (90 and 10) → 100th percentile.
        $third = $this->post($client, ['quiz' => $quiz, 'score' => 100]);
        self::assertSame(100, $third['percentile']);
    }

    public function testRejectsInvalidPayloads(): void
    {
        $client = self::createClient();

        $this->post($client, ['quiz' => 'x', 'score' => 150]); // out of 0-100
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $this->post($client, ['score' => 50]); // missing quiz
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    private function post(KernelBrowser $client, array $payload): array
    {
        $client->request(
            Request::METHOD_POST,
            '/quiz/result',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode($payload),
        );

        $decoded = json_decode((string) $client->getResponse()->getContent(), true);

        return \is_array($decoded) ? $decoded : [];
    }
}
