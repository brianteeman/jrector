<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla3
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\Rector\Joomla3\MVC\ComponentRouterNamespaceRector;
use Joomla\Rector\Joomla3\MVC\Config\JoomlaLegacyPrefixToNamespace;
use Joomla\Rector\Joomla3\MVC\FileRenameCollectorService;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->singleton(FileRenameCollectorService::class, static function () {
        return new FileRenameCollectorService();
    });

    $rectorConfig->ruleWithConfiguration(ComponentRouterNamespaceRector::class, [
        new JoomlaLegacyPrefixToNamespace('Example', 'Acme\Example', []),
    ]);
};
