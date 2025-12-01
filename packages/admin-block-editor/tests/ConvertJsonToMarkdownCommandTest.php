<?php

namespace Pushword\AdminBlockEditor\Tests;

use Pushword\Core\Entity\Page;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConvertJsonToMarkdownCommandTest extends KernelTestCase
{
    public function testConvertJsonToMarkdown(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        // Créer une page avec le contenu JSON KitchenSink
        $pageId = $this->createKitchenSinkPage();

        // Exécuter la commande de conversion
        $command = $application->find('pw:json-to-markdown');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--page-id' => (string) $pageId,
        ]);

        // Vérifier que la commande s'est exécutée avec succès
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Conversion terminée', $output);
        self::assertStringContainsString('1 page(s) convertie(s)', $output);
        self::assertSame(0, $commandTester->getStatusCode());

        // Récupérer le contenu converti
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        // Clear pour forcer le rechargement depuis la base de données
        $em->clear();

        $page = $em->getRepository(Page::class)->find($pageId);
        self::assertNotNull($page);

        $convertedContent = $page->getMainContent();

        // Charger le contenu Markdown attendu
        $expectedContent = file_get_contents(__DIR__.'/content/KitchenSink.md');

        // Comparer les contenus (en normalisant les fins de lignes)
        self::assertSame(
            $this->normalizeContent($expectedContent),
            $this->normalizeContent($convertedContent),
            'Le contenu converti devrait être identique au fichier KitchenSink.md'
        );
    }

    public function testDryRun(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        // Créer une page avec du contenu JSON
        $pageId = $this->createKitchenSinkPage();

        // Exécuter la commande en mode dry-run
        $command = $application->find('pw:json-to-markdown');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--page-id' => (string) $pageId,
            '--dry-run' => true,
        ]);

        // Vérifier que la commande affiche les pages sans les modifier
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('qui seraient converties (dry-run)', $output);
        self::assertStringContainsString('admin-block-editor.test/kitchen-sink', $output);

        // Vérifier que le contenu n'a PAS été modifié
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        // Clear pour forcer le rechargement depuis la base de données
        $em->clear();

        $page = $em->getRepository(Page::class)->find($pageId);
        self::assertNotNull($page);

        $content = $page->getMainContent();

        // Le contenu doit toujours être en JSON
        self::assertTrue($this->isJsonContent($content), 'Le contenu devrait toujours être en JSON après un dry-run');
    }

    public function testNoJsonPages(): void
    {
        $kernel = self::createKernel();
        $application = new Application($kernel);

        // Créer une page avec du contenu Markdown (pas JSON)
        $pageId = $this->createMarkdownTestPage();

        // Exécuter la commande sur cette page spécifique
        $command = $application->find('pw:json-to-markdown');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--page-id' => (string) $pageId,
        ]);

        // Vérifier qu'aucune page n'a été convertie
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Aucune page en format JSON trouvée', $output);
    }

    private function createKitchenSinkPage(): int
    {
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $jsonContent = file_get_contents(__DIR__.'/content/KitchenSink.json');

        $page = (new Page())
            ->setH1('Demo Page - Kitchen Sink Block')
            ->setSlug('kitchen-sink')
            ->setHost('admin-block-editor.test')
            ->setLocale('en')
            ->setMainContent($jsonContent);

        $em->persist($page);
        $em->flush();

        return (int) $page->getId();
    }

    private function createMarkdownTestPage(): int
    {
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $page = (new Page())
            ->setH1('Test Markdown Page')
            ->setSlug('test-markdown')
            ->setHost('admin-block-editor.test')
            ->setLocale('en')
            ->setMainContent('# Test\n\nThis is markdown content.');

        $em->persist($page);
        $em->flush();

        return (int) $page->getId();
    }

    private function isJsonContent(string $string): bool
    {
        json_decode($string);

        return \JSON_ERROR_NONE === json_last_error();
    }

    /**
     * Normalise le contenu pour la comparaison (trim les espaces et unifie les fins de ligne).
     */
    private function normalizeContent(string $content): string
    {
        // Normaliser les fins de ligne
        $content = str_replace("\r\n", "\n", $content);

        // Trim le contenu final
        return trim($content);
    }
}
