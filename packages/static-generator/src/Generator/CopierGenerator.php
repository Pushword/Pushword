<?php

namespace Pushword\StaticGenerator\Generator;

class CopierGenerator extends AbstractGenerator
{
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $symlink = $this->mustSymlink();
        $entries = $this->app->get('static_copy');

        foreach ($entries as $entry) {
            if (true === $symlink) {
                $this->filesystem->symlink(
                    str_replace($this->params->get('kernel.project_dir').'/', '../', $this->webDir.'/'.$entry),
                    $this->getStaticDir().'/'.$entry
                );
            } else {
                $action = is_file($this->webDir.'/'.$entry) ? 'copy' : 'mirror';
                $this->filesystem->$action($this->webDir.'/'.$entry, $this->getStaticDir().'/'.$entry);
            }
        }
    }
}
