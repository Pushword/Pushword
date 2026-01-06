---
title: 'How to perform bulk editing of content with Pushword'
h1: 'Edit content in batches'
id: 22
publishedAt: '2025-12-21 21:55'
parentPage: search.json
toc: true
---

The best way to edit page content in bulk is to do in directly in PHP.

## Example

1. Create a new [Symfony Command](https://symfony.com/doc/current/console.html#creating-a-command)

2. Do what you want inside

Like stripping the word `example` from all h1

```php

// src/Command/CleanContentCommand.php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'clean:content')]
class CleanContentCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PageRepository $pageRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pages = $this->pageRepo->findAll();
        foreach ($pages as $page) {
            $h1Cleaned = str_replace('example', '', $page->getH1());
            $page->setH1($h1cleaned);
        }

        $this->em->flush();

        return Command::SUCCESS;
    }
}
```

3. Run it `php bin/console clean`