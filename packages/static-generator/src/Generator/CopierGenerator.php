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
            if (! file_exists($this->publicDir.'/'.$entry)) {
                continue;
            }
            if (true === $symlink) {
                $this->filesystem->symlink(
                    str_replace($this->params->get('kernel.project_dir').'/', '../', $this->publicDir.'/'.$entry),
                    $this->getStaticDir().'/'.$entry
                );
            } else {
                $action = is_file($this->publicDir.'/'.$entry) ? 'copy' : 'mirror';
                $this->filesystem->$action($this->publicDir.'/'.$entry, $this->getStaticDir().'/'.$entry);
            }
        }
    }
}
