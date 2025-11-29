<?php

use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/vendor/autoload.php';

$packages = [
    'admin',
    'admin-block-editor',
    'advanced-main-image',
    'conversation',
    'core',
    'page-scanner',
    'template-editor',
    'version',
];

$globalKeys = [];
$missingInFr = [];
$missingInEn = [];
$usedButMissing = [];

// Load all translations
$translations = [];
foreach ($packages as $package) {
    $basePath = __DIR__ . sprintf('/packages/%s/src/translations', $package);
    foreach (['en', 'fr'] as $locale) {
        $file = sprintf('%s/messages.%s.yaml', $basePath, $locale);
        if (file_exists($file)) {
            $data = Yaml::parseFile($file);
            $keys = array_keys($data ?? []);
            $translations[$package][$locale] = $keys;
            // Assuming flat keys now
            foreach ($keys as $key) {
                $globalKeys[$key] = true;
            }
        }
    }
}

// Check parity
foreach ($packages as $package) {
    if (!isset($translations[$package])) {
        continue;
    }

    $enKeys = $translations[$package]['en'] ?? [];
    $frKeys = $translations[$package]['fr'] ?? [];

    $diffFr = array_diff($enKeys, $frKeys);
    if ($diffFr !== []) {
        $missingInFr[$package] = $diffFr;
    }

    $diffEn = array_diff($frKeys, $enKeys);
    if ($diffEn !== []) {
        $missingInEn[$package] = $diffEn;
    }
}

// Scan for usage
$usageRegexes = [
    '/[\'"]([\w.]+)[\'"]\s*\|\s*trans/', // Twig: 'key'|trans
    '/->trans\(\s*[\'"]([\w.]+)[\'"]/', // PHP: ->trans('key')
    '/t\(\s*[\'"]([\w.]+)[\'"]/', // PHP: t('key')
];

foreach ($packages as $package) {
    $dir = new RecursiveDirectoryIterator(__DIR__ . ('/packages/' . $package));
    $iterator = new RecursiveIteratorIterator($dir);

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            continue;
        }

        $ext = $file->getExtension();
        if (!in_array($ext, ['php', 'twig'])) {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        foreach ($usageRegexes as $regex) {
            preg_match_all($regex, $content, $matches);
            if (isset($matches[1]) && $matches[1] !== []) {
                foreach ($matches[1] as $key) {
                    // Ignore dynamic keys or variables (simple check)
                    if (str_contains($key, '$')) {
                        continue;
                    }

                    // Ignore keys with spaces if we assume camelCase, but let's keep them to see if there are old keys left
                    // if (strpos($key, ' ') !== false) continue;

                    if (!isset($globalKeys[$key])) {
                        $usedButMissing[$package][] = $key . ' (in ' . $file->getFilename() . ")";
                    }
                }
            }
        }
    }
}

echo "Missing in FR:\n";
print_r($missingInFr);

echo "\nMissing in EN:\n";
print_r($missingInEn);

echo "\nUsed but missing in translations (Global check):\n";
print_r($usedButMissing);
