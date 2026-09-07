<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Extension
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Extension;

/**
 * Renders the boilerplate files a namespaced Joomla extension needs.
 *
 * Every template here is derived from a Joomla 6.1.2 core extension, not written from memory:
 *
 *   - component provider  : administrator/components/com_banners/services/provider.php
 *   - component extension : administrator/components/com_banners/src/Extension/BannersComponent.php
 *   - module provider     : modules/mod_articles_news/services/provider.php
 *   - plugin provider     : plugins/content/joomla/services/provider.php
 *
 * The core files were reduced to the parts every extension needs; component specific extras
 * such as the category, tag and HTML registry services are left out on purpose, because a
 * converted third party component rarely provides them and an unused service registration
 * fails at runtime.
 *
 * @since  1.0.0
 */
final class ExtensionTemplateFactory
{
    /**
     * Renders services/provider.php for a component.
     *
     * @param   string  $namespacePrefix  Base namespace of the component, e.g. Acme\Example.
     * @param   string  $componentName    Component name without com_, in UpperCamelCase.
     *
     * @return  string
     * @since   1.0.0
     */
    public function componentProvider(string $namespacePrefix, string $componentName): string
    {
        $namespacePrefix = trim($namespacePrefix, '\\');
        $escapedPrefix   = str_replace('\\', '\\\\', $namespacePrefix);
        $extensionClass  = $componentName . 'Component';

        return <<<PHP
            <?php

            /**
             * @package     {$componentName}
             * @subpackage  com_{$this->toLowerName($componentName)}
             *
             * @license     GNU General Public License version 2 or later; see LICENSE.txt
             */

            \\defined('_JEXEC') or die;

            use Joomla\\CMS\\Dispatcher\\ComponentDispatcherFactoryInterface;
            use Joomla\\CMS\\Extension\\ComponentInterface;
            use Joomla\\CMS\\Extension\\Service\\Provider\\ComponentDispatcherFactory;
            use Joomla\\CMS\\Extension\\Service\\Provider\\MVCFactory;
            use Joomla\\CMS\\Extension\\Service\\Provider\\RouterFactory;
            use Joomla\\CMS\\MVC\\Factory\\MVCFactoryInterface;
            use Joomla\\DI\\Container;
            use Joomla\\DI\\ServiceProviderInterface;
            use {$namespacePrefix}\\Administrator\\Extension\\{$extensionClass};

            return new class () implements ServiceProviderInterface {
                /**
                 * Registers the service provider with a DI container.
                 *
                 * @param   Container  \$container  The DI container.
                 *
                 * @return  void
                 */
                public function register(Container \$container)
                {
                    \$container->registerServiceProvider(new MVCFactory('\\\\{$escapedPrefix}'));
                    \$container->registerServiceProvider(new ComponentDispatcherFactory('\\\\{$escapedPrefix}'));
                    \$container->registerServiceProvider(new RouterFactory('\\\\{$escapedPrefix}'));

                    \$container->set(
                        ComponentInterface::class,
                        function (Container \$container) {
                            \$component = new {$extensionClass}(\$container->get(ComponentDispatcherFactoryInterface::class));

                            \$component->setMVCFactory(\$container->get(MVCFactoryInterface::class));

                            return \$component;
                        }
                    );
                }
            };

            PHP;
    }

    /**
     * Renders src/Extension/<Name>Component.php for a component.
     *
     * @param   string  $namespacePrefix  Base namespace of the component, e.g. Acme\Example.
     * @param   string  $componentName    Component name without com_, in UpperCamelCase.
     *
     * @return  string
     * @since   1.0.0
     */
    public function componentExtensionClass(string $namespacePrefix, string $componentName): string
    {
        $namespacePrefix = trim($namespacePrefix, '\\');
        $extensionClass  = $componentName . 'Component';

        return <<<PHP
            <?php

            /**
             * @package     {$componentName}
             * @subpackage  com_{$this->toLowerName($componentName)}
             *
             * @license     GNU General Public License version 2 or later; see LICENSE.txt
             */

            namespace {$namespacePrefix}\\Administrator\\Extension;

            use Joomla\\CMS\\Component\\Router\\RouterServiceInterface;
            use Joomla\\CMS\\Component\\Router\\RouterServiceTrait;
            use Joomla\\CMS\\Extension\\MVCComponent;

            // phpcs:disable PSR1.Files.SideEffects
            \\defined('_JEXEC') or die;
            // phpcs:enable PSR1.Files.SideEffects

            /**
             * Component class for com_{$this->toLowerName($componentName)}
             */
            class {$extensionClass} extends MVCComponent implements RouterServiceInterface
            {
                use RouterServiceTrait;
            }

            PHP;
    }

