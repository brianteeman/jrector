<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Open Source Matters, Inc.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla3\MVC;

use Joomla\Rector\Extension\ExtensionTemplateFactory;
use Joomla\Rector\FileSystem\AddedFileCollectorService;
use Joomla\Rector\FileSystem\AddedFileWithContent;
use Joomla\Rector\Joomla3\MVC\Config\JoomlaLegacyPrefixToNamespace;
use PhpParser\Node;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * Creates the two files a namespaced Joomla 4+ component needs and that a Joomla 3 component
 * never had: `services/provider.php` and `src/Extension/<Name>Component.php`.
 *
 * Both are written into the administrator side of the component, which is where Joomla looks
 * for them. Neither file is overwritten if it already exists, so the rule is idempotent and
 * cannot clobber a hand written provider.
 *
 * The templates are derived from com_banners of Joomla 6.1.2
 * (administrator/components/com_banners/services/provider.php and
 * .../src/Extension/BannersComponent.php), reduced to the services every component needs.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla3\MVC\ComponentServiceProviderRector\ComponentServiceProviderRectorTest
 */
final class ComponentServiceProviderRector extends AbstractRector implements ConfigurableRectorInterface
{
    use JoomlaNamespaceHandlingTrait;

    /**
     * Folder names that hold the administrator side of a component.
     *
     * @var string[]
     */
    private const ADMIN_FOLDERS = ['admin', 'administrator', 'backend'];

    /**
     * The configuration mapping legacy class prefixes to Joomla 4 namespaces.
     *
     * @var JoomlaLegacyPrefixToNamespace[]
     */
    private array $legacyPrefixesToNamespaces = [];

    public function __construct(
        private readonly AddedFileCollectorService $addedFileCollectorService,
        private readonly ExtensionTemplateFactory $templateFactory,
        protected readonly FileRenameCollectorService $fileRenameCollectorService,
    ) {
    }

    /**
     * @param   JoomlaLegacyPrefixToNamespace[]  $configuration
     */
    public function configure(array $configuration): void
    {
        Assert::allIsAOf($configuration, JoomlaLegacyPrefixToNamespace::class);

        $this->legacyPrefixesToNamespaces = $configuration;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Create services/provider.php and the Extension class of a namespaced Joomla component',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
// com_example/admin/ has neither services/provider.php nor src/Extension/
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// com_example/admin/services/provider.php          (generated)
// com_example/admin/src/Extension/ExampleComponent.php (generated)
CODE_SAMPLE,
                    [new JoomlaLegacyPrefixToNamespace('Example', 'Acme\Example', [])]
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
        if ($this->legacyPrefixesToNamespaces === []) {
            return null;
        }

        $projectRoot = $this->divineProjectRootFolder();

        if ($projectRoot === null) {
            return null;
        }

        $adminRoot = $this->findAdminRoot($projectRoot);

        if ($adminRoot === null) {
            return null;
        }

        $namespacePrefix = $this->resolveNamespacePrefix($projectRoot);

        if ($namespacePrefix === null) {
            return null;
        }

        $parts         = explode('\\', trim($namespacePrefix, '\\'));
        $componentName = end($parts);

        if (!\is_string($componentName) || $componentName === '') {
            return null;
        }

        $this->addedFileCollectorService->addFile(
            new AddedFileWithContent(
                $adminRoot . '/services/provider.php',
                $this->templateFactory->componentProvider($namespacePrefix, $componentName)
            )
        );

        $this->addedFileCollectorService->addFile(
            new AddedFileWithContent(
                $adminRoot . '/src/Extension/' . $componentName . 'Component.php',
                $this->templateFactory->componentExtensionClass($namespacePrefix, $componentName)
            )
        );

        // Nothing in the parsed file itself changes.
        return null;
    }

    // -------------------------------------------------------------------------

    /**
     * Returns the administrator side folder of the component, or null when there is none.
     */
    private function findAdminRoot(string $projectRoot): ?string
    {
        foreach (self::ADMIN_FOLDERS as $folder) {
            if (is_dir($projectRoot . '/' . $folder)) {
                return $projectRoot . '/' . $folder;
            }
        }

        // A component laid out directly as administrator/components/com_example.
        if (preg_match('#/administrator/components/com_[^/]+$#i', $projectRoot) === 1) {
            return $projectRoot;
        }

        return null;
    }

    /**
     * Picks the configured namespace that belongs to this component.
     *
     * With a single configured mapping that one is used. With several, the component folder
     * name has to match either the legacy prefix or the last namespace segment — otherwise the
     * rule cannot tell which component it is looking at and skips.
     */
    private function resolveNamespacePrefix(string $projectRoot): ?string
    {
        if (\count($this->legacyPrefixesToNamespaces) === 1) {
            return $this->legacyPrefixesToNamespaces[array_key_first($this->legacyPrefixesToNamespaces)]
                ->getNewNamespace();
        }

        $folderName = basename($projectRoot);
        $bareName   = preg_replace('/^com_/i', '', $folderName) ?? $folderName;

        $matches = [];

        foreach ($this->legacyPrefixesToNamespaces as $map) {
            $namespaceParts = explode('\\', trim($map->getNewNamespace(), '\\'));
            $lastSegment    = end($namespaceParts);

            if (
                strcasecmp($map->getNamespacePrefix(), $bareName) === 0
                || (\is_string($lastSegment) && strcasecmp($lastSegment, $bareName) === 0)
            ) {
                $matches[$map->getNewNamespace()] = $map->getNewNamespace();
            }
        }

        return \count($matches) === 1 ? reset($matches) : null;
    }
}
