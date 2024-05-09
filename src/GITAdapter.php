<?php

namespace SpaceMade\GIT;

use DateTime;
use GuzzleHttp\Exception\ClientException;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Throwable;

/**
 * Class GITAdapter
 *
 * @package SpaceMade\GIT
 */
class GITAdapter implements FilesystemAdapter
{
    const UPLOADED_FILE_COMMIT_MESSAGE = 'Uploaded file via GIT API';
    const DELETED_FILE_COMMIT_MESSAGE = 'Deleted file via GIT API';

    protected Client $client;

    protected PathPrefixer $prefixer;

    protected ExtensionMimeTypeDetector $mimeTypeDetector;

    public function __construct(Client $client, $prefix = '')
    {
        $this->client = $client;
        $this->prefixer = new PathPrefixer($prefix, DIRECTORY_SEPARATOR);
        $this->mimeTypeDetector = new ExtensionMimeTypeDetector();
    }
    
    /**
     * @inheritdoc
     */
    public function fileExists(string $path): bool
    {
        try {
            $this->client->read($this->prefixer->prefixPath($path));
        } catch (Throwable $e) {
            if ($e instanceof ClientException && $e->getCode() == 404) {
                return false;
            }
            
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    
        return true;
    }

    /**
     * @inheritdoc
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $location = $this->prefixer->prefixPath($path);
        
        try {
            $override = $this->fileExists($location);
            
            $this->client->upload($location, $contents, self::UPLOADED_FILE_COMMIT_MESSAGE, $override);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $location = $this->prefixer->prefixPath($path);
        
        try {
            $override = $this->fileExists($location);
            
            $this->client->uploadStream($location, $contents, self::UPLOADED_FILE_COMMIT_MESSAGE, $override);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function read(string $path): string
    {
        try {
            return $this->client->readRaw($this->prefixer->prefixPath($path));
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function readStream(string $path)
    {
        try {
            if (null === ($resource = $this->client->readStream($this->prefixer->prefixPath($path)))) {
                throw UnableToReadFile::fromLocation($path, 'Empty content');
            }

            return $resource;
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function delete(string $path): void
    {
        try {
            $this->client->delete($this->prefixer->prefixPath($path), self::DELETED_FILE_COMMIT_MESSAGE);
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteDirectory(string $path): void
    {
        $files = $this->listContents($this->prefixer->prefixPath($path), false);
    
        /** @var StorageAttributes $file */
        foreach ($files as $file) {
            if ($file->isFile()) {
                try {
                    $this->client->delete($file->path(), self::DELETED_FILE_COMMIT_MESSAGE);
                } catch (Throwable $e) {
                    throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function createDirectory(string $path, Config $config): void
    {
        $path = rtrim($path, '/') . '/.gitkeep';
    
        try {
            $this->write($this->prefixer->prefixPath($path), '', $config);
        } catch (Throwable $e) {
            throw UnableToCreateDirectory::dueToFailure($path, $e);
        }
    }

    /**
     * @inheritdoc
     *
     * @throws \League\Flysystem\UnableToSetVisibility
     */
    public function setVisibility(string $path, $visibility): void
    {
        throw new UnableToSetVisibility(get_class($this).' GIT API does not support visibility.');
    }
    
    /**
     * @inheritdoc
     *
     * @throws \League\Flysystem\UnableToSetVisibility
     */
    public function visibility(string $path): FileAttributes
    {
        throw new UnableToSetVisibility(get_class($this).' GIT API does not support visibility.');
    }
    
    /**
     * @inheritdoc
     *
     * @throws \League\Flysystem\UnableToRetrieveMetadata
     */
    public function mimeType(string $path): FileAttributes
    {
        $mimeType = $this->mimeTypeDetector->detectMimeTypeFromPath($this->prefixer->prefixPath($path));
    
        if ($mimeType === null) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }
        
        return new FileAttributes($path, null, null, null, $mimeType);
    }
    
    /**
     * @inheritdoc
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            $response = $this->client->blame($this->prefixer->prefixPath($path));
            
            if (empty($response)) {
                return new FileAttributes($path, null, null, null);
            }
            
            $lastModified = DateTime::createFromFormat("Y-m-d\TH:i:s.uO", $response[0]['commit']['committed_date']);
            
            return new FileAttributes($path, null, null, $lastModified->getTimestamp());
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $meta = $this->client->read($this->prefixer->prefixPath($path));
        
            return new FileAttributes($path, $meta['size'][0] ?? 0);
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $tree = $this->client->tree($this->prefixer->prefixPath($path), $deep);
            
            foreach ($tree as $folders) {
                foreach ($folders as $item) {
                    $isDirectory = $item['type'] == 'tree';
        
                    yield $isDirectory ? new DirectoryAttributes($item['path'], null, null) : new FileAttributes(
                        $item['path'],
                        $this->fileSize($item['path'])->fileSize(),
                        null,
                        $this->lastModified($item['path'])->lastModified(),
                        $this->mimeTypeDetector->detectMimeTypeFromPath($item['path'])
                    );
                }
            }
        } catch (Throwable $e) {
            throw new UnableToRetrieveFileTree($e->getMessage());
        }
    }
    
    /**
     * @inheritdoc
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $contents = $this->client->readRaw($this->prefixer->prefixPath($source));
        
            $this->client->upload(
                $this->prefixer->prefixPath($destination),
                $contents,
                self::UPLOADED_FILE_COMMIT_MESSAGE
            );
        
            $this->client->delete($this->prefixer->prefixPath($source), self::DELETED_FILE_COMMIT_MESSAGE);
        } catch (Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $contents = $this->client->readRaw($this->prefixer->prefixPath($source));
        
            $this->client->upload(
                $this->prefixer->prefixPath($destination),
                $contents,
                self::UPLOADED_FILE_COMMIT_MESSAGE
            );
        } catch (Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @inheritdoc
     */
    public function directoryExists(string $path): bool
    {
        try {
            $tree = $this->client->tree($this->prefixer->prefixPath($path));

            return (bool)count($tree->current());
        } catch (Throwable $e) {
            if ($e instanceof ClientException && $e->getCode() == 404) {
                return false;
            }
            
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }
}
