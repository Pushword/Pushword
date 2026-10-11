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

    public function testInlineToolbarHistory(): void
    {
        $client = $this->openEditor('block-editor-inline-history-test');
        $script = file_get_contents(__DIR__.'/browser/undo-inline.js');
        self::assertIsString($script);
        $client->executeScript($script);
        self::assertTrue($this->pollUntilTrue($client, 'return window.undoInlineChecks.done', [], 90));
        $results = $client->executeScript('return window.undoInlineChecks.results');
        self::assertIsArray($results);
        self::assertCount(17, $results);
        foreach ($results as $result) {
            self::assertIsArray($result);
            self::assertTrue($result['passed'], json_encode($result, \JSON_THROW_ON_ERROR));
        }
    }

    public function testNativeInlineToolbarHistory(): void
    {
        $client = $this->openEditor('block-editor-native-inline-history-test');
        foreach (['bold' => 'b,strong', 'italic' => 'i,em', 'inline-code' => 'code', 'marker' => 'mark', 'Strikethrough' => 's,strike', 'small' => 'small', 'link' => 'a[href]'] as $name => $selector) {
            $client->executeScript(<<<'JS'
                void window.editors[document.querySelector('.editorjs-holder').id].blocks.render({
                    blocks: [{ id: 'native-inline', type: 'paragraph', data: { text: 'Format me' } }],
                });
                JS);
            $client->waitFor('[data-id="native-inline"] .ce-paragraph');
            $this->sleepMs(650);
            $field = $client->findElement(WebDriverBy::cssSelector('[data-id="native-inline"] .ce-paragraph'));
            $field->click();
            $field->sendKeys([WebDriverKeys::HOME, WebDriverKeys::SHIFT, ...array_fill(0, 6, WebDriverKeys::ARROW_RIGHT), WebDriverKeys::NULL]);
            $buttonSelector = '.ce-inline-toolbar [data-item-name="'.$name.'"]';
            $client->waitForVisibility($buttonSelector);
            if ('link' === $name) {
                $this->shortcut($client, 'k');
                $client->waitForVisibility('.link-options__url');
                $input = $client->findElement(WebDriverBy::cssSelector('.link-options__url'));
                $input->sendKeys('https://example.org/native');
                $this->shortcut($client, 'z');
                self::assertTrue($this->pollUntilTrue($client, 'return document.querySelector(".link-options__url").value === ""'), 'URL input uses native undo');
                self::assertSame('Format me', $client->executeScript('return document.querySelector("[data-id=native-inline] .ce-paragraph").innerHTML'));
                $this->shortcut($client, 'y');
                self::assertTrue($this->pollUntilTrue($client, 'return document.querySelector(".link-options__url").value === "https://example.org/native"'), 'URL input uses native redo');
                $input->sendKeys(WebDriverKeys::ENTER);
            } else {
                $button = $client->findElement(WebDriverBy::cssSelector($buttonSelector));
                if ('button' !== $button->getTagName()) {
                    $button = $button->findElement(WebDriverBy::tagName('button'));
                }

                $button->click();
            }

            $formatted = 'return document.querySelector("[data-id=native-inline] .ce-paragraph").querySelector(arguments[0])?.textContent === "Format"';
            self::assertTrue($this->pollUntilTrue($client, $formatted, [$selector]), $name.' applied');
            $this->shortcut($client, 'z');
            self::assertTrue($this->pollUntilTrue($client, 'return document.querySelector("[data-id=native-inline] .ce-paragraph").innerHTML === "Format me"'), $name.' undo');
            self::assertSame('Format', $client->executeScript('return getSelection().toString()'), $name.' selection');
            $this->shortcut($client, 'y');
            self::assertTrue($this->pollUntilTrue($client, $formatted, [$selector]), $name.' redo');

            if (\in_array($name, ['bold', 'italic'], true)) {
                $this->shortcut($client, 'bold' === $name ? 'b' : 'i');
                self::assertTrue($this->pollUntilTrue($client, 'return !document.querySelector("[data-id=native-inline] .ce-paragraph").querySelector(arguments[0])', [$selector]), $name.' keyboard toggle');
                $this->shortcut($client, 'z');
                self::assertTrue($this->pollUntilTrue($client, $formatted, [$selector]), $name.' keyboard undo');
            }
        }
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
