<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\Frontend\AbstractPantherAdminTest;
use Pushword\Core\Entity\Page;

#[Group('panther')]
final class PageSubmitBrowserTest extends AbstractPantherAdminTest
{
    public function testSaveButtonSubmitsWhenBlockConversionFinishesImmediately(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = new Page();
        $page->host = 'admin-block-editor.test';
        $page->locale = 'en';
        $page->slug = 'block-editor-submit-test';
        $page->h1 = 'Before save';
        $page->mainContent = '{"blocks":[{"type":"paragraph","data":{"text":"Before save"}}]}';

        $entityManager->persist($page);
        $entityManager->flush();

        $pageId = $page->id;
        self::assertIsInt($pageId);

        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client, $pageId, '.ce-paragraph', self::timeoutLong());
        self::assertTrue(
            $this->pollUntilTrue($client, 'return Boolean(window.editors?.[document.querySelector(".editorjs-holder")?.id])'),
            'The block editor must be initialized before submitting',
        );

        $client->executeScript(<<<'JS'
            const paragraph = document.querySelector('.ce-paragraph');
            paragraph.textContent = 'Saved from the browser';
            paragraph.dispatchEvent(new InputEvent('input', { bubbles: true }));
            // Keep the conversion in the original submit task to expose native resubmission races.
            const holder = document.querySelector('.editorjs-holder');
            window.editors[holder.id].saver.save = async () => ({
                blocks: [{ type: 'paragraph', data: { text: paragraph.textContent } }],
            });
            window.EditorJsExportMarkdown.prototype.exportToMarkdown = async () => paragraph.textContent;
            JS);
        $form = $client->findElement(WebDriverBy::cssSelector('.ea-edit-form'));
        $client->findElement(WebDriverBy::cssSelector('.action-saveAndContinue'))->click();
        $client->wait(self::timeoutLong())->until(WebDriverExpectedCondition::stalenessOf($form));
        $client->waitFor('.ce-paragraph', self::timeoutLong());
        $client->request('GET', $this->generateAdminUrl('admin_page_edit', ['id' => $pageId]));
        $client->waitFor('.ce-paragraph', self::timeoutLong());

        $storedContent = $client->executeScript('return document.querySelector("[id$=_mainContent]")?.defaultValue');
        self::assertIsString($storedContent);
        self::assertStringContainsString('Saved from the browser', $storedContent);
    }
}
