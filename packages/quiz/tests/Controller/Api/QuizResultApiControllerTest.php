<?php

namespace Pushword\Quiz\Tests\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Quiz\Entity\QuizResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class QuizResultApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    /** Unique per run: the table is shared with whatever the other tests recorded. */
    private string $quiz = '';

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->quiz = 'api-'.bin2hex(random_bytes(6));

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'quiz-result-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $recorded = $this->em->getRepository(QuizResult::class)->createQueryBuilder('r')
            ->andWhere('r.quiz LIKE :quiz')->setParameter('quiz', '%'.$this->quiz)
            ->getQuery()->getResult();
        foreach ($recorded as $quizResult) {
            $this->em->remove($quizResult);
        }

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = $this->em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $this->em->remove($user);
        }

        $this->em->flush();

        parent::tearDown();
    }

    public function testListingAttemptsScopedToOneQuiz(): void
    {
        $this->record(40);
        $this->record(80);
        $this->record(60, 'other-'.$this->quiz);

        $body = $this->get('/api/quiz/result?quiz='.$this->quiz);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(2, $body['total']);
        $items = $body['items'];
        self::assertIsArray($items);
        self::assertCount(2, $items);
        self::assertSame([80, 40], array_column($items, 'score'), 'newest first');
    }

    public function testListingAttemptsScopedToOneHost(): void
    {
        $this->record(40);
        $this->record(80, host: 'pushword.piedweb.com');

        $body = $this->get('/api/quiz/result?quiz='.$this->quiz.'&host=pushword.piedweb.com');

        self::assertSame(1, $body['total']);
        $items = $body['items'];
        self::assertIsArray($items);
        self::assertSame([80], array_column($items, 'score'));
    }

    /** One brand's tally is not another's, even when both play a quiz of the same name. */
    public function testStatsKeepTheHostsApart(): void
    {
        $this->record(40);
        $this->record(45);
        $this->record(80, host: 'pushword.piedweb.com');

        $body = $this->get('/api/quiz/result/stats?quiz='.$this->quiz);

        self::assertSame(2, $body['total']);
        $items = $body['items'];
        self::assertIsArray($items);
        self::assertSame(['localhost.dev', 'pushword.piedweb.com'], array_column($items, 'host'));
        self::assertSame([2, 1], array_column($items, 'attempts'));

        $body = $this->get('/api/quiz/result/stats?quiz='.$this->quiz.'&host=localhost.dev');

        self::assertSame(1, $body['total']);
    }

    public function testStatsAverageTheKnowledgeAttempts(): void
    {
        $this->record(40);
        $this->record(80);
        $this->record(76);

        $body = $this->get('/api/quiz/result/stats?quiz='.$this->quiz);

        self::assertSame(1, $body['total']);
        $items = $body['items'];
        self::assertIsArray($items);
        self::assertSame([[
            'quiz' => $this->quiz,
            'host' => 'localhost.dev',
            'attempts' => 3,
            'knowledgeAttempts' => 3,
            'averageScore' => 65.3,
            'profiles' => [],
        ]], $items);
    }

    /** A personality test scores nothing; what it has to report is how the profiles split. */
    public function testStatsSplitPersonalityAttemptsByProfile(): void
    {
        $this->record(0, result: 'calm');
        $this->record(0, result: 'calm');
        $this->record(0, result: 'sommet');
        $this->record(50);
        $this->record(55);

        $body = $this->get('/api/quiz/result/stats?quiz='.$this->quiz);

        $items = $body['items'];
        self::assertIsArray($items);
        $stats = $items[0];
        self::assertIsArray($stats);
        self::assertSame(5, $stats['attempts']);
        self::assertSame(2, $stats['knowledgeAttempts']);
        self::assertSame(52.5, $stats['averageScore'], 'the profile attempts do not weigh on the score');
        self::assertSame(['calm' => 2, 'sommet' => 1], $stats['profiles']);
    }

    public function testStatsOfAQuizNobodyPlayed(): void
    {
        $body = $this->get('/api/quiz/result/stats?quiz='.$this->quiz);

        self::assertSame(0, $body['total']);
        self::assertSame([], $body['items']);
    }

    public function testRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/quiz/result');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    private function record(int $score, ?string $quiz = null, ?string $result = null, string $host = 'localhost.dev'): void
    {
        $quizResult = new QuizResult();
        $quizResult->host = $host;
        $quizResult->quiz = $quiz ?? $this->quiz;
        $quizResult->score = $score;
        $quizResult->result = $result;

        $this->em->persist($quizResult);
        $this->em->flush();
    }

    /** @return array<array-key, mixed> */
    private function get(string $url): array
    {
        $this->client->request(Request::METHOD_GET, $url, [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken]);

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }
}
