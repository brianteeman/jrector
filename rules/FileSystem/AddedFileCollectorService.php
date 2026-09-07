<?php

/**
 * @package     Joomla.Rector
 * @subpackage  FileSystem
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Rector\FileSystem;

/**
 * Collects files that the structural rules want to create and writes them on shutdown.
 *
 * The counterpart of FileRenameCollectorService: that one moves existing files through a
 * generated rename.php, this one creates new ones such as services/provider.php.
 *
 * Two safety rules apply, both deliberate:
 *
 *   - An existing file is never overwritten. Re-running the rules over a half converted
 *     extension therefore cannot destroy hand written code, and the rules stay idempotent.
 *   - Nothing is written during a --dry-run. Creating source files while the user is only
 *     previewing would be a surprise, so the run reports what it would create instead.
 *
 * One instance is shared by all rules (singleton in the DI container).
 *
 * @since  1.0.0
 */
final class AddedFileCollectorService
{
    /**
     * Pending files, keyed by normalised path so the same file is only created once.
     *
     * @var array<string, AddedFileWithContent>
     */
    private array $addedFiles = [];

    /**
     * @param   bool  $writeOnDestruct  Set to false to only collect. The rule tests register the
     *                                  service this way, so running them cannot scatter generated
     *                                  files across the fixture folders.
     *
     * @since   1.0.0
     */
    public function __construct(
        private readonly bool $writeOnDestruct = true,
    ) {
    }

    /**
     * Write the collected files on service destruction.
     *
     * @since  1.0.0
     */
    public function __destruct()
    {
        if (!$this->writeOnDestruct) {
            return;
        }

        $this->writeAddedFiles();
    }

    /**
     * Creates every registered file that does not exist yet.
     *
     * Public so it can be triggered deliberately, for instance from a test.
     *
     * @return  void
     * @since   1.0.0
     */
    public function writeAddedFiles(): void
    {
        $this->doWriteAddedFiles();
    }

    /**
     * Register a file to be created.
     *
     * The first registration for a path wins; a later one for the same path is ignored.
     *
     * @param   AddedFileWithContent  $addedFile  The file to create.
     *
     * @return  void
     * @since   1.0.0
     */
    public function addFile(AddedFileWithContent $addedFile): void
    {
        $this->addedFiles[$addedFile->getNormalisedFilePath()] ??= $addedFile;
    }

    /**
     * Whether a file is already registered for this path.
     *
     * @param   string  $filePath  Absolute path.
     *
     * @return  bool
     * @since   1.0.0
     */
    public function hasFile(string $filePath): bool
    {
        return isset($this->addedFiles[str_replace('\\', '/', $filePath)]);
    }

    /**
     * All registered files, keyed by normalised path.
     *
     * @return  array<string, AddedFileWithContent>
     * @since   1.0.0
     */
    public function getAddedFiles(): array
    {
        return $this->addedFiles;
    }

    // -------------------------------------------------------------------------

    /**
     * Creates every registered file that does not exist yet.
     *
     * @return  void
     * @since   1.0.0
     */
    private function doWriteAddedFiles(): void
    {
        if ($this->addedFiles === []) {
            return;
        }

        $isDryRun = $this->isDryRun();

        foreach ($this->addedFiles as $addedFile) {
            $filePath = $addedFile->getFilePath();

            if (file_exists($filePath)) {
                continue;
            }

            if ($isDryRun) {
                echo \sprintf("[jrector] would create %s\n", $addedFile->getNormalisedFilePath());

                continue;
            }

            $directory = \dirname($filePath);

            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                fwrite(\STDERR, \sprintf("[jrector] cannot create directory %s\n", $directory));

                continue;
            }

            if (file_put_contents($filePath, $addedFile->getContent()) === false) {
                fwrite(\STDERR, \sprintf("[jrector] cannot write %s\n", $addedFile->getNormalisedFilePath()));

                continue;
            }

            echo \sprintf("[jrector] created %s\n", $addedFile->getNormalisedFilePath());
        }
    }

    /**
     * Whether Rector runs in preview mode.
     *
     * The dry run flag is a console option that Rector reads straight off the input and turns
     * into its Configuration value object; it never reaches SimpleParameterProvider, and that
     * object is built per run and is not available to a shared service. Reading the command
     * line is therefore the only reliable way to see it from here.
     *
     * @return  bool
     * @since   1.0.0
     */
    private function isDryRun(): bool
    {
        $arguments = $_SERVER['argv'] ?? [];

        if (!\is_array($arguments)) {
            return false;
        }

        foreach ($arguments as $argument) {
            // Option::DRY_RUN and Option::DRY_RUN_SHORT, as registered by Rector itself.
            if ($argument === '--dry-run' || $argument === '-n') {
                return true;
            }
        }

        return false;
    }
}
