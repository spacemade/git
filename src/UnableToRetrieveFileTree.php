<?php

declare(strict_types=1);

namespace SpaceMade\GIT;

use League\Flysystem\FilesystemOperationFailed;
use RuntimeException;

/**
 * Class UnableToRetrieveFileTree
 *
 * @package SpaceMade\GIT
 */
final class UnableToRetrieveFileTree extends RuntimeException implements FilesystemOperationFailed
{
    /**
     * @see FilesystemOperationFailed::operation()
     */
    public function operation(): string
    {
        return FilesystemOperationFailed::OPERATION_READ;
    }
}