    /**
     * Renders services/provider.php for a module.
     *
     * @param   string  $namespacePrefix  Base namespace of the module, e.g. Acme\Module\LatestNews.
     * @param   string  $moduleName       Module folder name, e.g. mod_latest_news.
     * @param   string  $client           Either site or administrator.
     *
     * @return  string
     * @since   1.0.0
     */
    public function moduleProvider(string $namespacePrefix, string $moduleName, string $client): string
    {
        $namespacePrefix = trim($namespacePrefix, '\\');
        $escapedPrefix   = str_replace('\\', '\\\\', $namespacePrefix);
        $clientSegment   = $client === 'administrator' ? 'Administrator' : 'Site';
        $package         = $client === 'administrator' ? 'Administrator' : 'Site';

        return <<<PHP
            <?php

            /**
             * @package     {$package}
             * @subpackage  {$moduleName}
             *
             * @license     GNU General Public License version 2 or later; see LICENSE.txt
             */

            \\defined('_JEXEC') or die;

            use Joomla\\CMS\\Extension\\Service\\Provider\\HelperFactory;
            use Joomla\\CMS\\Extension\\Service\\Provider\\Module;
            use Joomla\\CMS\\Extension\\Service\\Provider\\ModuleDispatcherFactory;
            use Joomla\\DI\\Container;
            use Joomla\\DI\\ServiceProviderInterface;

            return new class () implements ServiceProviderInterface {
                /**
                 * Registers the service provider with a DI container.
                 *
                 * @param   Container  \$container  The DI container.
                 *
                 * @return  void
                 */
                public function register(Container \$container)
                {
                    \$container->registerServiceProvider(new ModuleDispatcherFactory('\\\\{$escapedPrefix}'));
                    \$container->registerServiceProvider(new HelperFactory('\\\\{$escapedPrefix}\\\\{$clientSegment}\\\\Helper'));

                    \$container->registerServiceProvider(new Module());
                }
            };

            PHP;
    }

    /**
     * Renders services/provider.php for a plugin.
     *
     * @param   string  $namespacePrefix  Base namespace of the plugin, e.g. Acme\Plugin\Content\Example.
     * @param   string  $group            Plugin group, e.g. content.
     * @param   string  $element          Plugin element, i.e. the folder name.
     * @param   string  $className        Short name of the plugin class under Extension.
     *
     * @return  string
     * @since   1.0.0
     */
    public function pluginProvider(string $namespacePrefix, string $group, string $element, string $className): string
    {
        $namespacePrefix = trim($namespacePrefix, '\\');
        $subPackage      = ucfirst($group) . '.' . $element;

        return <<<PHP
            <?php

            /**
             * @package     Plugin
             * @subpackage  {$subPackage}
             *
             * @license     GNU General Public License version 2 or later; see LICENSE.txt
             */

            \\defined('_JEXEC') or die;

            use Joomla\\CMS\\Extension\\PluginInterface;
            use Joomla\\CMS\\Factory;
            use Joomla\\CMS\\Plugin\\PluginHelper;
            use Joomla\\DI\\Container;
            use Joomla\\DI\\ServiceProviderInterface;
            use {$namespacePrefix}\\Extension\\{$className};

            return new class () implements ServiceProviderInterface {
                /**
                 * Registers the service provider with a DI container.
                 *
                 * @param   Container  \$container  The DI container.
                 *
                 * @return  void
                 */
                public function register(Container \$container): void
                {
                    \$container->set(
                        PluginInterface::class,
                        \$container->lazy({$className}::class, function (Container \$container) {
                            \$plugin = new {$className}(
                                (array) PluginHelper::getPlugin('{$group}', '{$element}')
                            );
                            \$plugin->setApplication(Factory::getApplication());

                            return \$plugin;
                        })
                    );
                }
            };

            PHP;
    }

    /**
     * `LatestNews` becomes `latest_news`-ish lower case for the doc block.
     *
     * @param   string  $name  UpperCamelCase name.
     *
     * @return  string
     * @since   1.0.0
     */
    private function toLowerName(string $name): string
    {
        return strtolower($name);
    }
}
