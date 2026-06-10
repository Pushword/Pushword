<?php

namespace Pushword\Admin\Tests\Worker;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\Twig\AdminExtension;
use Pushword\Core\Entity\User;
use ReflectionObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Worker-mode safety guard for the admin. Under PHP-FPM every request is a fresh
 * process; under a long-running worker the kernel — and every shared service —
 * is reused across requests. Admin services that capture per-request state at
 * construction or cache it in memory would then leak it into later requests
 * handled by the same worker.
 */
#[Group('integration')]
#[Group('worker')]
final class AdminWorkerStateResetTest extends KernelTestCase
{
    public function testFormFieldManagerResolvesUserLazilyPerRequest(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $manager = $container->get(AdminFormFieldManager::class);
        // It must be a shared service for this to matter (and for the leak to exist).
        self::assertSame($manager, $container->get(AdminFormFieldManager::class));

        $tokenStorage = $container->get('security.token_storage');

        // Request A: a super-admin is authenticated.
        $superAdmin = new User();
        $superAdmin->email = 'super@example.tld';
        $superAdmin->setRoles(['ROLE_SUPER_ADMIN']);

        $tokenStorage->setToken(new UsernamePasswordToken($superAdmin, 'main', $superAdmin->getRoles()));
        self::assertSame($superAdmin, $manager->getUser());

        // Request B in the same worker: a different, lower-privilege user. The
        // shared manager must reflect the new identity — capturing the user at
        // construction would still return the super-admin here (privilege leak
        // for UserRolesField's ROLE_SUPER_ADMIN gate).
        $editor = new User();
        $editor->email = 'editor@example.tld';
        $editor->setRoles(['ROLE_EDITOR']);

        $tokenStorage->setToken(new UsernamePasswordToken($editor, 'main', $editor->getRoles()));
        self::assertSame($editor, $manager->getUser());

        // Anonymous request: no token → no user.
        $tokenStorage->setToken(null);
        self::assertNull($manager->getUser());
    }

    public function testAdminExtensionTagCacheResetsAtWorkerBoundary(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $extension = $container->get(AdminExtension::class);

        // Populate the per-host tag cache.
        $extension->getAllTagsJson();

        $cacheProperty = new ReflectionObject($extension)->getProperty('cache');
        self::assertNotEmpty($cacheProperty->getValue($extension), 'the tag cache should be warm after a call');

        // The worker boundary: reset everything tagged kernel.reset. AdminExtension
        // implements ResetInterface and is autoconfigured, so it must be flushed —
        // otherwise a tag added in a later request would never appear in the admin.
        $container->get('services_resetter')->reset();

        self::assertSame([], $cacheProperty->getValue($extension), 'the tag cache must be cleared at the worker boundary');
    }
}
