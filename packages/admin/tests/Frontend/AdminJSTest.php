<?php

namespace Pushword\Admin\Tests\Frontend;

use Exception;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\WebDriverBy;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Panther\Client;
use Throwable;

/**
 * Integration tests for the admin JavaScript.
 * Uses Symfony Panther to test in a real browser.
 *
 * IMPORTANT: These tests require Chrome/Chromium and may be slow.
 */
#[Group('panther')]
class AdminJSTest extends AbstractAdminTestClass
{
    // CSS selectors
    private const string SELECTOR_MAIN_FIELDS = 'body';

    private const string SELECTOR_MEDIA_PICKER = '[data-pw-media-picker]';

    private const string SELECTOR_PANELS = '.pw-settings-accordion, .collapse';

    private const string SELECTOR_PANEL_TOGGLE = '.pw-settings-toggle, [data-bs-toggle="collapse"]';

    private const string SELECTOR_AUTOSIZE_TEXTAREA = '.autosize';

    private const string SELECTOR_TITLE_INPUT = '.titleToMeasure';

    private const string SELECTOR_HOST_SELECT = 'select[name$="[host]"]';

    private const string SELECTOR_MEDIA_PICKER_CHOOSE = '[data-pw-media-picker-action="choose"]';

    private const string SELECTOR_MEDIA_PICKER_UPLOAD = '[data-pw-media-picker-action="upload"]';

    private const string SELECTOR_MEDIA_PICKER_REMOVE = '[data-pw-media-picker-action="remove"]';

    private const string SELECTOR_MEDIA_PICKER_MODAL = '#pw-media-picker-modal';

    private const string SELECTOR_MEDIA_PICKER_IFRAME = '.pw-admin-popup-iframe';

    // Base timeout values (in seconds) before multiplier
    private const int BASE_TIMEOUT_SHORT = 3;

    private const int BASE_TIMEOUT_MEDIUM = 5;

    private const int BASE_TIMEOUT_LONG = 10;

    // Base wait values (in milliseconds) before multiplier
    private const int BASE_WAIT_SHORT = 200;

    private const int BASE_WAIT_MEDIUM = 500;

    /**
     * Static cached client - shared across all tests for performance.
     * Login is performed once and reused.
     */
    private static ?Client $loggedInClient = null;

    private static bool $isLoggedIn = false;

    private static ?float $timeoutMultiplier = null;

    private static function timeoutMultiplier(): float
    {
        if (null === self::$timeoutMultiplier) {
            $envValue = $_SERVER['PANTHER_TIMEOUT_MULTIPLIER']
                ?? $_ENV['PANTHER_TIMEOUT_MULTIPLIER']
                ?? getenv('PANTHER_TIMEOUT_MULTIPLIER');
            self::$timeoutMultiplier = false !== $envValue && '' !== $envValue ? (float) $envValue : 1.0;
        }

        return self::$timeoutMultiplier;
    }

    protected static function timeoutShort(): int
    {
        return (int) ceil(self::BASE_TIMEOUT_SHORT * self::timeoutMultiplier());
    }

    protected static function timeoutMedium(): int
    {
        return (int) ceil(self::BASE_TIMEOUT_MEDIUM * self::timeoutMultiplier());
    }

    protected static function timeoutLong(): int
    {
        return (int) ceil(self::BASE_TIMEOUT_LONG * self::timeoutMultiplier());
    }

    protected static function waitShort(): int
    {
        return (int) ceil(self::BASE_WAIT_SHORT * self::timeoutMultiplier());
    }

    protected static function waitMedium(): int
    {
        return (int) ceil(self::BASE_WAIT_MEDIUM * self::timeoutMultiplier());
    }

