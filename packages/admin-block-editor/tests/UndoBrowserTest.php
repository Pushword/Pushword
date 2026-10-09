<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\Frontend\AbstractPantherAdminTest;
use Pushword\Core\Entity\Page;
use Symfony\Component\Panther\Client;

#[Group('panther')]
final class UndoBrowserTest extends AbstractPantherAdminTest
{
    public function testUndoRedoForEveryConfiguredBlock(): void
    {
        $client = $this->openEditor('block-editor-history-test');
        $script = file_get_contents(__DIR__.'/browser/undo.js');
        self::assertIsString($script);
        $client->executeScript($script);
        self::assertTrue($this->pollUntilTrue($client, 'return window.undoBrowserChecks.done', [], 120));
        $results = $client->executeScript('return window.undoBrowserChecks.results');
        self::assertIsArray($results);
        foreach ($results as $result) {
            self::assertIsArray($result);
            self::assertTrue($result['passed'], json_encode($result, \JSON_THROW_ON_ERROR));
        }

        self::assertCount(59, $results, 'Every configured block must pass edit, move and delete round trips.');

        $selectionScript = file_get_contents(__DIR__.'/browser/undo-selection.js');
        self::assertIsString($selectionScript);
        $client->executeScript($selectionScript);
        self::assertTrue($this->pollUntilTrue($client, 'return window.undoSelectionChecks.done', [], 60));
        $selectionResults = $client->executeScript('return window.undoSelectionChecks.results');
        self::assertIsArray($selectionResults);
        foreach ($selectionResults as $result) {
            self::assertIsArray($result);
            self::assertTrue($result['passed'], json_encode($result, \JSON_THROW_ON_ERROR));
        }

        self::assertCount(16, $selectionResults);
    }

    public function testNativeKeyboardHistory(): void
    {
        $client = $this->openEditor('block-editor-keyboard-test');
        // Real WebDriver keystrokes additionally exercise trusted input and native editing.
        $client->executeScript(<<<'JS'
            void window.editors[document.querySelector('.editorjs-holder').id].blocks.render({
                blocks: [{ id: 'keyboard', type: 'paragraph', data: { text: 'Original' } }],
            });
            JS);
        $client->waitFor('[data-id="keyboard"] .ce-paragraph');
        $this->sleepMs(650);
        $field = $client->findElement(WebDriverBy::cssSelector('[data-id="keyboard"] .ce-paragraph'));
        $field->click();
        $field->sendKeys([WebDriverKeys::END, ' first']);
        $this->sleepMs(550);
        $client->getWebDriver()->switchTo()->activeElement()->sendKeys(' second');
        $this->shortcut($client, 'z');
        self::assertTrue(
            $this->pollUntilTrue($client, 'return document.querySelector("[data-id=keyboard] .ce-paragraph").textContent === "Original first"'),
            json_encode($client->executeScript('return document.querySelector("[data-id=keyboard]").textContent'), \JSON_THROW_ON_ERROR),
        );
        $this->shortcut($client, 'y');
        self::assertTrue($this->pollUntilTrue($client, 'return document.querySelector("[data-id=keyboard] .ce-paragraph").textContent === "Original first second"'));
    }

    private function shortcut(Client $client, string $key): void
    {
        $driver = $client->getWebDriver();
        self::assertInstanceOf(RemoteWebDriver::class, $driver);
        $devTools = new ChromeDevToolsDriver($driver);
        // WebDriver sendKeys maps Ctrl+Z to Ctrl+W on an AZERTY desktop.
        foreach (['keyDown', 'keyUp'] as $type) {
            $devTools->execute('Input.dispatchKeyEvent', [
                'type' => $type,
                'key' => $key,
                'code' => 'Key'.strtoupper($key),
                'windowsVirtualKeyCode' => ord(strtoupper($key)),
                'modifiers' => 2,
            ]);
        }
    }

    private function openEditor(string $slug): Client
    {
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = new Page();
        $page->host = 'admin-block-editor.test';
        $page->locale = 'en';
        $page->slug = $slug;
        $page->h1 = 'History browser test';
        $page->mainContent = '{"blocks":[{"type":"paragraph","data":{"text":"History baseline"}}]}';

        $entityManager->persist($page);
        $entityManager->flush();
        self::assertIsInt($page->id);

        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client, $page->id, '.ce-paragraph', self::timeoutLong());
        self::assertTrue($this->pollUntilTrue($client, 'return Boolean(window.editors?.[document.querySelector(".editorjs-holder")?.id])'));

        return $client;
    }
}
