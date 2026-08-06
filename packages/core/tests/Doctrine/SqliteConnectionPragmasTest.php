<?php

namespace Pushword\Core\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * What {@see \Pushword\Core\Doctrine\SqliteConnectionPragmas} buys, and the one
 * command it has to stand down for.
 */
#[Group('integration')]
final class SqliteConnectionPragmasTest extends KernelTestCase
{
    /**
     * `ON DELETE CASCADE` is declared on the schema, not implemented by the ORM: a
     * ManyToOne carrying it is left to the database. Without the pragma these rows
     * outlived their parent, and SQLite reuses freed rowids, so one of them could
     * come back attached to someone else.
     */
    public function testDeletingAUserCascadesToItsLoginTokens(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = 'cascade-'.uniqid().'@example.tld';
        $user->setPassword('irrelevant');

        $entityManager->persist($user);

        $loginToken = new LoginToken($user)->setToken('plain-token');
        $entityManager->persist($loginToken);
        $entityManager->flush();

        $tokenId = $loginToken->id;
        self::assertNotNull($tokenId);

        $entityManager->remove($user);
        $entityManager->flush();

        $connection = self::getContainer()->get(Connection::class);
        self::assertFalse(
            $connection->fetchOne('SELECT 1 FROM login_token WHERE id = ?', [$tokenId]),
            'the token went away with its user',
        );
    }

    /**
     * A background console task writes to the same file as the request that spawned
     * it. With no timeout the second one does not queue behind the first, it fails
     * with `database is locked`.
     */
    public function testAWriterWaitsForALockInsteadOfFailingOnTheSpot(): void
    {
        self::bootKernel();

        $connection = $this->sqliteConnection();

        self::assertSame(5000, $connection->fetchOne('PRAGMA busy_timeout'));
    }

    /**
     * SQLite rebuilds a table by dropping it, which under enforced foreign keys
     * takes every cascading child row with it — so `doctrine:schema:update`, the
     * upgrade path here, must run with the pragma back off.
     */
    public function testASchemaCommandRunsWithForeignKeysOff(): void
    {
        self::bootKernel();

        $connection = $this->sqliteConnection();

        try {
            self::assertSame(1, $connection->fetchOne('PRAGMA foreign_keys'));

            self::getContainer()->get('event_dispatcher')->dispatch(
                new ConsoleCommandEvent(new Command('doctrine:schema:update'), new ArrayInput([]), new NullOutput()),
                ConsoleEvents::COMMAND,
            );

            self::assertSame(0, $connection->fetchOne('PRAGMA foreign_keys'));
        } finally {
            $connection->executeStatement('PRAGMA foreign_keys=ON');
        }
    }

    /** Every other command keeps the enforcement — a looser name match would drop it for all of them. */
    public function testAnyOtherCommandKeepsForeignKeysOn(): void
    {
        self::bootKernel();

        $connection = $this->sqliteConnection();

        self::getContainer()->get('event_dispatcher')->dispatch(
            new ConsoleCommandEvent(new Command('pw:flat:sync'), new ArrayInput([]), new NullOutput()),
            ConsoleEvents::COMMAND,
        );

        self::assertSame(1, $connection->fetchOne('PRAGMA foreign_keys'));
    }

    private function sqliteConnection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        if (! $connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('Pragmas only exist on SQLite.');
        }

        return $connection;
    }
}
