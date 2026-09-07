<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Extension
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Extension;

use Joomla\Rector\Extension\ExtensionTemplateFactory;
use PHPUnit\Framework\TestCase;

/**
 * The generated files are written verbatim into a user's extension, so a syntax error in a
 * template would only surface at runtime. Every template is therefore parsed here.
 *
 * @since  1.0.0
 */
final class ExtensionTemplateFactoryTest extends TestCase
{
    private ExtensionTemplateFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ExtensionTemplateFactory();
    }

    public function testComponentProviderIsValidPhpAndWiresTheNamespace(): void
    {
        $code = $this->factory->componentProvider('Acme\\Example', 'Example');

        $this->assertValidPhp($code);
        $this->assertStringContainsString('use Acme\\Example\\Administrator\\Extension\\ExampleComponent;', $code);
        $this->assertStringContainsString("new MVCFactory('\\\\Acme\\\\Example')", $code);
        $this->assertStringContainsString('implements ServiceProviderInterface', $code);
    }

    public function testComponentExtensionClassIsValidPhpAndNamespaced(): void
    {
        $code = $this->factory->componentExtensionClass('Acme\\Example', 'Example');

        $this->assertValidPhp($code);
        $this->assertStringContainsString('namespace Acme\\Example\\Administrator\\Extension;', $code);
        $this->assertStringContainsString('class ExampleComponent extends MVCComponent', $code);
    }

    public function testModuleProviderUsesTheClientSpecificHelperNamespace(): void
    {
        $site = $this->factory->moduleProvider('Acme\\Module\\LatestNews', 'mod_latest_news', 'site');

        $this->assertValidPhp($site);
        $this->assertStringContainsString("new ModuleDispatcherFactory('\\\\Acme\\\\Module\\\\LatestNews')", $site);
        $this->assertStringContainsString("new HelperFactory('\\\\Acme\\\\Module\\\\LatestNews\\\\Site\\\\Helper')", $site);

        $admin = $this->factory->moduleProvider('Acme\\Module\\Tools', 'mod_tools', 'administrator');

        $this->assertValidPhp($admin);
        $this->assertStringContainsString("new HelperFactory('\\\\Acme\\\\Module\\\\Tools\\\\Administrator\\\\Helper')", $admin);
    }

    public function testPluginProviderReferencesTheExtensionClassAndPluginIdentity(): void
    {
        $code = $this->factory->pluginProvider('Acme\\Plugin\\Content\\Example', 'content', 'example', 'Example');

        $this->assertValidPhp($code);
        $this->assertStringContainsString('use Acme\\Plugin\\Content\\Example\\Extension\\Example;', $code);
        $this->assertStringContainsString("PluginHelper::getPlugin('content', 'example')", $code);
        $this->assertStringContainsString('$container->lazy(Example::class', $code);
    }

    /**
     * Parses the code, so a broken template fails here rather than in a user's site.
     */
    private function assertValidPhp(string $code): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jrector-tpl-') . '.php';

        file_put_contents($file, $code);

        $output   = [];
        $exitCode = 0;

        exec(\sprintf('%s -l %s 2>&1', escapeshellarg(\PHP_BINARY), escapeshellarg($file)), $output, $exitCode);

        unlink($file);

        $this->assertSame(0, $exitCode, 'Generated template is not valid PHP: ' . implode("\n", $output));
    }
}
