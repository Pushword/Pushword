<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\PHPStan;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;

class FrameworkUsageProviderFixture
{
    #[AdminRoute('/routed')]
    public function routed(): void
    {
    }

    #[AdminRoute('/hidden')]
    protected function hidden(): void
    {
    }

    public function kernelExplicit(): void
    {
    }

    public function onSampleReady(): void
    {
    }

    public function postPersist(): void
    {
    }

    public function entityWritten(): void
    {
    }

    public function __invoke(): void
    {
    }

    public function unrelated(): void
    {
    }
}