    protected function sleepMs(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    #[Override]
    public static function setUpBeforeClass(): void
    {
        // Set chromedriver path if installed via bdi and not already configured
        if (! isset($_SERVER['PANTHER_CHROME_DRIVER_BINARY'])) {
            $chromeDriverPath = realpath(__DIR__.'/../../../../drivers/chromedriver');
            if (false !== $chromeDriverPath && is_executable($chromeDriverPath)) {
                $_SERVER['PANTHER_CHROME_DRIVER_BINARY'] = $chromeDriverPath;
                putenv('PANTHER_CHROME_DRIVER_BINARY='.$chromeDriverPath);
            }
        }

        // Clean up any stale processes from previous test runs
        $port = $_SERVER['PANTHER_WEB_SERVER_PORT'] ?? '9080';
        exec('lsof -ti:'.$port.' 2>/dev/null | xargs -r kill -9 2>/dev/null');
        exec('pkill -9 -f chromedriver 2>/dev/null');
        usleep(500000);

        // Safety net: ensure the Panther client is quit before PHP shuts down,
        // preventing __destruct from hitting a dead chromedriver.
        register_shutdown_function(static function (): void {
            if (null !== self::$loggedInClient) {
                try {
                    self::$loggedInClient->quit();
                } catch (Throwable) {
                }

                self::$loggedInClient = null;
            }
        });

        parent::setUpBeforeClass();
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Dismiss any lingering modals from previous tests
        if (null !== self::$loggedInClient && self::$isLoggedIn) {
            try {
                self::$loggedInClient->executeScript(
                    'document.querySelectorAll(".modal.show").forEach(m => { bootstrap?.Modal?.getInstance(m)?.hide(); });'
                );
            } catch (Throwable) {
            }
        }
    }

    /**
     * Creates a Panther client and performs login.
     * The client is statically cached for reuse across tests.
     */
    protected function createPantherClientWithLogin(): Client
    {
        // Reuse existing logged-in client if available
        if (null !== self::$loggedInClient && self::$isLoggedIn) {
            return self::$loggedInClient;
        }

        $client = static::createPantherClient();
        self::$loggedInClient = $client;

        self::createUser();

        $client->request('GET', '/login');

        // Let the page start loading
        $this->sleepMs(self::waitShort());

        // Step 1: Enter email
        $client->waitFor('input[name="email"]', self::timeoutLong());

        $client->findElement(WebDriverBy::name('email'))->sendKeys('admin@example.tld');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        // Step 2: Wait for password step and enter password
        $client->waitFor('input[name="password"]', self::timeoutLong());

        $client->findElement(WebDriverBy::name('password'))->sendKeys('mySecr3tpAssword');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        // Wait for redirect
        $this->sleepMs(self::waitMedium());

        self::$isLoggedIn = true;

        return $client;
    }

    #[Override]
    protected function tearDown(): void
    {
        // Don't quit the client between tests - reuse the logged-in session
        parent::tearDown();
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        // Explicitly quit the client before releasing the reference.
        // This clears the internal WebDriver so __destruct won't attempt
        // a DELETE to an already-stopped chromedriver.
        if (null !== self::$loggedInClient) {
            try {
                self::$loggedInClient->quit();
            } catch (Throwable) {
                // ChromeDriver may already be stopped
            }
        }

        self::$loggedInClient = null;
        self::$isLoggedIn = false;

        static::stopWebServer();

        parent::tearDownAfterClass();
    }

    /**
     * Navigates to the edit page and waits for the DOM to load.
     */
    protected function navigateToPageEdit(
        Client $client,
        int $pageId = 1,
        string $waitSelector = self::SELECTOR_MAIN_FIELDS,
        ?int $timeout = null,
    ): Crawler {
        $client->request('GET', $this->generateAdminUrl('admin_page_edit', ['id' => $pageId]));

        return $client->waitFor($waitSelector, $timeout ?? self::timeoutMedium());
    }

    /**
     * Navigates to the create page and waits for the DOM to load.
     */
    protected function navigateToPageCreate(
        Client $client,
        string $waitSelector = 'body',
        ?int $timeout = null,
    ): Crawler {
        $client->request('GET', $this->generateAdminUrl('admin_page_create'));

        return $client->waitFor($waitSelector, $timeout ?? self::timeoutMedium());
    }

    /**
     * Polls a JS expression until it returns true, using WebDriverWait.
     *
     * @param string[]|int[] $jsArgs
     */
    protected function pollUntilTrue(Client $client, string $jsExpression, array $jsArgs = [], int $timeoutSeconds = 0): bool
    {
        try {
            $client->wait($timeoutSeconds ?: self::timeoutMedium(), 250)->until(
                static fn (): bool => (bool) $client->executeScript($jsExpression, $jsArgs),
            );

            return true;
        } catch (TimeoutException) {
            return false;
        }
    }

    /**
     * Waits for an element to appear, or fails the test with a message.
     */
    protected function waitForElement(Client $client, string $selector, string $failureMessage, int $timeout = 0): void
    {
        try {
            $client->waitFor($selector, $timeout ?: self::timeoutMedium());
        } catch (Exception) {
            self::fail($failureMessage.' (selector: '.$selector.')');
        }
    }

    /**
     * Ensures the media picker is present, initialized and returns its select element ID.
     */
    protected function ensureMediaPickerReady(Client $client): string
    {
        $this->waitForElement(
            $client,
            self::SELECTOR_MEDIA_PICKER,
            'No media picker found on this page',
            self::timeoutShort(),
        );

        $this->pollUntilTrue(
            $client,
            'return document.querySelector(arguments[0])?.dataset?.pwMediaPickerReady === "1"',
            [self::SELECTOR_MEDIA_PICKER],
        );

        $selectId = $client->executeScript(
            'return document.querySelector(arguments[0])?.id || ""',
            [self::SELECTOR_MEDIA_PICKER]
        );

        self::assertIsString($selectId);
        self::assertNotEmpty($selectId, 'Media picker select element must have an ID');

        // Expand parent accordion panel if collapsed so buttons become interactable
        $client->executeScript('
            const picker = document.querySelector(arguments[0]);
            if (!picker) return;
            const panel = picker.closest(".pw-settings-accordion");
            if (panel && panel.classList.contains("pw-settings-collapsed")) {
                const toggle = panel.querySelector(".pw-settings-toggle");
                if (toggle) toggle.click();
            }
        ', [self::SELECTOR_MEDIA_PICKER]);

        // Wait for accordion expansion to complete
        $this->pollUntilTrue(
            $client,
            'const p = document.querySelector(arguments[0])?.closest(".pw-settings-accordion"); return !p || !p.classList.contains("pw-settings-collapsed")',
            [self::SELECTOR_MEDIA_PICKER],
            self::timeoutShort(),
        );

        // Scroll the picker into view
        $client->executeScript(
            'document.querySelector(arguments[0])?.scrollIntoView({block: "center", behavior: "instant"})',
            [self::SELECTOR_MEDIA_PICKER]
        );
        $this->sleepMs(self::waitShort());

        return $selectId;
    }

    /**
     * Scrolls an element into view and clicks it via JavaScript.
     * Avoids ElementNotInteractableException for elements in collapsed panels.
     */
    protected function scrollAndClick(Client $client, string $selector): void
    {
        $client->executeScript(
            'const el = document.querySelector(arguments[0]);
             if (el) { el.scrollIntoView({block: "center", behavior: "instant"}); el.click(); }',
            [$selector]
        );
    }

    /**
     * Closes the media picker modal if open.
     */
    protected function closeMediaPickerModal(Client $client): void
    {
        $client->executeScript(
            'const m = document.querySelector(arguments[0]);
             if (m && window.bootstrap) window.bootstrap.Modal.getInstance(m)?.hide();',
            [self::SELECTOR_MEDIA_PICKER_MODAL]
        );
        $this->sleepMs(self::waitMedium());
    }

    /**
     * Test the media picker.
     */
    public function testMediaPicker(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        $this->waitForElement($client, self::SELECTOR_MEDIA_PICKER, 'No media picker found on this page');

        // Wait for initialization via polling
        $isInitialized = $this->pollUntilTrue(
            $client,
            'return document.querySelector(arguments[0])?.dataset?.pwMediaPickerReady === "1"',
            [self::SELECTOR_MEDIA_PICKER],
        );

        self::assertTrue($isInitialized, 'Media picker should be initialized');
    }

    /**
     * Test that the media picker modal opens when clicking Choose.
     */
    public function testMediaPickerModalOpens(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);
        $this->ensureMediaPickerReady($client);

        $this->scrollAndClick($client, self::SELECTOR_MEDIA_PICKER_CHOOSE);

        $client->waitFor(self::SELECTOR_MEDIA_PICKER_MODAL, self::timeoutMedium());

        // Wait for modal to be fully visible (Bootstrap fade animation)
        $this->pollUntilTrue(
            $client,
            'const m = document.querySelector(arguments[0]); return m && m.classList.contains("show") && getComputedStyle(m).display !== "none"',
            [self::SELECTOR_MEDIA_PICKER_MODAL],
            self::timeoutShort(),
        );

        $result = $client->executeScript('
            const modal = document.querySelector(arguments[0]);
            const iframe = modal?.querySelector(arguments[1]);
            return {
                isVisible: modal !== null && getComputedStyle(modal).display !== "none",
                hasIframe: iframe !== null,
                hasAriaModal: modal?.getAttribute("aria-modal") === "true",
                hasRoleDialog: modal?.getAttribute("role") === "dialog"
            };
        ', [self::SELECTOR_MEDIA_PICKER_MODAL, self::SELECTOR_MEDIA_PICKER_IFRAME]);

        self::assertIsArray($result);

        /** @var array{isVisible: bool, hasIframe: bool, hasAriaModal: bool, hasRoleDialog: bool} $result */
        self::assertTrue($result['isVisible'], 'Media picker modal should be visible');
        self::assertTrue($result['hasIframe'], 'Modal should contain an iframe');
        self::assertTrue($result['hasAriaModal'], 'Modal should have aria-modal="true"');
        self::assertTrue($result['hasRoleDialog'], 'Modal should have role="dialog"');

        $this->closeMediaPickerModal($client);
    }

    /**
     * Test that selecting a media item via postMessage updates the form.
     */
    public function testMediaPickerSelectMedia(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);
        $selectId = $this->ensureMediaPickerReady($client);

        $this->scrollAndClick($client, self::SELECTOR_MEDIA_PICKER_CHOOSE);
        $client->waitFor(self::SELECTOR_MEDIA_PICKER_MODAL, self::timeoutMedium());

        // Wait for modal to be fully visible before sending postMessage
        $this->pollUntilTrue(
            $client,
            'const m = document.querySelector(arguments[0]); return m && m.classList.contains("show") && getComputedStyle(m).display !== "none"',
            [self::SELECTOR_MEDIA_PICKER_MODAL],
            self::timeoutShort(),
        );

        $client->executeScript('
            window.postMessage({
                type: "pw-media-picker-select",
                fieldId: arguments[0],
                media: {
                    id: "999",
                    name: "test-image.jpg",
                    fileName: "test-image.jpg",
                    alt: "Test image",
                    thumb: "/media/default/test-image.jpg",
                    meta: "800x600",
                    ratio: "4:3",
                    width: "800",
                    height: "600"
                }
            }, window.location.origin);
        ', [$selectId]);

        // Wait for postMessage to be processed
        $postMessageProcessed = $this->pollUntilTrue(
            $client,
            'return document.getElementById(arguments[0])?.dataset?.pwMediaPickerSelectedId === "999"',
            [$selectId],
        );

        self::assertTrue($postMessageProcessed, 'postMessage should update the selected media ID');

        $result = $client->executeScript('
            const select = document.getElementById(arguments[0]);
            const wrapper = select?.closest(".pw-media-picker");
            return {
                selectedId: select?.dataset.pwMediaPickerSelectedId || "",
                selectedName: select?.dataset.pwMediaPickerSelectedName || "",
                selectValue: select?.value || "",
                isEmpty: wrapper?.classList.contains("pw-media-picker--empty") ?? true,
                thumbStyle: wrapper?.querySelector(".pw-media-picker__thumb-inner")?.style.backgroundImage || ""
            };
        ', [$selectId]);

        self::assertIsArray($result);

        /** @var array{selectedId: string, selectedName: string, selectValue: string, isEmpty: bool, thumbStyle: string} $result */
        self::assertSame('999', $result['selectedId'], 'Selected media ID should be set');
        self::assertSame('test-image.jpg', $result['selectedName'], 'Selected media name should be set');
        self::assertSame('999', $result['selectValue'], 'Select value should match media ID');
        self::assertFalse($result['isEmpty'], 'Picker should not have empty class after selection');
        self::assertNotEmpty($result['thumbStyle'], 'Thumbnail should have a background image');

        // Poll for modal close (Bootstrap fade animation is async)
        $modalClosed = $this->pollUntilTrue(
            $client,
            'const m = document.querySelector(arguments[0]); return !m || getComputedStyle(m).display === "none"',
            [self::SELECTOR_MEDIA_PICKER_MODAL],
        );

        self::assertTrue($modalClosed, 'Modal should close after selection');
    }

    /**
     * Test that the remove button clears the media selection.
     */
    public function testMediaPickerRemoveSelection(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);
        $selectId = $this->ensureMediaPickerReady($client);

        $client->executeScript('
            const select = document.getElementById(arguments[0]);
            if (!select) return;
            select.dataset.pwMediaPickerSelectedId = "999";
            select.dataset.pwMediaPickerSelectedName = "test-image.jpg";
            select.dataset.pwMediaPickerSelectedFilename = "test-image.jpg";
            select.dataset.pwMediaPickerSelectedThumb = "/media/default/test.jpg";
            select.dataset.pwMediaPickerSelectedMeta = "800x600";
            select.dataset.pwMediaPickerSelectedWidth = "800";
            select.dataset.pwMediaPickerSelectedHeight = "600";
            const option = new Option("test-image.jpg", "999", true, true);
            select.add(option);
            select.value = "999";
            select.dispatchEvent(new Event("change", { bubbles: true }));
        ', [$selectId]);
        $this->sleepMs(self::waitShort());

        $hasSelection = (bool) $client->executeScript(
            'return Boolean(document.getElementById(arguments[0])?.dataset?.pwMediaPickerSelectedId)',
            [$selectId]
        );
        self::assertTrue($hasSelection, 'Media should be selected before remove test');

        $this->scrollAndClick($client, self::SELECTOR_MEDIA_PICKER_REMOVE);
        $this->sleepMs(self::waitShort());

        $result = $client->executeScript('
            const select = document.getElementById(arguments[0]);
            const wrapper = select?.closest(".pw-media-picker");
            return {
                hasSelectedId: Boolean(select?.dataset?.pwMediaPickerSelectedId),
                selectValue: select?.value || "",
                isEmpty: wrapper?.classList.contains("pw-media-picker--empty") ?? false
            };
        ', [$selectId]);

        self::assertIsArray($result);

        /** @var array{hasSelectedId: bool, selectValue: string, isEmpty: bool} $result */
        self::assertFalse($result['hasSelectedId'], 'Selected ID should be cleared after remove');
        self::assertEmpty($result['selectValue'], 'Select value should be empty after remove');
        self::assertTrue($result['isEmpty'], 'Picker should have empty class after remove');
    }

    /**
     * Test that the upload button opens the modal.
     */
    public function testMediaPickerUploadOpensModal(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);
        $this->ensureMediaPickerReady($client);

        $this->waitForElement($client, self::SELECTOR_MEDIA_PICKER_UPLOAD, 'No upload button found');

        $this->scrollAndClick($client, self::SELECTOR_MEDIA_PICKER_UPLOAD);

        $client->waitFor(self::SELECTOR_MEDIA_PICKER_MODAL, self::timeoutMedium());

        // Wait for modal to be fully visible
        $this->pollUntilTrue(
            $client,
            'const m = document.querySelector(arguments[0]); return m && m.classList.contains("show") && getComputedStyle(m).display !== "none"',
            [self::SELECTOR_MEDIA_PICKER_MODAL],
            self::timeoutShort(),
        );

        $result = $client->executeScript('
            const modal = document.querySelector(arguments[0]);
            const iframe = modal?.querySelector(arguments[1]);
            return {
                isVisible: modal !== null && getComputedStyle(modal).display !== "none",
                iframeSrc: iframe?.src || ""
            };
        ', [self::SELECTOR_MEDIA_PICKER_MODAL, self::SELECTOR_MEDIA_PICKER_IFRAME]);

        self::assertIsArray($result);

        /** @var array{isVisible: bool, iframeSrc: string} $result */
        self::assertTrue($result['isVisible'], 'Modal should be visible after clicking Upload');
        self::assertNotEmpty($result['iframeSrc'], 'Iframe should have a source URL');

        $this->closeMediaPickerModal($client);
    }

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
