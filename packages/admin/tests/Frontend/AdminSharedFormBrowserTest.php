<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Frontend;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverKeys;
use PHPUnit\Framework\Attributes\Group;

#[Group('panther')]
final class AdminSharedFormBrowserTest extends AbstractPantherAdminTest
{
    public function testExpandedHostChoicesRemainReadableAndKeyboardSelectable(): void
    {
        $client = $this->createPantherClientWithLogin();
        $window = $client->getWebDriver()->manage()->window();
        $originalSize = $window->getSize();

        try {
            $window->setSize(new WebDriverDimension(390, 844));
            $client->request('GET', '/admin/newsletter/automation/new');
            $this->waitForElement($client, '#Automation_hosts', 'Automation hosts should load');
            $client->getWebDriver()->findElement(WebDriverBy::cssSelector('.form-fieldset:has(#Automation_hosts) .form-fieldset-title-content'))->click();
            self::assertTrue($this->pollUntilTrue($client, <<<'JS'
                const rows = [...document.querySelectorAll('#Automation_hosts .form-check')];
                return rows.length > 0 && rows.every(row => {
                    const rect = row.getBoundingClientRect();
                    return rect.width >= 200 && rect.height >= 44;
                });
                JS), 'Every host choice should use the available width and a touch-sized row');

            $input = $client->getWebDriver()->findElement(WebDriverBy::cssSelector('#Automation_hosts input[type=checkbox]'));
            $input->sendKeys(WebDriverKeys::SPACE);
            self::assertTrue($input->isSelected());
            self::assertTrue($client->executeScript(<<<'JS'
                const input = document.querySelector('#Automation_hosts input');
                const descriptions = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
                return descriptions.length > 0 && descriptions.every(id => {
                    const summary = document.getElementById(id);
                    return summary && summary.textContent.trim() && !summary.closest('[popover]');
                });
                JS), 'The empty-selection consequence should be visible and described to assistive technology');
        } finally {
            $window->setSize($originalSize);
        }
    }

    public function testMediaSettingsStackOnTabletAndSaveActionsShareAMobileRow(): void
    {
        $client = $this->createPantherClientWithLogin();
        $window = $client->getWebDriver()->manage()->window();
        $originalSize = $window->getSize();

        try {
            $window->setSize(new WebDriverDimension(800, 1000));
            $client->request('GET', '/admin/media/new');
            $this->waitForElement($client, '.ea-new-form .form-column', 'Media form should load');
            self::assertTrue($client->executeScript(<<<'JS'
                const columns = [...document.querySelectorAll('.ea-new-form > .row > .form-column')];
                const main = columns[0].getBoundingClientRect();
                const settings = columns[1].getBoundingClientRect();
                return Math.abs(main.left - settings.left) < 2 && settings.top >= main.bottom;
                JS), 'Tablet settings should have a full row below the main fields');

            $window->setSize(new WebDriverDimension(390, 844));
            self::assertTrue($this->pollUntilTrue($client, <<<'JS'
                const actions = document.querySelector('.page-actions');
                const primary = actions.querySelector('.action-saveAndReturn');
                const secondary = actions.querySelector('.action-saveAndAddAnother');
                if (!primary || !secondary) return false;
                const first = primary.getBoundingClientRect();
                const second = secondary.getBoundingClientRect();
                return actions.firstElementChild === primary && first.left < second.left
                    && Math.abs(first.top - second.top) < 2 && first.height >= 44 && second.height >= 44
                    && document.documentElement.scrollWidth <= window.innerWidth;
                JS), 'Mobile save actions should be reachable in one row, in keyboard order');
        } finally {
            $window->setSize($originalSize);
        }
    }
}
