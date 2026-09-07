<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6\Module;

use Joomla\Rector\Extension\ExtensionTemplateFactory;
use Joomla\Rector\FileSystem\AddedFileCollectorService;
use Joomla\Rector\FileSystem\AddedFileWithContent;
use Joomla\Rector\Joomla3\MVC\FileRenameCollectorService;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Lifts a legacy module onto the namespaced structure with a service provider.
 *
 * A legacy module is `mod_<name>/mod_<name>.php` (plus an optional `helper.php`) without a
 * `services/provider.php`. The rule
 *
 *   - namespaces the helper class as `<Vendor>\Module\<Name>\<Client>\Helper\<Name>Helper`,
 *   - registers the move of `helper.php` to `src/Helper/<Name>Helper.php` with the
 *     FileRenameCollectorService, which writes the rename.php the user runs once, and
 *   - creates `services/provider.php` through the AddedFileCollectorService.
 *
 * The provider template is derived from modules/mod_articles_news/services/provider.php of
 * Joomla 6.1.2.
 *
 * The dispatcher is not generated: its content comes from `mod_<name>.php` and belongs to the
 * getLayoutData() split, which DispatcherGetLayoutDataRector handles once the dispatcher exists.
 *
 * The vendor namespace is mandatory configuration and is never guessed — the `Joomla\` namespace
 * belongs to the core.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Module\LegacyModuleToJ6Rector\LegacyModuleToJ6RectorTest
 */
final class LegacyModuleToJ6Rector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * Configuration key for the vendor part of the namespace, e.g. `Acme`.
     */
    public const VENDOR_NAMESPACE = 'vendor_namespace';

    private string $vendorNamespace = '';

    public function __construct(
        private readonly FileRenameCollectorService $fileRenameCollectorService,
        private readonly AddedFileCollectorService $addedFileCollectorService,
        private readonly ExtensionTemplateFactory $templateFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        $vendorNamespace = $configuration[self::VENDOR_NAMESPACE] ?? '';

        if (\is_string($vendorNamespace)) {
            $this->vendorNamespace = trim($vendorNamespace, '\\');
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert a legacy module to the namespaced structure with a service provider',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
// File: modules/mod_latest_news/helper.php
class ModLatestNewsHelper
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// File: modules/mod_latest_news/src/Helper/LatestNewsHelper.php (moved by rename.php)
namespace Acme\Module\LatestNews\Site\Helper;

class LatestNewsHelper
{
}
// plus a generated modules/mod_latest_news/services/provider.php
CODE_SAMPLE,
                    [self::VENDOR_NAMESPACE => 'Acme']
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($this->vendorNamespace === '') {
            return null;
        }

        $filePath = str_replace('\\', '/', $this->getFile()->getFilePath());

        $module = $this->matchModuleFile($filePath);

        if ($module === null) {
            return null;
        }

        [$moduleRoot, $moduleName, $fileName] = $module;

        // A legacy module still has its entry file; an already converted one has a provider.
        if (!is_file($moduleRoot . '/' . $moduleName . '.php') || is_file($moduleRoot . '/services/provider.php')) {
            return null;
        }

        $name            = $this->toStudlyCase(substr($moduleName, 4));
        $client          = $this->detectClient($moduleRoot, $moduleName);
        $clientSegment   = $client === 'administrator' ? 'Administrator' : 'Site';
        $moduleNamespace = \sprintf('%s\\Module\\%s\\%s', $this->vendorNamespace, $name, $clientSegment);

        // The provider is registered from whichever module file Rector reaches first.
        $this->addedFileCollectorService->addFile(
            new AddedFileWithContent(
                $moduleRoot . '/services/provider.php',
                $this->templateFactory->moduleProvider(
                    \sprintf('%s\\Module\\%s', $this->vendorNamespace, $name),
                    $moduleName,
                    $client
                )
            )
        );

        // Only helper.php is moved and namespaced here.
        if (strcasecmp($fileName, 'helper') !== 0) {
            return null;
        }

        /** @var FileNode $node */
        $class = $this->findTopLevelClass($node);

        if ($class === null) {
            return null;
        }

        $this->fileRenameCollectorService->addRename(
            $moduleRoot,
            $this->getFile()->getFilePath(),
            $moduleRoot . '/src/Helper/' . $name . 'Helper.php'
        );

        $class->name     = new Identifier($name . 'Helper');
        $node->stmts     = [new Namespace_(new Name($moduleNamespace . '\\Helper'), $node->stmts)];

        return $node;
    }

    // -------------------------------------------------------------------------

    /**
     * Matches any PHP file directly inside a `mod_<name>` folder.
     *
     * @return array{0: string, 1: string, 2: string}|null Module root, module name and file name.
     */
    private function matchModuleFile(string $filePath): ?array
    {
        if (preg_match('#^(.*/(mod_[^/]+))/([^/]+)\.php(?:\.inc)?$#i', $filePath, $matches) !== 1) {
            return null;
        }

        return [$matches[1], strtolower($matches[2]), $matches[3]];
    }

    /**
     * Reads the client from the manifest, defaulting to the site.
     */
    private function detectClient(string $moduleRoot, string $moduleName): string
    {
        $manifest = $moduleRoot . '/' . $moduleName . '.xml';

        if (!is_file($manifest)) {
            return 'site';
        }

        $source = (string) file_get_contents($manifest);

        if (preg_match('/<extension[^>]*\bclient\s*=\s*"([^"]+)"/i', $source, $matches) === 1) {
            return strtolower($matches[1]) === 'administrator' ? 'administrator' : 'site';
        }

        return 'site';
    }

    private function findTopLevelClass(FileNode $fileNode): ?Class_
    {
        foreach ($fileNode->stmts as $stmt) {
            if ($stmt instanceof Namespace_) {
                return null;
            }

            if ($stmt instanceof Class_ && $stmt->name instanceof Identifier) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * `latest_news` becomes `LatestNews`.
     */
    private function toStudlyCase(string $value): string
    {
        $parts = preg_split('/[_\-]+/', $value) ?: [];

        return implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));
    }
}
