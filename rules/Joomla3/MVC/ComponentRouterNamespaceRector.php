<?php

/**
 * Joomla 3 Component Upgrade Rectors
 *
 * @copyright  2026 Open Source Matters, Inc.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Joomla3\MVC;

use Joomla\Rector\Joomla3\MVC\Config\JoomlaLegacyPrefixToNamespace;
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
use Webmozart\Assert\Assert;

/**
 * Namespaces the component router and moves it to its Joomla 4+ location.
 *
 * A Joomla 3 component ships `components/com_example/router.php` holding a class such as
 * `ExampleRouter extends JComponentRouterBase`. Joomla 4 and later expect
 * `site/src/Service/Router.php` with the class `Router` in `<Namespace>\Site\Service`.
 *
 * The rule renames the class to `Router`, wraps the file in that namespace and registers the
 * move with the FileRenameCollectorService, so it ends up in the same rename.php the other MVC
 * rules write — the file is moved by the one script the user runs after the Rector run.
 *
 * The file location is verified against components/com_content/src/Service/Router.php of
 * Joomla 6.1.2, which declares `namespace Joomla\Component\Content\Site\Service;`.
 *
 * @since  1.0.0
 * @see    \Joomla\Rector\Tests\Joomla3\MVC\ComponentRouterNamespaceRector\ComponentRouterNamespaceRectorTest
 */
final class ComponentRouterNamespaceRector extends AbstractRector implements ConfigurableRectorInterface
{
    use JoomlaNamespaceHandlingTrait;

    /**
     * Folder names that hold the site side of a component.
     *
     * @var string[]
     */
    private const SITE_FOLDERS = ['site', 'frontend', 'components'];

    /**
     * The configuration mapping legacy class prefixes to Joomla 4 namespaces.
     *
     * @var JoomlaLegacyPrefixToNamespace[]
     */
    private array $legacyPrefixesToNamespaces = [];

    public function __construct(
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
            'Namespace the Joomla 3 component router and move it to src/Service/Router.php',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
// File: com_example/site/router.php
class ExampleRouter extends JComponentRouterBase
{
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
// File: com_example/site/src/Service/Router.php (moved by rename.php)
namespace Acme\Example\Site\Service;

class Router extends JComponentRouterBase
{
}
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

        $filePath = str_replace('\\', '/', $this->getFile()->getFilePath());

        // Only the legacy router entry file, which sits directly in the component side folder.
        if (preg_match('#/router\.php(?:\.inc)?$#i', $filePath) !== 1) {
            return null;
        }

        $projectRoot = $this->divineProjectRootFolder();

        if ($projectRoot === null) {
            return null;
        }

        $namespacePrefix = $this->resolveNamespacePrefix($projectRoot);

        if ($namespacePrefix === null) {
            return null;
        }

        /** @var FileNode $node */
        $class = $this->findRouterClass($node);

        // A function based Joomla 3 router has no class to namespace — that has to be rewritten
        // by hand, so the rule leaves the file alone.
        if ($class === null) {
            return null;
        }

        $siteRoot = \dirname($filePath);
        $newPath  = $siteRoot . '/src/Service/Router.php';

        $this->fileRenameCollectorService->addRename(
            $projectRoot,
            $this->getFile()->getFilePath(),
            $newPath
        );

        $class->name = new Identifier('Router');

        $node->stmts = [
            new Namespace_(new Name(trim($namespacePrefix, '\\') . '\\Site\\Service'), $node->stmts),
        ];

        return $node;
    }

    // -------------------------------------------------------------------------

    /**
     * Returns the router class of the file, or null when it is namespaced already or the file
     * only holds the legacy routing functions.
     */
    private function findRouterClass(FileNode $fileNode): ?Class_
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
     * Picks the configured namespace belonging to this component, see
     * ComponentServiceProviderRector for the same resolution rules.
     */
    private function resolveNamespacePrefix(string $projectRoot): ?string
    {
        if (\count($this->legacyPrefixesToNamespaces) === 1) {
            return $this->legacyPrefixesToNamespaces[array_key_first($this->legacyPrefixesToNamespaces)]
                ->getNewNamespace();
        }

        $bareName = preg_replace('/^com_/i', '', basename($projectRoot)) ?? basename($projectRoot);
        $matches  = [];

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
