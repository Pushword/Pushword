<?php

namespace Pushword\Admin\Service;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use InvalidArgumentException;
use Pushword\Admin\Controller\MediaCrudController;
use Pushword\Admin\Controller\PageCheatSheetCrudController;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\Controller\UserCrudController;

/**
 * Service to generate EasyAdmin URLs compatible with old Sonata route names.
 * This allows gradual migration without breaking existing code.
 */
class AdminUrlGeneratorAlias
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    /**
     * Generate URL for a named route (Sonata-style).
     *
     * @param array<string, mixed> $parameters
     */
    public function generate(string $routeName, array $parameters = []): string
    {
        // Map old Sonata routes to EasyAdmin
        return match ($routeName) {
            'admin_page_list' => $this->generatePageList($parameters),
            'admin_page_edit' => $this->generatePageEdit($parameters),
            'admin_page_create', 'admin_page_new' => $this->generatePageCreate(),
            'admin_page_show' => $this->generatePageShow($parameters),
            'admin_page_delete' => $this->generatePageDelete($parameters),
            'admin_cheatsheet_edit' => $this->generateCheatSheetEdit($parameters),

            'admin_media_list' => $this->generateMediaList($parameters),
            'admin_media_edit' => $this->generateMediaEdit($parameters),
            'admin_media_create', 'admin_media_new' => $this->generateMediaCreate(),

            'admin_user_list' => $this->generateUserList($parameters),
            'admin_user_edit' => $this->generateUserEdit($parameters),
            'admin_user_create', 'admin_user_new' => $this->generateUserCreate(),

            default => throw new InvalidArgumentException(sprintf('Unknown route "%s". Please migrate to EasyAdmin routing.', $routeName))
        };
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generatePageList(array $parameters = []): string
    {
        $url = $this->adminUrlGenerator
            ->setController(PageCrudController::class)
            ->setAction(Action::INDEX);

        // Handle filters if provided
        if (isset($parameters['filter']) && \is_array($parameters['filter'])) {
            foreach ($parameters['filter'] as $field => $value) {
                if (! \is_string($field)) {
                    continue;
                }

                $url->set(sprintf('filters[%s]', $field), $value);
            }
        }

        return $url->generateUrl();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generatePageEdit(array $parameters): string
    {
        if (! isset($parameters['id'])) {
            throw new InvalidArgumentException('Missing required parameter "id" for admin_page_edit');
        }

        return $this->adminUrlGenerator
            ->setController(PageCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($parameters['id'])
            ->generateUrl();
    }

    private function generatePageCreate(): string
    {
        return $this->adminUrlGenerator
            ->setController(PageCrudController::class)
            ->setAction(Action::NEW)
            ->generateUrl();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generatePageShow(array $parameters): string
    {
        if (! isset($parameters['id'])) {
            throw new InvalidArgumentException('Missing required parameter "id" for admin_page_show');
        }

        return $this->adminUrlGenerator
            ->setController(PageCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($parameters['id'])
            ->generateUrl();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generatePageDelete(array $parameters): string
    {
        if (! isset($parameters['id'])) {
            throw new InvalidArgumentException('Missing required parameter "id" for admin_page_delete');
        }

        return $this->adminUrlGenerator
            ->setController(PageCrudController::class)
            ->setAction(Action::DELETE)
            ->setEntityId($parameters['id'])
            ->generateUrl();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generateCheatSheetEdit(array $parameters): string
    {
        if (! isset($parameters['id'])) {
            throw new InvalidArgumentException('Missing required parameter "id" for admin_cheatsheet_edit');
        }

        return $this->adminUrlGenerator
            ->setController(PageCheatSheetCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($parameters['id'])
            ->generateUrl();
    }

    // Media methods
    /**
     * @param array<string, mixed> $parameters
     */
    private function generateMediaList(array $parameters = []): string
    {
        return $this->adminUrlGenerator
            ->setController(MediaCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generateMediaEdit(array $parameters): string
    {
        if (! isset($parameters['id'])) {
            throw new InvalidArgumentException('Missing required parameter "id" for admin_media_edit');
        }

        return $this->adminUrlGenerator
            ->setController(MediaCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($parameters['id'])
            ->generateUrl();
    }

    private function generateMediaCreate(): string
    {
        return $this->adminUrlGenerator
            ->setController(MediaCrudController::class)
            ->setAction(Action::NEW)
            ->generateUrl();
    }

    // User methods
    /**
     * @param array<string, mixed> $parameters
     */
    private function generateUserList(array $parameters = []): string
    {
        return $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generateUserEdit(array $parameters): string
    {
        if (! isset($parameters['id'])) {
            throw new InvalidArgumentException('Missing required parameter "id" for admin_user_edit');
        }

        return $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($parameters['id'])
            ->generateUrl();
    }

    private function generateUserCreate(): string
    {
        return $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction(Action::NEW)
            ->generateUrl();
    }
}
