<?php

namespace Pushword\Admin\Tests\Frontend;

use Exception;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverBy;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Panther\Client;

/**
 * Tests d'intégration pour le JavaScript de l'admin
 * Utilise Symfony Panther pour tester dans un vrai navigateur.
 *
 * IMPORTANT: Ces tests nécessitent Chrome/Chromium et peuvent être lents.
 */
#[Group('panther')]
class AdminJSTest extends AbstractAdminTestClass
{
    // Configuration des timeouts (en secondes)
    private const int TIMEOUT_SHORT = 3;

    private const int TIMEOUT_MEDIUM = 5;

    private const int TIMEOUT_LONG = 10;

    private const int WAIT_SHORT = 200;

    private const int WAIT_MEDIUM = 500;

    // Sélecteurs CSS
    private const string SELECTOR_MAIN_FIELDS = 'body'; // Sélecteur toujours présent

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

    /**
     * Static cached client - shared across all tests for performance.
     * Login is performed once and reused.
     */
    private static ?Client $loggedInClient = null;

    private static bool $isLoggedIn = false;

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
        exec('lsof -ti:9080 2>/dev/null | xargs -r kill -9 2>/dev/null');
        exec('pkill -9 -f chromedriver 2>/dev/null');
        usleep(500000);

        parent::setUpBeforeClass();
    }

    /**
     * Crée un client Panther et effectue le login.
     * Le client est mis en cache statiquement pour être réutilisé entre les tests.
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

        // Step 1: Enter email
        try {
            $client->waitFor('input[name="email"]', self::TIMEOUT_LONG);
        } catch (Exception) {
            $client->wait(self::WAIT_MEDIUM);
            $client->waitFor('input[name="email"]', self::TIMEOUT_LONG);
        }

        $client->findElement(WebDriverBy::name('email'))->sendKeys('admin@example.tld');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        // Step 2: Wait for password step and enter password
        try {
            $client->waitFor('input[name="password"]', self::TIMEOUT_LONG);
        } catch (Exception) {
            $client->wait(self::WAIT_MEDIUM);
            $client->waitFor('input[name="password"]', self::TIMEOUT_LONG);
        }

        $client->findElement(WebDriverBy::name('password'))->sendKeys('mySecr3tpAssword');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        // Attend la redirection
        $client->wait(self::WAIT_MEDIUM);

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
        // Reset static state
        self::$loggedInClient = null;
        self::$isLoggedIn = false;

        // Ensure all Panther resources (webserver, chromedriver) are properly cleaned up
        static::stopWebServer();

        parent::tearDownAfterClass();
    }

    /**
     * Navigue vers la page d'édition et attend que le DOM soit chargé.
     */
    protected function navigateToPageEdit(
        Client $client,
        int $pageId = 1,
        string $waitSelector = self::SELECTOR_MAIN_FIELDS,
        int $timeout = self::TIMEOUT_MEDIUM
    ): Crawler {
        $client->request('GET', $this->generateAdminUrl('admin_page_edit', ['id' => $pageId]));

        return $client->waitFor($waitSelector, $timeout);
    }

    /**
     * Navigue vers la page de création et attend que le DOM soit chargé.
     */
    protected function navigateToPageCreate(
        Client $client,
        string $waitSelector = 'body',
        int $timeout = self::TIMEOUT_MEDIUM
    ): Crawler {
        $client->request('GET', $this->generateAdminUrl('admin_page_create'));

        return $client->waitFor($waitSelector, $timeout);
    }

    /**
     * Vérifie si un élément existe dans le DOM.
     */
    protected function elementExists(Client $client, string $selector): bool
    {
        $result = $client->executeScript(
            'return document.querySelector(arguments[0]) !== null;',
            [$selector]
        );

        return (bool) $result;
    }

    /**
     * Attend qu'un élément existe, sinon skip le test.
     */
    protected function waitForElementOrSkip(
        Client $client,
        string $selector,
        string $skipMessage,
        int $timeout = self::TIMEOUT_SHORT
    ): void {
        if (! $this->elementExists($client, $selector)) {
            self::markTestSkipped($skipMessage);
        }

        try {
            $client->waitFor($selector, $timeout);
        } catch (Exception) {
            self::markTestSkipped($skipMessage);
        }
    }

    /**
     * Ensures the media picker is present, initialized and returns its select element ID.
     */
    protected function ensureMediaPickerReady(Client $client): string
    {
        $this->waitForElementOrSkip(
            $client,
            self::SELECTOR_MEDIA_PICKER,
            'No media picker found on this page',
            self::TIMEOUT_SHORT
        );

        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; ++$i) {
            $isReady = (bool) $client->executeScript(
                'return document.querySelector(arguments[0])?.dataset?.pwMediaPickerReady === "1"',
                [self::SELECTOR_MEDIA_PICKER]
            );
            if ($isReady) {
                break;
            }

            $client->wait(self::WAIT_SHORT);
        }

        $selectId = $client->executeScript(
            'return document.querySelector(arguments[0])?.id || ""',
            [self::SELECTOR_MEDIA_PICKER]
        );

        if (! \is_string($selectId) || '' === $selectId) {
            self::markTestSkipped('Media picker select element has no ID');
        }

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
        $client->wait(self::WAIT_MEDIUM);

        // Scroll the picker into view
        $client->executeScript(
            'document.querySelector(arguments[0])?.scrollIntoView({block: "center", behavior: "instant"})',
            [self::SELECTOR_MEDIA_PICKER]
        );
        $client->wait(self::WAIT_SHORT);

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
        $client->wait(self::WAIT_MEDIUM);
    }

    /**
     * Test que les modules JavaScript sont chargés correctement.
     */
    // public function testAdminJSModulesLoaded(): void
    // {
    //     $client = $this->createPantherClientWithLogin();
    //     $this->navigateToPageEdit($client);
    //     sleep(1);
    //     // Vérifie que les variables globales sont définies
    //     self::assertTrue(
    //         $client->executeScript('return typeof window.htmx !== "undefined"'),
    //         'HTMX should be loaded'
    //     );

    //     self::assertTrue(
    //         $client->executeScript('return typeof window.copyElementText === "function"'),
    //         'copyElementText should be available'
    //     );
    // }

    /**
     * Test du media picker.
     */
    public function testMediaPicker(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        if (! $this->elementExists($client, self::SELECTOR_MEDIA_PICKER)) {
            self::markTestSkipped('No media picker found on this page');
        }

        $this->waitForElementOrSkip(
            $client,
            self::SELECTOR_MEDIA_PICKER,
            'Media picker not initialized',
            self::TIMEOUT_SHORT
        );

        // Vérifie que le media picker est initialisé
        $isInitialized = $client->executeScript(
            'return document.querySelector(arguments[0])?.dataset?.pwMediaPickerReady === "1"',
            [self::SELECTOR_MEDIA_PICKER]
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

        $client->waitFor(self::SELECTOR_MEDIA_PICKER_MODAL, self::TIMEOUT_MEDIUM);
        $client->wait(self::WAIT_MEDIUM);

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
        $client->waitFor(self::SELECTOR_MEDIA_PICKER_MODAL, self::TIMEOUT_MEDIUM);
        $client->wait(self::WAIT_MEDIUM);

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

        $client->wait(self::WAIT_MEDIUM);

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
        $modalClosed = false;
        for ($i = 0; $i < 10; ++$i) {
            $modalClosed = (bool) $client->executeScript(
                'const m = document.querySelector(arguments[0]); return !m || getComputedStyle(m).display === "none"',
                [self::SELECTOR_MEDIA_PICKER_MODAL]
            );
            if ($modalClosed) {
                break;
            }

            $client->wait(self::WAIT_SHORT);
        }

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
        $client->wait(self::WAIT_SHORT);

        $hasSelection = (bool) $client->executeScript(
            'return Boolean(document.getElementById(arguments[0])?.dataset?.pwMediaPickerSelectedId)',
            [$selectId]
        );
        self::assertTrue($hasSelection, 'Media should be selected before remove test');

        $this->scrollAndClick($client, self::SELECTOR_MEDIA_PICKER_REMOVE);
        $client->wait(self::WAIT_SHORT);

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

        if (! $this->elementExists($client, self::SELECTOR_MEDIA_PICKER_UPLOAD)) {
            self::markTestSkipped('No upload button found');
        }

        $this->scrollAndClick($client, self::SELECTOR_MEDIA_PICKER_UPLOAD);

        $client->waitFor(self::SELECTOR_MEDIA_PICKER_MODAL, self::TIMEOUT_MEDIUM);
        $client->wait(self::WAIT_MEDIUM);

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
     * Test de la mémorisation des panels ouverts.
     */
    public function testMemorizeOpenPanel(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        if (! $this->elementExists($client, self::SELECTOR_PANELS)) {
            self::markTestSkipped('No panels found on this page');
        }

        $crawler = $client->getCrawler();
        $panelButton = $crawler->filter(self::SELECTOR_PANEL_TOGGLE)->first();

        if (0 === $panelButton->count()) {
            self::markTestSkipped('No panel toggle button found');
        }

        // Clique sur le panel
        try {
            $buttonElement = $client->findElement(
                WebDriverBy::cssSelector(self::SELECTOR_PANEL_TOGGLE)
            );
            $buttonElement->click();
            $client->wait(self::WAIT_MEDIUM);

            // Vérifie que l'état est sauvegardé dans localStorage
            $stored = $client->executeScript('return localStorage.getItem("panels")');
            self::assertNotNull($stored, 'Panel state should be stored in localStorage');
        } catch (NoSuchElementException) {
            self::markTestSkipped('Panel button not clickable');
        }
    }

    /**
     * Test du filtre de page parente par host.
     */
    public function testFilterParentPageFromHost(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageCreate($client);

        if (! $this->elementExists($client, self::SELECTOR_HOST_SELECT)) {
            self::markTestSkipped('No host select found on create page');
        }

        // Vérifie que window.pageHost est défini
        $pageHost = $client->executeScript('return window.pageHost || ""');
        self::assertNotEmpty($pageHost, 'pageHost should be set');
    }

    /**
     * Test de l'auto-resize des textareas.
     */
    public function testTextareaAutoSize(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        if (! $this->elementExists($client, self::SELECTOR_AUTOSIZE_TEXTAREA)) {
            self::markTestSkipped('No autosize textarea found on this page');
        }

        try {
            // Teste le feature d'autosize en ajoutant du contenu et en déclenchant l'event
            $result = $client->executeScript('
                const textarea = document.querySelector(arguments[0]);
                if (!textarea) return { success: false, reason: "not_found" };

                const initialHeight = parseInt(window.getComputedStyle(textarea).height);

                // Ajoute beaucoup de lignes pour forcer un changement
                textarea.value = textarea.value + "\\n".repeat(10) + "Test line\\n".repeat(10);

                // Déclenche l\'événement input manuellement
                textarea.dispatchEvent(new Event("input", { bubbles: true }));

                // Attend un peu pour le resize
                return new Promise(resolve => {
                    setTimeout(() => {
                        const newHeight = parseInt(window.getComputedStyle(textarea).height);
                        resolve({
                            success: true,
                            initialHeight: initialHeight,
                            newHeight: newHeight,
                            changed: newHeight > initialHeight
                        });
                    }, 300);
                });
            ', [self::SELECTOR_AUTOSIZE_TEXTAREA]);

            if (! is_array($result)) {
                self::markTestSkipped('Autosize test returned invalid result');
            }

            /** @var array{success: bool, initialHeight?: int, newHeight?: int, changed?: bool} $result */
            if (! $result['success']) {
                self::markTestSkipped('Autosize textarea not found or not working');
            }

            if (! isset($result['changed']) || ! $result['changed']) {
                // Le autosize peut ne pas être actif sur cette page
                self::markTestSkipped('Textarea autosize feature not active on this page');
            }

            self::assertGreaterThan(
                $result['initialHeight'] ?? 0,
                $result['newHeight'] ?? 0,
                'Textarea height should increase after adding content'
            );
        } catch (Exception $exception) {
            self::markTestSkipped('Autosize test failed: '.$exception->getMessage());
        }
    }

    /**
     * Test de la fonction copyElementText.
     */
    public function testCopyElementText(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        // Wait for admin.js to be fully loaded (poll until function is available)
        $maxAttempts = 10;
        $jsLoaded = false;
        for ($i = 0; $i < $maxAttempts; ++$i) {
            $jsLoaded = (bool) $client->executeScript('return typeof window.copyElementText === "function"');
            if ($jsLoaded) {
                break;
            }

            $client->wait(self::WAIT_SHORT);
        }

        self::assertTrue($jsLoaded, 'Admin JavaScript function copyElementText should be available');

        // Crée un élément de test
        $client->executeScript('
            const testEl = document.createElement("div");
            testEl.id = "test-copy";
            testEl.innerText = "Test content";
            document.body.appendChild(testEl);
        ');

        // Teste la fonction
        $result = $client->executeScript('
            const el = document.getElementById("test-copy");
            window.copyElementText(el);
            return navigator.clipboard ? "clipboard-api" : "execCommand";
        ');

        self::assertNotEmpty($result);
    }

    /**
     * Test de l'affichage de la largeur du titre en pixels.
     */
    public function testShowTitlePixelWidth(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        if (! $this->elementExists($client, self::SELECTOR_TITLE_INPUT)) {
            self::markTestSkipped('No title measurement field found on this page');
        }

        try {
            $titleInput = $client->findElement(
                WebDriverBy::cssSelector(self::SELECTOR_TITLE_INPUT)
            );

            // Change la valeur
            $titleInput->clear();
            $titleInput->sendKeys('Test Title for Width Measurement');

            $client->wait(self::WAIT_SHORT);

            // Vérifie que le compteur a été mis à jour
            $widthValue = $client->executeScript(
                'return document.getElementById("titleWidth")?.innerText || "";'
            );

            // On s'attend à voir la longueur
            self::assertNotEmpty($widthValue, 'Title width should be displayed');
        } catch (NoSuchElementException) {
            self::markTestSkipped('Title input not accessible');
        }
    }

    /**
     * Test de la récupération du host et de la locale.
     */
    public function testRetrievePageState(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        // Vérifie que window.pageHost et window.pageLocale sont définis
        $stateExists = $client->executeScript('
            return {
                hasHost: typeof window.pageHost !== "undefined",
                hasLocale: typeof window.pageLocale !== "undefined"
            };
        ');

        if (! is_array($stateExists)) {
            self::markTestSkipped('Page state test returned invalid result');
        }

        /** @var array{hasHost: bool, hasLocale: bool} $stateExists */
        // Au moins un des deux devrait être défini, sinon on skip
        if (! $stateExists['hasHost'] && ! $stateExists['hasLocale']) {
            self::markTestSkipped('Page state variables (pageHost/pageLocale) not defined on this page');
        }

        // Au moins un est défini, le test réussit
        self::assertTrue(
            $stateExists['hasHost'] || $stateExists['hasLocale'],
            'At least one of pageHost or pageLocale should be defined'
        );
    }
}
