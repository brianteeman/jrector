<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla6
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla6\Plugin;

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
 * Lifts a legacy single file plugin onto the DI based structure.
 *
 * A legacy plugin is `plugins/<group>/<element>/<element>.php` holding a `Plg<Group><Element>`
 * class, with no `services/provider.php`. The rule
 *
 *   - namespaces the plugin class as `<Vendor>\Plugin\<Group>\<Name>\Extension\<Name>`,
 *   - registers the move of the class file to `src/Extension/<Name>.php` with the
 *     FileRenameCollectorService, which writes the rename.php the user runs once, and
 *   - creates `services/provider.php` through the AddedFileCollectorService.
 *
 * The provider template is derived from plugins/content/joomla/services/provider.php of
 * Joomla 6.1.2.
 *
 * The vendor namespace is mandatory configuration. Guessing it is not an option: the rule would
 * otherwise occupy the `Joomla\` namespace of the core with third party code.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla6\Plugin\PluginServiceProviderRector\PluginServiceProviderRectorTest
 */
final class PluginServiceProviderRector extends AbstractRector implements ConfigurableRectorInterface
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
            'Convert a legacy single file plugin to the namespaced structure with a service provider',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
// File: plugins/content/example/example.php
class PlgContentExample extends CMSPlugin
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// File: plugins/content/example/src/Extension/Example.php (moved by rename.php)
namespace Acme\Plugin\Content\Example\Extension;

class Example extends CMSPlugin
{
}
// plus a generated plugins/content/example/services/provider.php
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
        // Without a vendor namespace the rule cannot produce a valid namespace, so it stays off.
        if ($this->vendorNamespace === '') {
            return null;
        }

        $filePath = str_replace('\\', '/', $this->getFile()->getFilePath());

        $plugin = $this->matchLegacyPluginFile($filePath);

        if ($plugin === null) {
            return null;
        }

        [$pluginRoot, $group, $element] = $plugin;

        // Idempotence: an already converted plugin has a service provider.
        if (is_file($pluginRoot . '/services/provider.php')) {
            return null;
        }

        /** @var FileNode $node */
        $class = $this->findPluginClass($node);

        if ($class === null) {
            return null;
        }

        $name            = $this->toStudlyCase($element);
        $pluginNamespace = \sprintf(
            '%s\\Plugin\\%s\\%s',
            $this->vendorNamespace,
            $this->toStudlyCase($group),
            $name
        );

        // 1. Move the class file under src/Extension/.
        $this->fileRenameCollectorService->addRename(
            $pluginRoot,
            $this->getFile()->getFilePath(),
            $pluginRoot . '/src/Extension/' . $name . '.php'
        );

        // 2. Create the service provider.
        $this->addedFileCollectorService->addFile(
            new AddedFileWithContent(
                $pluginRoot . '/services/provider.php',
                $this->templateFactory->pluginProvider($pluginNamespace, $group, $element, $name)
            )
        );

        // 3. Namespace the class and shorten its name, so the moved file is autoloadable.
        return $this->namespaceClass($node, $class, $pluginNamespace . '\\Extension', $name);
    }

    // -------------------------------------------------------------------------

    /**
     * Matches `plugins/<group>/<element>/<element>.php`.
     *
     * @return array{0: string, 1: string, 2: string}|null Plugin root, group and element.
     */
    private function matchLegacyPluginFile(string $filePath): ?array
    {
        if (preg_match('#^(.*/plugins/([^/]+)/([^/]+))/([^/]+)\.php(?:\.inc)?$#i', $filePath, $matches) !== 1) {
            return null;
        }

        [, $pluginRoot, $group, $element, $fileName] = $matches;

        // Only the plugin entry file itself, not some other PHP file in the folder.
        if (strcasecmp($fileName, $element) !== 0) {
            return null;
        }

        return [$pluginRoot, strtolower($group), strtolower($element)];
    }

    /**
     * Returns the plugin class of the file, i.e. a top level class that is not namespaced yet.
     */
    private function findPluginClass(FileNode $fileNode): ?Class_
    {
        foreach ($fileNode->stmts as $stmt) {
            // Already namespaced — nothing to convert.
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
     * Wraps the file statements in a namespace and renames the class.
     */
    private function namespaceClass(FileNode $fileNode, Class_ $class, string $namespace, string $className): FileNode
    {
        $class->name = new Identifier($className);

        $fileNode->stmts = [new Namespace_(new Name($namespace), $fileNode->stmts)];

        return $fileNode;
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
