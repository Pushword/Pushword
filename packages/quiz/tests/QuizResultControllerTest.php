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

    public function testRecordsProfileAttemptsAndComputesShare(): void
    {
        $client = self::createClient();
        $quiz = 'controller-test-personality';

        // First participant: no prior → share 0.
        $first = $this->post($client, ['quiz' => $quiz, 'result' => 'explorer']);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertSame(0, $first['share']);
        self::assertArrayNotHasKey('percentile', $first);

        // Two more: one same profile, one different → 2 of 3 prior share 'explorer'.
        $this->post($client, ['quiz' => $quiz, 'result' => 'builder']);
        $this->post($client, ['quiz' => $quiz, 'result' => 'explorer']);
        $fourth = $this->post($client, ['quiz' => $quiz, 'result' => 'explorer']);

        self::assertSame(67, $fourth['share']); // 2 of the 3 prior attempts
    }

    public function testScoreAndProfileTalliesStaySeparateUnderOneSlug(): void
    {
        $client = self::createClient();
        // A single page can host both a quiz and a personality test under one slug.
        $quiz = 'controller-test-mixed';

        // Seed two low knowledge-quiz scores, then two of the same profile.
        $this->post($client, ['quiz' => $quiz, 'score' => 10]);
        $this->post($client, ['quiz' => $quiz, 'score' => 20]);
        $this->post($client, ['quiz' => $quiz, 'result' => 'explorer']);
        $this->post($client, ['quiz' => $quiz, 'result' => 'explorer']);

        // Percentile ignores the profile rows: 100 beats both prior *scores*.
        $score = $this->post($client, ['quiz' => $quiz, 'score' => 100]);
        self::assertSame(100, $score['percentile']);

        // Share ignores the score rows: 2 of the 2 prior *profiles* match.
        $share = $this->post($client, ['quiz' => $quiz, 'result' => 'explorer']);
        self::assertSame(100, $share['share']);
    }

    public function testSetsCorsHeaderSoStaticPagesCanReadTheResult(): void
    {
        $client = self::createClient();
        $this->post($client, ['quiz' => 'cors-quiz', 'score' => 50]);

        self::assertSame('*', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testScopesByOriginHostNotTheSharedDynamicHost(): void
    {
        $client = self::createClient();
        $quiz = 'origin-scoped-quiz';

        // A statically served page posts cross-origin: it lands on the shared
        // dynamic PHP host, but its Origin is the real site.
        $this->post($client, ['quiz' => $quiz, 'score' => 90], 'https://site-a.example');

        // Another site's page, same score: a fresh bucket (percentile 0) proves
        // results are scoped per Origin, not collapsed under the PHP host.
        $other = $this->post($client, ['quiz' => $quiz, 'score' => 90], 'https://site-b.example');
        self::assertSame(0, $other['percentile']);

        // A second attempt from site-a sees its own prior 90 → beats it (100).
        $second = $this->post($client, ['quiz' => $quiz, 'score' => 100], 'https://site-a.example');
        self::assertSame(100, $second['percentile']);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    private function post(KernelBrowser $client, array $payload, ?string $origin = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $origin) {
            $server['HTTP_ORIGIN'] = $origin;
        }

        $client->request(
            Request::METHOD_POST,
            '/quiz/result',
            server: $server,
            content: (string) json_encode($payload),
        );

        $decoded = json_decode((string) $client->getResponse()->getContent(), true);

        return \is_array($decoded) ? $decoded : [];
    }
}
