<?php

namespace Pushword\Quiz\Tests\Admin;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Quiz\Entity\QuizResult;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class QuizResultCrudControllerTest extends AbstractAdminTestClass
{
    public function testIndexListsResultsReadOnly(): void
    {
        $client = $this->loginUser();
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $result = new QuizResult();
        $result->host = 'localhost.dev';
        $result->quiz = 'admin-test-quiz';
        $result->score = 73;

        $entityManager->persist($result);
        $entityManager->flush();

        $client->request(Request::METHOD_GET, '/admin/quiz-result');
        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $html = (string) $response->getContent();
        self::assertStringContainsString('admin-test-quiz', $html);
        self::assertStringContainsString('73', $html);

        // Read-only: the New action is disabled, so the index offers no create link.
        self::assertStringNotContainsString('/admin/quiz-result/new', $html);

        $entityManager->remove($entityManager->getRepository(QuizResult::class)->find($result->id) ?? $result);
        $entityManager->flush();
    }
}
