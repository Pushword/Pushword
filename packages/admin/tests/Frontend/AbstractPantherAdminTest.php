<?php

namespace Pushword\Admin\Tests\Frontend;

use Exception;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\WebDriverBy;
use Override;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Panther\Client;
use Throwable;

/**
 * Shared Panther infrastructure for admin browser tests.
 * Manages a cached Chrome client, login, navigation helpers and polling utilities.
 */
abstract class AbstractPantherAdminTest extends AbstractAdminTestClass
{
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

        // Assign unique web server port per class so parallel workers don't conflict
        if (! isset($_SERVER['PANTHER_WEB_SERVER_PORT'])) {
            $port = 9080 + (abs(crc32(static::class)) % 100);
            $_SERVER['PANTHER_WEB_SERVER_PORT'] = (string) $port;
        }

        // Clean up stale processes only when NOT running in parallel
        // (paratest sets TEST_TOKEN; pkill would kill other workers' chromedriver)
        $testToken = getenv('TEST_TOKEN');
        if (false === $testToken || '' === $testToken) {
            $port = $_SERVER['PANTHER_WEB_SERVER_PORT'];
            exec('lsof -ti:'.$port.' 2>/dev/null | xargs -r kill -9 2>/dev/null');
            exec('pkill -9 -f chromedriver 2>/dev/null');
            usleep(500000);
        }

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
        // Clear our cached reference; Panther's stopWebServer() will quit the client
        self::$loggedInClient = null;
        self::$isLoggedIn = false;

        try {
            static::stopWebServer();
        } catch (Throwable) {
            // ChromeDriver may already be stopped
        }

        parent::tearDownAfterClass();
    }

    /**
     * Navigates to the edit page and waits for the DOM to load.
     */
    protected function navigateToPageEdit(
        Client $client,
        int $pageId = 1,
        string $waitSelector = 'body',
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
}
