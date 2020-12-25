<?php

$container->loadFromExtension('doctrine_migrations', [
'migrations_paths' => [
'DoctrineMigrations' => 'src/Migrations',
],
]);
