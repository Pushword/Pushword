<?php

return [
    '@PER-CS2.0:risky' => true,
    '@PSR2' => true,
    '@DoctrineAnnotation' => true,
    '@Symfony' => true,
    '@Symfony:risky' => true,
    'array_syntax' => ['syntax' => 'short'],
    'array_indentation' => true,
    'trim_array_spaces' => true,
    'ordered_imports' => ['sort_algorithm' => 'alpha'],
    'no_unused_imports' => true,
    'not_operator_with_successor_space' => true,
    'trailing_comma_in_multiline' => true,
    'phpdoc_scalar' => true,
    'unary_operator_spaces' => true,
    'binary_operator_spaces' => true,
    'blank_line_before_statement' => [
        'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
    ],
    'phpdoc_single_line_var_spacing' => true,
    'phpdoc_var_without_name' => true,
    'class_attributes_separation' => [
        'elements' => ['const' => 'one', 'method' => 'one', 'property' => 'one'],
    ],
    'ordered_traits' => false,
    'method_argument_space' => [
        'on_multiline' => 'ensure_fully_multiline',
    ],
    'single_trait_insert_per_statement' => true,
    // 'modernize_types_casting' => false, // PHPStan...*
    'phpdoc_to_comment' => false, // see here to add use to structural element https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/402b34d4ab33146eaab0f17d60c928eaa7e332b9/src/Tokenizer/Analyzer/CommentsAnalyzer.php#L155
    'native_function_invocation' => false,
    'global_namespace_import' => false,
];
