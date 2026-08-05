<?php

namespace Pushword\Core\Tests\BackgroundTask;

use PHPUnit\Framework\TestCase;
use Pushword\Core\BackgroundTask\BackgroundCommand;

final class BackgroundCommandTest extends TestCase
{
    public function testAConsoleCommandIsPinnedToTheGivenEnvironment(): void
    {
        self::assertSame(
            ['php', 'bin/console', 'pw:image:cache', 'photo.jpg', '--env=prod'],
            BackgroundCommand::pinEnvironment(['php', 'bin/console', 'pw:image:cache', 'photo.jpg'], 'prod'),
        );
    }

    public function testAnythingElseIsLeftAlone(): void
    {
        // The dispatchers are not restricted to console commands, and `--env` means
        // nothing to a plain binary — appending it would corrupt the invocation.
        self::assertSame(['echo', 'hello'], BackgroundCommand::pinEnvironment(['echo', 'hello'], 'test'));
    }
}
