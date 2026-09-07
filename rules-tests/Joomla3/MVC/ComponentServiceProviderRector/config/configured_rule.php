<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla3
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\FileSystem\AddedFileCollectorService;
use Joomla\Rector\Joomla3\MVC\ComponentServiceProviderRector;
use Joomla\Rector\Joomla3\MVC\Config\JoomlaLegacyPrefixToNamespace;
use Joomla\Rector\Joomla3\MVC\FileRenameCollectorService;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->singleton(FileRenameCollectorService::class, static function () {
        return new FileRenameCollectorService();
    });

    // Collect only: the test must not scatter generated files into the fixtures.
    $rectorConfig->singleton(AddedFileCollectorService::class, static function () {
        return new AddedFileCollectorService(false);
    });

    $rectorConfig->ruleWithConfiguration(ComponentServiceProviderRector::class, [
        new JoomlaLegacyPrefixToNamespace('Example', 'Acme\Example', []),
    ]);
};
