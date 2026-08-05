<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Tests log in through the real HTTP form, so every admin test class pays a
    // password verification, and every created fixture user pays a hash. At `auto`'s
    // production strength one verify costs ~330ms, which sampling put at 17% of the
    // whole suite's CPU. Asking each algorithm for the lowest cost it accepts keeps
    // the code path identical and removes only the stretching, which is not what any
    // of these tests exercise.
    //
    // The key must stay byte-identical to core's security.php entry, or this adds a
    // second hasher instead of overriding the first.
    $container->extension('security', [
        'password_hashers' => [
            '%pw.entity_user%' => [
                'algorithm' => 'auto',
                'cost' => 4,          // bcrypt minimum
                'time_cost' => 3,     // argon2 minimum
                'memory_cost' => 10,  // argon2 minimum
            ],
        ],
    ]);
};
