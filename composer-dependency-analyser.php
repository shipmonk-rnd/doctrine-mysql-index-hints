<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

$ciTools = [
    'editorconfig-checker/editorconfig-checker',
    'ergebnis/composer-normalize',
    'phpstan/phpstan',
    'phpstan/phpstan-phpunit',
    'phpstan/phpstan-strict-rules',
    'shipmonk/composer-dependency-analyser',
    'shipmonk/dead-code-detector',
    'shipmonk/phpstan-rules',
    'slevomat/coding-standard',
];

return $config
    ->enableAnalysisOfUnusedDevDependencies()
    ->ignoreErrorsOnPackages($ciTools, [ErrorType::UNUSED_DEPENDENCY]);
