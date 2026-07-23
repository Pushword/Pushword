<?php

namespace Pushword\Admin\Tests\Frontend;

use PHPUnit\Framework\Attributes\Group;

/**
 * EasyAdmin cancels the submit when a control is invalid and only knows how to
 * re-open a Bootstrap `.form-fieldset-body.collapse`. Our pw-settings panels hide
 * their body with `display: none`, so without admin.revealInvalidField the save
 * button silently does nothing: no submit, no console error, and the offending
 * field stays out of sight.
 */
#[Group('panther')]
final class AdminRevealInvalidFieldTest extends AbstractPantherAdminTest
{
    private const string SELECTOR_PANEL = '.pw-settings-accordion';

    public function testCollapsedPanelOpensWhenItHidesAnInvalidField(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        $this->waitForElement($client, self::SELECTOR_PANEL, 'No pw-settings panel on the edit form');
        self::assertTrue(
            $this->pollUntilTrue($client, 'return document.querySelector(".pw-settings-accordion.pw-settings-collapsed") !== null'),
            'admin.js should have collapsed at least one panel'
        );

        // Arrange: an invalid, required control inside a collapsed panel.
        $panelKey = $client->executeScript(<<<'JS'
            const field = document.querySelector('.ea-edit-form input[id$="_locale"]');
            if (null === field) return '';

            const panel = field.closest('.pw-settings-accordion');
            if (null === panel) return '';

            panel.classList.remove('has-fieldset-error');
            if (!panel.classList.contains('pw-settings-collapsed')) {
                panel.querySelector('.pw-settings-toggle').click();
            }

            field.value = '';
            field.required = true;

            return panel.dataset.pwPanelKey || '';
            JS);

        self::assertIsString($panelKey);
        self::assertNotSame('', $panelKey, 'Expected the locale field to sit in a keyed pw-settings panel');

        // Act: click save exactly like a user would.
        $client->executeScript(<<<'JS'
            const form = document.querySelector('.ea-edit-form');
            Array.from(form.elements).filter((element) => 'submit' === element.type)[0].click();
            JS);

        // Assert: the panel hiding the invalid field is now open.
        $opened = $this->pollUntilTrue(
            $client,
            'const panel = document.querySelector(`.pw-settings-accordion[data-pw-panel-key="${arguments[0]}"]`);
             return null !== panel && !panel.classList.contains("pw-settings-collapsed")',
            [$panelKey],
        );

        self::assertTrue($opened, 'The panel hiding the invalid field should have been opened');
    }
}
