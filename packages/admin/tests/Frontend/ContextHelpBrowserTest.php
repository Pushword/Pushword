<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Frontend;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\Group;

#[Group('panther')]
final class ContextHelpBrowserTest extends AbstractPantherAdminTest
{
    public function testReferenceHelpSurvivesScrollAndClosesWithEscape(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        $selector = '[data-pw-help-id="Page_title_reference_help"] .pw-help-button';
        $this->waitForElement($client, $selector, 'Reference help should be attached to the title label');
        // TagsField moves focus to the H1 after loading; let it finish before testing focus restoration.
        self::assertTrue($this->pollUntilTrue($client, 'return document.activeElement.matches("[id$=_h1]")'));

        $button = $client->getWebDriver()->findElement(WebDriverBy::cssSelector($selector));
        $button->click();
        self::assertTrue($this->pollUntilTrue($client, 'return document.querySelector("#Page_title_reference_help").matches(":popover-open")'));

        self::assertTrue($client->executeScript(<<<'JS'
            window.dispatchEvent(new Event('scroll'));
            const panel = document.querySelector('#Page_title_reference_help');
            const counter = document.querySelector('#titleWidth');
            return panel.matches(':popover-open') && !panel.contains(counter);
            JS));

        $button->sendKeys(WebDriverKeys::ESCAPE);
        self::assertTrue($this->pollUntilTrue($client, 'return !document.querySelector("#Page_title_reference_help").matches(":popover-open")'));
        self::assertSame('false', $button->getAttribute('aria-expanded'));
        self::assertTrue($client->executeScript('return document.activeElement.matches("[data-pw-help-id=Page_title_reference_help] button")'));
    }
}
