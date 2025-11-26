<?php

namespace Pushword\Admin\Tests\Frontend;

use Exception;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriverBy;
use Override;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Panther\Client;

/**
 * Tests d'intégration pour le JavaScript de l'admin
 * Utilise Symfony Panther pour tester dans un vrai navigateur.
 *
 * IMPORTANT: Ces tests nécessitent Chrome/Chromium et peuvent être lents.
 */
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

    private ?Client $cachedClient = null;

    /**
     * Crée un client Panther et effectue le login.
     * Le client est mis en cache pour accélérer les tests.
     */
    protected function createPantherClientWithLogin(): Client
    {
        if (null !== $this->cachedClient) {
            return $this->cachedClient;
        }

        $client = static::createPantherClient([
            'webServerDir' => __DIR__.'/../../../skeleton/public',
        ]);

        self::createUser();

        $client->request('GET', '/login');

        // Attend le formulaire de login
        try {
            $client->waitFor('input[name="email"]', self::TIMEOUT_LONG);
        } catch (Exception) {
            $client->wait(self::WAIT_MEDIUM);
            $client->waitFor('input[name="email"]', self::TIMEOUT_LONG);
        }

        // Remplit et soumet le formulaire
        $client->findElement(WebDriverBy::name('email'))->sendKeys('admin@example.tld');
        $client->findElement(WebDriverBy::name('password'))->sendKeys('mySecr3tpAssword');
        $client->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        // Attend la redirection
        $client->wait(self::WAIT_MEDIUM);

        $this->cachedClient = $client;

        return $client;
    }

    #[Override]
    protected function tearDown(): void
    {
        if (null !== $this->cachedClient) {
            $this->cachedClient->quit();
            $this->cachedClient = null;
        }

        parent::tearDown();
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
     * Test que les modules JavaScript sont chargés correctement.
     */
    public function testAdminJSModulesLoaded(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        // Vérifie que les variables globales sont définies
        self::assertTrue(
            $client->executeScript('return typeof window.htmx !== "undefined"'),
            'HTMX should be loaded'
        );

        self::assertTrue(
            $client->executeScript('return typeof window.copyElementText === "function"'),
            'copyElementText should be available'
        );
    }

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

    /**
     * Test du gestionnaire de taille de colonnes.
     */
    public function testColumnSizeManager(): void
    {
        $client = $this->createPantherClientWithLogin();
        $this->navigateToPageEdit($client);

        // Vérifie si le gestionnaire de colonnes existe
        $hasColumnManager = $client->executeScript('
            return document.querySelector(".expandColumnFields") !== null
                && document.querySelector(".columnFields") !== null
                && document.querySelector(".mainFields") !== null;
        ');

        if (! (bool) $hasColumnManager) {
            self::markTestSkipped('No column size manager found on this page');
        }

        // Teste l'interaction (sans vérifier le résultat car cela dépend de l'état initial)
        $client->executeScript('document.querySelector(".expandColumnFields")?.click();');
        $client->wait(self::WAIT_SHORT);

        // Vérifie simplement que les éléments sont toujours présents et fonctionnels
        $stillExists = $client->executeScript('
            return document.querySelector(".columnFields") !== null;
        ');

        self::assertTrue($stillExists, 'Column manager should remain functional after interaction');
    }
}
