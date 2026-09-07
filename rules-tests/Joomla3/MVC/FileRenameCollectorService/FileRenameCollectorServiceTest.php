<?php

/**
 * @package     Joomla.Rector
 * @subpackage  Joomla3
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\Tests\Joomla3\MVC\FileRenameCollectorService;

use Joomla\Rector\Joomla3\MVC\FileRenameCollectorService as Collector;
use PHPUnit\Framework\TestCase;

/**
 * @since  1.0.0
 */
final class FileRenameCollectorServiceTest extends TestCase
{
    private string $workingDirectory = '';

    protected function setUp(): void
    {
        $this->workingDirectory = sys_get_temp_dir() . '/jrector-rename-' . uniqid('', true);

        mkdir($this->workingDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_file($this->workingDirectory . '/rename.php')) {
            unlink($this->workingDirectory . '/rename.php');
        }

        if (is_dir($this->workingDirectory)) {
            rmdir($this->workingDirectory);
        }
    }

    public function testWritesNoScriptWhenNothingWasRegistered(): void
    {
        $collector = new Collector();
        unset($collector);

        $this->assertFileDoesNotExist($this->workingDirectory . '/rename.php');
    }

    public function testIgnoresARenameOntoItself(): void
    {
        $collector = new Collector();
        $collector->addRename(
            $this->workingDirectory,
            $this->workingDirectory . '/site/router.php',
            $this->workingDirectory . '/site/router.php'
        );

        $this->assertSame([], $collector->getRenames());
    }

    public function testTreatsBackslashAndForwardSlashAsTheSameFile(): void
    {
        $collector = new Collector();
        $collector->addRename(
            $this->workingDirectory,
            'C:\\project\\site\\router.php',
            'C:/project/site/router.php'
        );

        $this->assertSame([], $collector->getRenames(), 'Only the path separators differ, so this is not a move.');
    }

    public function testWritesNoScriptWhenEveryRegisteredRenameWasANoOp(): void
    {
        $collector = new Collector();
        $collector->addRename(
            $this->workingDirectory,
            $this->workingDirectory . '/a.php',
            $this->workingDirectory . '/a.php'
        );
        unset($collector);

        $this->assertFileDoesNotExist(
            $this->workingDirectory . '/rename.php',
            'A rename.php full of SKIP lines is worse than none at all.'
        );
    }

    public function testWritesTheScriptForARealMove(): void
    {
        $collector = new Collector();
        $collector->addRename(
            $this->workingDirectory,
            $this->workingDirectory . '/site/router.php',
            $this->workingDirectory . '/site/src/Service/Router.php'
        );
        unset($collector);

        $scriptPath = $this->workingDirectory . '/rename.php';

        $this->assertFileExists($scriptPath);

        $script = (string) file_get_contents($scriptPath);

        $this->assertStringContainsString("'site/router.php'", $script);
        $this->assertStringContainsString("'site/src/Service/Router.php'", $script);
    }
}
