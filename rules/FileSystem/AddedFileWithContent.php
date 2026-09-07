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
 * A file that a rule wants to create, together with its content.
 *
 * Rector 2 no longer ships the AddedFileWithContent value object that earlier versions had, so
 * the structural Joomla rules bring their own. Collecting the intent as a value object — instead
 * of writing straight from the rule — keeps the rules free of file system side effects and lets
 * AddedFileCollectorService decide what actually happens on disk.
 *
 * @since  1.0.0
 */
final class AddedFileWithContent
{
    /**
     * @param   string  $filePath  Absolute path of the file to create.
     * @param   string  $content   The full file content.
     *
     * @since   1.0.0
     */
    public function __construct(
        private readonly string $filePath,
        private readonly string $content,
    ) {
    }

    /**
     * @return  string
     * @since   1.0.0
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return  string
     * @since   1.0.0
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * The path with forward slashes, for comparisons and de-duplication.
     *
     * @return  string
     * @since   1.0.0
     */
    public function getNormalisedFilePath(): string
    {
        return str_replace('\\', '/', $this->filePath);
    }
}
