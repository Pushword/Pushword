<?php

namespace Pushword\Quiz\Tests\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class QuizApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'quiz-api-test-'.uniqid().'@example.com';
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
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = $this->em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $this->em->remove($user);
            $this->em->flush();
        }

        parent::tearDown();
    }

    public function testValidPayloadReturnsValid(): void
    {
        $body = $this->post([
            'questions' => [
                ['q' => 'Capital of France?', 'answers' => [['a' => 'Paris', 'correct' => true], ['a' => 'Lyon']]],
            ],
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertTrue($body['valid']);
        self::assertSame(1, $body['questions']);
    }

    public function testLevelsPayloadSumsQuestionsAcrossLevels(): void
    {
        $body = $this->post([
            'title' => 'Mountains',
            'levels' => [
                ['difficulty' => 'Easy', 'questions' => [
                    ['q' => 'Q1', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]],
                ]],
                ['difficulty' => 'Hard', 'questions' => [
                    ['q' => 'Q2', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]],
                    ['q' => 'Q3', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]],
                ]],
            ],
        ]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertTrue($body['valid']);
        self::assertSame(3, $body['questions']);
    }

    public function testSchemaEndpointReturnsJsonSchema(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/api/quiz/schema',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken],
        );

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $schema = (array) json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Pushword Quiz', $schema['title']);
        self::assertArrayHasKey('$defs', $schema);
    }

    public function testInvalidPayloadReturns422WithPreciseViolations(): void
    {
        $body = $this->post([
            'questions' => [['q' => '', 'answers' => [['a' => 'only']]]],
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertNotEmpty($body['violations']);
        self::assertIsArray($body['violations']);
        self::assertIsArray($body['violations'][0]);
        self::assertArrayHasKey('path', $body['violations'][0]);
        self::assertArrayHasKey('message', $body['violations'][0]);
    }

    public function testRequiresAuthentication(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/quiz/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['questions' => []]),
        );

        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    private function post(array $body): array
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken, 'CONTENT_TYPE' => 'application/json'];
        $this->client->request(Request::METHOD_POST, '/api/quiz/validate', [], [], $server, (string) json_encode($body));

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }
}
