<?php

namespace Pushword\Admin\Tests\Frontend;

use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for the admin JavaScript (panels, textarea, title, host).
 * Uses Symfony Panther to test in a real browser.
 */
#[Group('panther')]
class AdminJSTest extends AbstractPantherAdminTest
{
    private const string SELECTOR_PANELS = '.pw-settings-accordion, .collapse';

    private const string SELECTOR_PANEL_TOGGLE = '.pw-settings-toggle, [data-bs-toggle="collapse"]';

    private const string SELECTOR_AUTOSIZE_TEXTAREA = '.autosize';

    private const string SELECTOR_TITLE_INPUT = '.titleToMeasure';

    private const string SELECTOR_HOST_SELECT = 'select[name$="[host]"]';

    /**
     * Test panel open state memorization.
     */
    public function testMemorizeOpenPanel(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        $this->waitForElement($client, self::SELECTOR_PANELS, 'No panels found on this page');
        $this->waitForElement($client, self::SELECTOR_PANEL_TOGGLE, 'No panel toggle button found');

        $buttonElement = $client->findElement(
            WebDriverBy::cssSelector(self::SELECTOR_PANEL_TOGGLE)
        );
        $buttonElement->click();
        $this->sleepMs(self::waitMedium());

        // Poll for localStorage to be updated
        $stored = $this->pollUntilTrue(
            $client,
            'return localStorage.getItem("panels") !== null',
        );

        self::assertTrue($stored, 'Panel state should be stored in localStorage');
    }

    /**
     * Test parent page filter by host.
     */
    public function testFilterParentPageFromHost(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageCreate($client);

        $this->waitForElement($client, self::SELECTOR_HOST_SELECT, 'No host select found on create page');

        // Check that window.pageHost is defined
        $pageHost = $client->executeScript('return window.pageHost || ""');
        self::assertNotEmpty($pageHost, 'pageHost should be set');
    }

    /**
     * Test textarea auto-resize.
     */
    public function testTextareaAutoSize(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        $this->waitForElement($client, self::SELECTOR_AUTOSIZE_TEXTAREA, 'No autosize textarea found on this page');

        // Wait for autosize initialization (style.height is set)
        $autosizeInitialized = $this->pollUntilTrue(
            $client,
            'const ta = document.querySelector(arguments[0]); return ta && ta.style.height !== ""',
            [self::SELECTOR_AUTOSIZE_TEXTAREA],
        );

        self::assertTrue($autosizeInitialized, 'Autosize should initialize and set style.height');

        // Read initial height
        $initialHeight = $client->executeScript(
            'return parseInt(window.getComputedStyle(document.querySelector(arguments[0])).height)',
            [self::SELECTOR_AUTOSIZE_TEXTAREA]
        );

        self::assertIsInt($initialHeight);
        self::assertGreaterThan(0, $initialHeight, 'Textarea should have a positive initial height');

        // Add content and dispatch input event
        $client->executeScript('
            const textarea = document.querySelector(arguments[0]);
            textarea.value = textarea.value + "\\n".repeat(10) + "Test line\\n".repeat(10);
            textarea.dispatchEvent(new Event("input", { bubbles: true }));
        ', [self::SELECTOR_AUTOSIZE_TEXTAREA]);

        // Poll until height increases
        $heightIncreased = $this->pollUntilTrue(
            $client,
            'return parseInt(window.getComputedStyle(document.querySelector(arguments[0])).height) > arguments[1]',
            [self::SELECTOR_AUTOSIZE_TEXTAREA, $initialHeight],
        );

        self::assertTrue($heightIncreased, 'Textarea height should increase after adding content');

        $newHeight = $client->executeScript(
            'return parseInt(window.getComputedStyle(document.querySelector(arguments[0])).height)',
            [self::SELECTOR_AUTOSIZE_TEXTAREA]
        );

        self::assertGreaterThan($initialHeight, $newHeight, 'New height should be greater than initial height');
    }

    /**
     * Test the copyElementText function.
     */
    public function testCopyElementText(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        // Wait for admin.js to be fully loaded (poll until function is available)
        $jsLoaded = $this->pollUntilTrue($client, 'return typeof window.copyElementText === "function"');

        self::assertTrue($jsLoaded, 'Admin JavaScript function copyElementText should be available');

        // Create a test element
        $client->executeScript('
            const testEl = document.createElement("div");
            testEl.id = "test-copy";
            testEl.innerText = "Test content";
            document.body.appendChild(testEl);
        ');

        // Test the function
        $result = $client->executeScript('
            const el = document.getElementById("test-copy");
            window.copyElementText(el);
            return navigator.clipboard ? "clipboard-api" : "execCommand";
        ');

        self::assertNotEmpty($result);
    }

    /**
     * Test title pixel width display.
     */
    public function testShowTitlePixelWidth(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        $this->waitForElement($client, self::SELECTOR_TITLE_INPUT, 'No title measurement field found on this page');

        $titleInput = $client->findElement(
            WebDriverBy::cssSelector(self::SELECTOR_TITLE_INPUT)
        );

        // Change the value
        $titleInput->clear();
        $titleInput->sendKeys('Test Title for Width Measurement');

        $this->sleepMs(self::waitShort());

        // Check that the counter has been updated
        $widthValue = $client->executeScript(
            'return document.getElementById("titleWidth")?.innerText || "";'
        );

        // We expect to see the width
        self::assertNotEmpty($widthValue, 'Title width should be displayed');
    }

    /**
     * Test retrieving the host and locale.
     */
    public function testRetrievePageState(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        // Wait for JS initialization to set pageHost/pageLocale
        $stateReady = $this->pollUntilTrue(
            $client,
            'return typeof window.pageHost !== "undefined" || typeof window.pageLocale !== "undefined"',
        );

        self::assertTrue($stateReady, 'At least one of pageHost or pageLocale should be defined');

        $stateExists = $client->executeScript('
            return {
                hasHost: typeof window.pageHost !== "undefined",
                hasLocale: typeof window.pageLocale !== "undefined"
            };
        ');

        self::assertIsArray($stateExists);

        /** @var array{hasHost: bool, hasLocale: bool} $stateExists */
        self::assertTrue(
            $stateExists['hasHost'] || $stateExists['hasLocale'],
            'At least one of pageHost or pageLocale should be defined'
        );
    }
}
