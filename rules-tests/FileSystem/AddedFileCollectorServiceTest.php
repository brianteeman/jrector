<?php

/**
 * @package     Joomla.Rector
 * @subpackage  FileSystem
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\FileSystem;

use Joomla\Rector\FileSystem\AddedFileCollectorService;
use Joomla\Rector\FileSystem\AddedFileWithContent;
use PHPUnit\Framework\TestCase;

/**
 * @since  1.0.0
 */
final class AddedFileCollectorServiceTest extends TestCase
{
    private string $workingDirectory = '';

    protected function setUp(): void
    {
        $this->workingDirectory = sys_get_temp_dir() . '/jrector-added-' . uniqid('', true);

        mkdir($this->workingDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workingDirectory);
    }

    public function testCreatesFileIncludingMissingDirectories(): void
    {
        $target    = $this->workingDirectory . '/services/provider.php';
        $collector = new AddedFileCollectorService(false);

        $collector->addFile(new AddedFileWithContent($target, '<?php // generated'));
        $collector->writeAddedFiles();

        $this->assertFileExists($target);
        $this->assertSame('<?php // generated', file_get_contents($target));
    }

    public function testNeverOverwritesAnExistingFile(): void
    {
        $target = $this->workingDirectory . '/provider.php';
        file_put_contents($target, '<?php // hand written');

        $collector = new AddedFileCollectorService(false);
        $collector->addFile(new AddedFileWithContent($target, '<?php // generated'));
        $collector->writeAddedFiles();

        $this->assertSame(
            '<?php // hand written',
            file_get_contents($target),
            'An existing file must never be clobbered by a generated one.'
        );
    }

    public function testTheFirstRegistrationForAPathWins(): void
    {
        $target    = $this->workingDirectory . '/provider.php';
        $collector = new AddedFileCollectorService(false);

        $collector->addFile(new AddedFileWithContent($target, '<?php // first'));
        $collector->addFile(new AddedFileWithContent($target, '<?php // second'));

        $this->assertCount(1, $collector->getAddedFiles());

        $collector->writeAddedFiles();

        $this->assertSame('<?php // first', file_get_contents($target));
    }

    public function testWritesNothingWhenNothingWasRegistered(): void
    {
        $collector = new AddedFileCollectorService(false);
        $collector->writeAddedFiles();

        $this->assertSame([], glob($this->workingDirectory . '/*'));
    }

    public function testHasFileIsPathSeparatorAgnostic(): void
    {
        $collector = new AddedFileCollectorService(false);
        $collector->addFile(new AddedFileWithContent('C:/tmp/a/provider.php', '<?php'));

        $this->assertTrue($collector->hasFile('C:\\tmp\\a\\provider.php'));
        $this->assertFalse($collector->hasFile('C:/tmp/b/provider.php'));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($directory);
    }
}
