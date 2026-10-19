<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\Frontend\AbstractPantherAdminTest;
use Pushword\Core\Entity\Page;
use stdClass;

#[Group('panther')]
final class ListBrowserTest extends AbstractPantherAdminTest
{
    #[DataProvider('listStyles')]
    public function testKeyboardNestingSurvivesSaveAndReload(string $style): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = new Page();
        $page->host = 'admin-block-editor.test';
        $page->locale = 'en';
        $page->slug = 'block-editor-list-'.$style;
        $page->h1 = 'List keyboard test';
        $page->mainContent = json_encode(['blocks' => [[
            'type' => 'list',
            'data' => ['style' => $style, 'items' => [['content' => 'Parent', 'meta' => new stdClass(), 'items' => []]]],
        ]]], \JSON_THROW_ON_ERROR);
        $entityManager->persist($page);
        $entityManager->flush();
        self::assertIsInt($page->id);

        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client, $page->id, '.cdx-list__item-content', self::timeoutLong());
        $field = $client->findElement(WebDriverBy::cssSelector('.cdx-list__item-content'));
        $field->click();
        $field->sendKeys([WebDriverKeys::END, WebDriverKeys::ENTER]);
        $client->getWebDriver()->switchTo()->activeElement()->sendKeys(WebDriverKeys::TAB);
        $client->getWebDriver()->switchTo()->activeElement()->sendKeys('Child');

        $nestedItem = '.cdx-list__item-children .cdx-list__item-content';
        self::assertTrue($this->pollUntilTrue($client, 'return document.querySelector(arguments[0])?.textContent === "Child"', [$nestedItem]));

        $client->getWebDriver()->switchTo()->activeElement()->sendKeys([WebDriverKeys::SHIFT, WebDriverKeys::TAB, WebDriverKeys::NULL]);
        self::assertTrue($this->pollUntilTrue($client, 'return document.querySelectorAll(".ce-block__content > .cdx-list > .cdx-list__item").length === 2 && !document.querySelector(arguments[0])', [$nestedItem]));
        $client->getWebDriver()->switchTo()->activeElement()->sendKeys(WebDriverKeys::TAB);
        self::assertTrue($this->pollUntilTrue($client, 'return document.querySelector(arguments[0])?.textContent === "Child"', [$nestedItem]));

        $form = $client->findElement(WebDriverBy::cssSelector('.ea-edit-form'));
        $client->findElement(WebDriverBy::cssSelector('.action-saveAndContinue'))->click();
        $client->wait(self::timeoutLong())->until(WebDriverExpectedCondition::stalenessOf($form));
        $client->waitFor('.cdx-list__item-content', self::timeoutLong());
        $client->request('GET', $this->generateAdminUrl('admin_page_edit', ['id' => $page->id]));
        $client->waitFor($nestedItem, self::timeoutLong());

        self::assertSame('Child', $client->findElement(WebDriverBy::cssSelector($nestedItem))->getText());
        self::assertCount(1, $client->findElements(WebDriverBy::cssSelector('.ce-block__content > .cdx-list > .cdx-list__item')));
        self::assertCount(1, $client->findElements(WebDriverBy::cssSelector('.ce-block__content > .cdx-list-'.$style)));
    }

    /** @return iterable<string, array{string}> */
    public static function listStyles(): iterable
    {
        yield 'unordered' => ['unordered'];
        yield 'ordered' => ['ordered'];
        yield 'checklist' => ['checklist'];
    }
}
