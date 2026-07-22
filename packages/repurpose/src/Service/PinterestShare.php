<?php

namespace Pushword\Repurpose\Service;

/**
 * Builds the URL of Pinterest's "create pin" widget, pre-filled with the pin
 * image, the source page and the caption. Opening it drops the user into
 * Pinterest's own compose screen where they confirm and publish — the same
 * behaviour as Pinterest's official "Save"/"Pin it" browser button, so nothing
 * is posted on the user's behalf without their action.
 *
 * The widget takes a single `media` URL, so a "post directly" pin is the cover
 * slide as one image (a multi-image Pinterest carousel needs Pinterest's app or
 * API and is out of scope here).
 */
final class PinterestShare
{
    private const string ENDPOINT = 'https://www.pinterest.com/pin/create/button/';

    /** Pinterest truncates pin descriptions at 500 characters. */
    private const int MAX_DESCRIPTION = 500;

    public function pinUrl(string $mediaUrl, ?string $pageUrl, ?string $description): string
    {
        $params = ['media' => $mediaUrl];

        if (null !== $pageUrl && '' !== $pageUrl) {
            $params['url'] = $pageUrl;
        }

        if (null !== $description && '' !== $description) {
            $params['description'] = mb_substr($description, 0, self::MAX_DESCRIPTION);
        }

        return self::ENDPOINT.'?'.http_build_query($params);
    }
}
