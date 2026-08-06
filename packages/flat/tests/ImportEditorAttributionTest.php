<?php

namespace Pushword\Flat\Tests;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\EntityClassRegistry;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\PageImporter;
use Pushword\Flat\Service\ImportEditorResolver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Who a page imported from a flat file belongs to.
 *
 * The export writes the editor's email into the front matter (User is Stringable), so an
 * import that cannot read it back both loses the attribution and parks the email in
 * customProperties, where every later export re-emits it as a key shadowing the relation.
 *
 * The test environment pins `default_editor` to an address nobody answers to unless a
 * test creates it, so what an import attributes never depends on the users another test
 * class left in the worker's database.
 *
 * @see ImportEditorResolver
 */
#[Group('integration')]
final class ImportEditorAttributionTest extends KernelTestCase
{
    private const string DEFAULT_EDITOR_EMAIL = 'flat-import@example.tld';

    private EntityManager $em;

    /** @var EntityRepository<Page> */
    private EntityRepository $pageRepo;

    /** @var string[] */
    private array $createdFiles = [];

    /** @var int[] */
    private array $createdUserIds = [];

    /** @var string[] */
    private array $importedSlugs = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->pageRepo = $this->em->getRepository(Page::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        $this->em->clear();
        foreach ($this->importedSlugs as $slug) {
            $page = $this->pageRepo->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
            if (null !== $page) {
                $this->em->remove($page);
            }
        }

        $this->em->flush();
        foreach ($this->createdUserIds as $userId) {
            $user = $this->em->getRepository(EntityClassRegistry::getUserClass())->find($userId);
            if (null !== $user) {
                $this->em->remove($user);
            }
        }

        $this->em->flush();
        parent::tearDown();
    }

    public function testAFileNamingItsEditorIsAttributedToThem(): void
    {
        $editor = $this->createUser('flat-editor@example.tld');

        $page = $this->import(
            'attribution-named-editor',
            "editedBy: flat-editor@example.tld\ncreatedBy: flat-editor@example.tld\n",
        );

        self::assertSame($editor, $page->editedBy?->email);
        self::assertSame($editor, $page->createdBy?->email);
        self::assertArrayNotHasKey('editedBy', $page->customProperties);
    }

    public function testAnEditorNoUserAnswersToNeverBecomesACustomProperty(): void
    {
        $page = $this->import('attribution-unknown-editor', "editedBy: ghost@example.tld\n");

        self::assertArrayNotHasKey(
            'editedBy',
            $page->customProperties,
            'Stored as a custom property, every later export re-emits it as a key shadowing the relation',
        );
        self::assertNotSame('ghost@example.tld', $page->editedBy?->email);
    }

    public function testAFileNamingNobodyGoesToTheDefaultEditor(): void
    {
        $this->createUser(self::DEFAULT_EDITOR_EMAIL);

        $page = $this->import('attribution-no-editor', '');

        self::assertSame(self::DEFAULT_EDITOR_EMAIL, $page->createdBy?->email);
        self::assertSame(self::DEFAULT_EDITOR_EMAIL, $page->editedBy?->email);
    }

    public function testAnUnresolvableDefaultEditorLeavesThePageUnattributed(): void
    {
        $page = $this->import('attribution-no-candidate', '');

        self::assertNull($page->createdBy, 'Better unattributed than attributed wrongly');
        self::assertNull($page->editedBy);
    }

    public function testALaterSyncOfTheSameFileDoesNotAttributeIt(): void
    {
        $this->createUser(self::DEFAULT_EDITOR_EMAIL);

        $page = $this->import('attribution-second-sync', '');
        self::assertSame(self::DEFAULT_EDITOR_EMAIL, $page->editedBy?->email, 'attributed at creation');

        $this->em->getConnection()->executeStatement(
            'UPDATE page SET created_by_id = NULL, edited_by_id = NULL WHERE id = ?',
            [(int) $page->id],
        );
        $this->em->clear();

        $reimported = $this->import('attribution-second-sync', "title: 'Changed by someone unknown'\n");

        self::assertNull(
            $reimported->editedBy,
            'Who edited a file outside the admin is unknown — editMessage records where the edit came from',
        );
        self::assertNull($reimported->createdBy);
    }

    public function testTheDefaultEditorFallsBackToTheFirstSuperAdmin(): void
    {
        $superAdmin = $this->createUser('flat-super-admin@example.tld', [User::ROLE_SUPER_ADMIN]);

        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);
        $editor = new ImportEditorResolver($userRepo)->getDefaultEditor();

        self::assertInstanceOf(User::class, $editor);
        self::assertContains(User::ROLE_SUPER_ADMIN, $editor->getRoles());
        self::assertLessThanOrEqual(
            (int) $this->em->getRepository(EntityClassRegistry::getUserClass())->findOneBy(['email' => $superAdmin])?->id,
            (int) $editor->id,
            'The first one, by id — another class may have left an older super admin behind',
        );
    }

    /** @param string[] $roles */
    private function createUser(string $email, array $roles = [User::ROLE_DEFAULT]): string
    {
        $userClass = EntityClassRegistry::getUserClass();
        $user = new $userClass();
        $user->email = $email;
        $user->username = $email;
        $user->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        $this->createdUserIds[] = (int) $user->id;

        return $email;
    }

    /**
     * The single-file importer rather than a whole-directory sync: what is under test is
     * what the front matter makes of the editor, and a sync would also delete every page
     * the worker's database holds without a file next to it.
     */
    private function import(string $slug, string $extraFrontmatter): Page
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $path = $contentDirFinder->get('localhost.dev').'/'.$slug.'.md';

        file_put_contents($path, "---\nh1: Attribution\n".$extraFrontmatter."---\n\nBody.\n");
        $this->createdFiles[] = $path;
        $this->importedSlugs[] = $slug;

        /** @var PageImporter $importer */
        $importer = self::getContainer()->get(PageImporter::class);
        $importer->import($path, new DateTime());
        $importer->finishImport();

        $this->em->clear();

        $page = $this->pageRepo->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);

        return $page;
    }
}
