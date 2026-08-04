<?php

namespace Pushword\Newsletter\Bounce;

/**
 * One address a mail server refused for good, as a delivery report names it.
 *
 * The diagnostic is carried along without being interpreted: it is what an
 * admin reads when asking why an address left the list, and the only part of a
 * bounce written by the remote server in its own words.
 */
final readonly class FailedRecipient
{
    public function __construct(
        public string $email,
        public string $status,
        public string $diagnostic = '',
    ) {
    }
}
