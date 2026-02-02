<?php

namespace Pushword\Admin\Tests\Frontend;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\Client;

/**
 * Integration tests for the admin media picker.
 * Uses Symfony Panther to test in a real browser.
 */
#[Group('panther')]
class AdminMediaPickerTest extends AbstractPantherAdminTest
{
    private const string SELECTOR_MEDIA_PICKER = '[data-pw-media-picker]';

    private const string SELECTOR_MEDIA_PICKER_CHOOSE = '[data-pw-media-picker-action="choose"]';

    private const string SELECTOR_MEDIA_PICKER_UPLOAD = '[data-pw-media-picker-action="upload"]';

    private const string SELECTOR_MEDIA_PICKER_REMOVE = '[data-pw-media-picker-action="remove"]';

    private const string SELECTOR_MEDIA_PICKER_MODAL = '#pw-media-picker-modal';

    private const string SELECTOR_MEDIA_PICKER_IFRAME = '.pw-admin-popup-iframe';

    /**
     * Ensures the media picker is present, initialized and returns its select element ID.
     */
    private function ensureMediaPickerReady(Client $client): string
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
     * Closes the media picker modal if open.
     */
    private function closeMediaPickerModal(Client $client): void
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
}
