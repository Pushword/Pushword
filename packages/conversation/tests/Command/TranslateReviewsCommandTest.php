<?php

namespace Pushword\Conversation\Tests\Command;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Review;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Throwable;

#[Group('integration')]
final class TranslateReviewsCommandTest extends KernelTestCase
{
    private string $testHost = 'localhost.dev';

    /** @var int[] */
    private array $createdReviewIds = [];

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    public function testRequiresLocaleOption(): void
    {
        self::bootKernel();

        $commandTester = $this->runTranslateCommand([]);

        self::assertStringContainsString('You must specify at least one target locale', $commandTester->getDisplay());
        self::assertSame(1, $commandTester->getStatusCode());
    }

    public function testSingleHostShowsHostInOutput(): void
    {
        self::bootKernel();

        $commandTester = $this->runTranslateCommand(['--locale' => 'fr', '--host' => 'localhost.dev']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('[localhost.dev]', $output);
        self::assertStringNotContainsString('[pushword.piedweb.com]', $output);
    }

    public function testAllHostsProcessedWhenNoHostOption(): void
    {
        self::bootKernel();

        $commandTester = $this->runTranslateCommand(['--locale' => 'fr']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('[localhost.dev]', $output);
        self::assertStringContainsString('[pushword.piedweb.com]', $output);
    }

    public function testDetectsTranslatesAndPersistsWhileSkippingExisting(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $container->set('http_client', $this->mockDeepLTranslateEndpoint());

        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $apps = $container->get(SiteRegistry::class);

        // Enable DeepL on the test host so a translator is available.
        $apps->switchSite($this->testHost);
        $apps->get()->setCustomProperty('translation_deepl_api_key', 'test-key');

        // Needs translation: English content, no source locale yet → detect (EN) then translate to FR.
        $toTranslate = new Review();
        $toTranslate->host = $this->testHost;
        $toTranslate->setRating(5);
        $toTranslate->setReferring('/trip');
        $toTranslate->setContent('A wonderful trip along the river.');

        $em->persist($toTranslate);

        // Already translated to FR → must be skipped, not overwritten or re-requested.
        $alreadyTranslated = new Review();
        $alreadyTranslated->host = $this->testHost;
        $alreadyTranslated->setRating(5);
        $alreadyTranslated->setReferring('/trip2');
        $alreadyTranslated->setContent('Another great trip.');
        $alreadyTranslated->locale = 'en';
        $alreadyTranslated->setTranslation('fr', null, 'déjà traduit');

        $em->persist($alreadyTranslated);

        $em->flush();

        $this->createdReviewIds = [(int) $toTranslate->id, (int) $alreadyTranslated->id];

        $commandTester = $this->runTranslateCommand([
            '--locale' => 'fr',
            '--host' => $this->testHost,
            '--delay' => 0,
        ]);

        self::assertSame(0, $commandTester->getStatusCode());

        $em->clear();
        $translated = $em->find(Review::class, $toTranslate->id);
        $untouched = $em->find(Review::class, $alreadyTranslated->id);
        self::assertInstanceOf(Review::class, $translated);
        self::assertInstanceOf(Review::class, $untouched);

        // Detected locale persisted and the canned FR translation stored.
        self::assertSame('en', $translated->locale);
        self::assertTrue($translated->hasTranslation('fr'));
        self::assertStringContainsString('FR::A wonderful trip along the river.', $translated->getTranslatedContent('fr'));

        // Pre-existing translation left exactly as it was.
        self::assertSame('déjà traduit', $untouched->getTranslatedContent('fr'));
    }

    public function testProcessesReviewsAcrossBatchBoundary(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $container->set('http_client', $this->mockDeepLTranslateEndpoint());

        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $apps = $container->get(SiteRegistry::class);

        $apps->switchSite($this->testHost);
        $apps->get()->setCustomProperty('translation_deepl_api_key', 'test-key');

        // Seed one more than BATCH_SIZE (50) so array_chunk yields two batches. The 51st review
        // lands alone in the second chunk, which is reached only after the first is flushed and
        // detach()ed. A regression from detach() to em->clear() would detach the tracker's cached
        // TranslationUsage, and the first translation in chunk 2 would throw instead of persisting.
        $created = [];
        for ($i = 0; $i < 51; ++$i) {
            $review = new Review();
            $review->host = $this->testHost;
            $review->setRating(5);
            $review->setReferring('/batch-'.$i);
            $review->setContent('A wonderful trip number '.$i.'.');
            $review->locale = 'en';
            $em->persist($review);
            $created[] = $review;
        }

        $em->flush();

        foreach ($created as $review) {
            $this->createdReviewIds[] = (int) $review->id;
        }

        $commandTester = $this->runTranslateCommand([
            '--locale' => 'fr',
            '--host' => $this->testHost,
            '--delay' => 0,
        ]);

        self::assertSame(0, $commandTester->getStatusCode());

        // Every review — including the one in the second chunk — is translated and persisted,
        // proving the batch loop consumed all chunks and the translator/tracker survived detach().
        $em->clear();
        foreach ($this->createdReviewIds as $id) {
            $review = $em->find(Review::class, $id);
            self::assertInstanceOf(Review::class, $review);
            self::assertTrue(
                $review->hasTranslation('fr'),
                \sprintf('Review #%d should be translated across the batch boundary.', $id),
            );
        }
    }

    private function mockDeepLTranslateEndpoint(): MockHttpClient
    {
        // Emulate DeepL over its single /v2/translate endpoint: the language-detection call uses
        // target_lang=EN (dummy) and reads detected_source_language; the real translation uses
        // target_lang=FR and reads translations[0].text. One callback serves both.
        return new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $decoded = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '{}', true);
            $body = \is_array($decoded) ? $decoded : [];
            $target = \is_string($body['target_lang'] ?? null) ? $body['target_lang'] : '';
            $textList = \is_array($body['text'] ?? null) ? $body['text'] : [];
            $text = \is_string($textList[0] ?? null) ? $textList[0] : '';

            $payload = 'FR' === $target
                ? ['translations' => [['text' => 'FR::'.$text]]]
                : ['translations' => [['detected_source_language' => 'EN']]];

            return new MockResponse((string) json_encode($payload), ['http_code' => 200]);
        });
    }

    /** @param array<string, mixed> $options */
    private function runTranslateCommand(array $options): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:conversation:translate-reviews'));
        $commandTester->execute($options);

        return $commandTester;
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    private function cleanupTestData(): void
    {
        try {
            $em = $this->getEntityManager();
            if (! $em->isOpen()) {
                return;
            }

            $em->clear();

            foreach ($this->createdReviewIds as $id) {
                $review = $em->find(Review::class, $id);
                if (null !== $review) {
                    $em->remove($review);
                }
            }

            $em->flush();
        } catch (Throwable) {
        }
    }
}
