<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Form;

use Composer\InstalledVersions;
use Override;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class EditorjsType extends TextareaType
{
    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $version = InstalledVersions::isInstalled('pushword/admin-block-editor')
            ? InstalledVersions::getPrettyVersion('pushword/admin-block-editor')
            : null;
        $view->vars['editorjs_version'] = $version ?? InstalledVersions::getRootPackage()['pretty_version'];
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'editorjs';
    }
}
