<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/public'])
    ->append([__FILE__]);

return new Config()
    ->setRiskyAllowed(true)
    // PHP 8.6 RC is newer than the fixer's supported syntax; remove when the fixer supports it.
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        'declare_strict_types' => true,
    ])
    ->setCacheFile(__DIR__ . '/var/cache/php-cs-fixer.cache')
    ->setFinder($finder);
