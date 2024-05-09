<?php

namespace SpaceMade\GIT\Tests;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\Test;
use SpaceMade\GIT\Client;
use SpaceMade\GIT\GITAdapter;

class GITAdapterTest extends TestCase
{
    protected GITAdapter $GITAdapter;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->GITAdapter = $this->getAdapterInstance();
    }

    #[Test]
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(GITAdapter::class, $this->getAdapterInstance());
    }

    #[Test]
    public function it_can_retrieve_client_instance()
    {
        $this->assertInstanceOf(Client::class, $this->GITAdapter->getClient());
    }

    #[Test]
    public function it_can_set_client_instance()
    {
        $this->setInvalidProjectId();

        $this->assertEquals($this->GITAdapter->getClient()
            ->getProjectId(), '123');
    }

    #[Test]
    public function it_can_read_a_file()
    {
        $response = $this->GITAdapter->read('README.md');

        $this->assertStringStartsWith('# Testing repo for `flysystem-GIT`', $response);
    }

    #[Test]
    public function it_can_read_a_file_into_a_stream()
    {
        $stream = $this->GITAdapter->readStream('README.md');

        $this->assertIsResource($stream);
        $this->assertEquals(stream_get_contents($stream, -1, 0), $this->GITAdapter->read('README.md'));
    }

    #[Test]
    public function it_throws_when_read_failed()
    {
        $this->setInvalidProjectId();

        $this->expectException(UnableToReadFile::class);

        $this->GITAdapter->read('README.md');
    }

    #[Test]
    public function it_can_determine_if_a_project_has_a_file()
    {
        $this->assertTrue($this->GITAdapter->fileExists('/README.md'));

        $this->assertFalse($this->GITAdapter->fileExists('/I_DONT_EXIST.md'));
    }

    #[Test]
    public function it_throws_when_file_existence_failed()
    {
        $this->setInvalidToken();

        $this->expectException(UnableToCheckFileExistence::class);

        $this->GITAdapter->fileExists('/README.md');
    }

    #[Test]
    public function it_can_delete_a_file()
    {
        $this->GITAdapter->write('testing.md', '# Testing create', new Config());

        $this->assertTrue($this->GITAdapter->fileExists('/testing.md'));

        $this->GITAdapter->delete('/testing.md');

        $this->assertFalse($this->GITAdapter->fileExists('/testing.md'));
    }

    #[Test]
    public function it_returns_false_when_delete_failed()
    {
        $this->setInvalidProjectId();

        $this->expectException(UnableToDeleteFile::class);

        $this->GITAdapter->delete('testing_renamed.md');
    }

    #[Test]
    public function it_can_write_a_new_file()
    {
        $this->GITAdapter->write('testing.md', '# Testing create', new Config());

        $this->assertTrue($this->GITAdapter->fileExists('testing.md'));
        $this->assertEquals('# Testing create', $this->GITAdapter->read('testing.md'));

        $this->GITAdapter->delete('testing.md');
    }

    #[Test]
    public function it_automatically_creates_missing_directories()
    {
        $this->GITAdapter->write('/folder/missing/testing.md', '# Testing create folders', new Config());

        $this->assertTrue($this->GITAdapter->fileExists('/folder/missing/testing.md'));
        $this->assertEquals('# Testing create folders', $this->GITAdapter->read('/folder/missing/testing.md'));

        $this->GITAdapter->delete('/folder/missing/testing.md');
    }

    #[Test]
    public function it_throws_when_write_failed()
    {
        $this->setInvalidProjectId();

        $this->expectException(UnableToWriteFile::class);

        $this->GITAdapter->write('testing.md', '# Testing create', new Config());
    }

    #[Test]
    public function it_can_write_a_file_stream()
    {
        $stream = fopen(__DIR__.'/assets/testing.txt', 'r+');
        $this->GITAdapter->writeStream('testing.txt', $stream, new Config());
        fclose($stream);

        $this->assertTrue($this->GITAdapter->fileExists('testing.txt'));
        $this->assertEquals('File for testing file streams', $this->GITAdapter->read('testing.txt'));

        $this->GITAdapter->delete('testing.txt');
    }

    #[Test]
    public function it_throws_when_writing_file_stream_failed()
    {
        $this->setInvalidProjectId();

        $this->expectException(UnableToWriteFile::class);

        $stream = fopen(__DIR__.'/assets/testing.txt', 'r+');
        $this->GITAdapter->writeStream('testing.txt', $stream, new Config());
        fclose($stream);
    }

    #[Test]
    public function it_can_override_a_file()
    {
        $this->GITAdapter->write('testing.md', '# Testing create', new Config());
        $this->GITAdapter->write('testing.md', '# Testing update', new Config());

        $this->assertStringStartsWith($this->GITAdapter->read('testing.md'), '# Testing update');

        $this->GITAdapter->delete('testing.md');
    }

    #[Test]
    public function it_can_override_with_a_file_stream()
    {
        $stream = fopen(__DIR__.'/assets/testing.txt', 'r+');
        $this->GITAdapter->writeStream('testing.txt', $stream, new Config());
        fclose($stream);

        $stream = fopen(__DIR__.'/assets/testing-update.txt', 'r+');
        $this->GITAdapter->writeStream('testing.txt', $stream, new Config());
        fclose($stream);

        $this->assertTrue($this->GITAdapter->fileExists('testing.txt'));
        $this->assertEquals('File for testing file streams!', $this->GITAdapter->read('testing.txt'));

        $this->GITAdapter->delete('testing.txt');
    }

    #[Test]
    public function it_can_move_a_file()
    {
        $this->GITAdapter->write('testing.md', '# Testing move', new Config());

        $this->GITAdapter->move('testing.md', 'testing_move.md', new Config());

        $this->assertFalse($this->GITAdapter->fileExists('testing.md'));
        $this->assertTrue($this->GITAdapter->fileExists('testing_move.md'));

        $this->assertEquals('# Testing move', $this->GITAdapter->read('testing_move.md'));

        $this->GITAdapter->delete('testing_move.md');
    }

    #[Test]
    public function it_throws_when_move_failed()
    {
        $this->setInvalidProjectId();

        $this->expectException(UnableToMoveFile::class);

        $this->GITAdapter->move('testing_move.md', 'testing.md', new Config());
    }

    #[Test]
    public function it_can_copy_a_file()
    {
        $this->GITAdapter->write('testing.md', '# Testing copy', new Config());

        $this->GITAdapter->copy('testing.md', 'testing_copy.md', new Config());

        $this->assertTrue($this->GITAdapter->fileExists('testing.md'));
        $this->assertTrue($this->GITAdapter->fileExists('testing_copy.md'));

        $this->assertEquals($this->GITAdapter->read('testing.md'), '# Testing copy');
        $this->assertEquals($this->GITAdapter->read('testing_copy.md'), '# Testing copy');

        $this->GITAdapter->delete('testing.md');
        $this->GITAdapter->delete('testing_copy.md');
    }

    #[Test]
    public function it_throws_when_copy_failed()
    {
        $this->setInvalidProjectId();

        $this->expectException(UnableToCopyFile::class);

        $this->GITAdapter->copy('testing_copy.md', 'testing.md', new Config());
    }

    #[Test]
    public function it_can_create_a_directory()
    {
        $this->GITAdapter->createDirectory('/testing', new Config());

        $this->assertTrue($this->GITAdapter->fileExists('/testing/.gitkeep'));

        $this->GITAdapter->delete('/testing/.gitkeep');
    }

    #[Test]
    public function it_can_retrieve_a_list_of_contents_of_root()
    {
        $list = $this->GITAdapter->listContents('/', false);
        $expectedPaths = [
            ['type' => 'dir', 'path' => 'recursive'],
            ['type' => 'file', 'path' => 'LICENSE'],
            ['type' => 'file', 'path' => 'README.md'],
            ['type' => 'file', 'path' => 'test'],
            ['type' => 'file', 'path' => 'test2'],
        ];

        foreach ($list as $item) {
            $this->assertInstanceOf(StorageAttributes::class, $item);
            $this->assertContains(
                ['type' => $item['type'], 'path' => $item['path']], $expectedPaths
            );
        }
    }

    #[Test]
    public function it_can_retrieve_a_list_of_contents_of_root_recursive()
    {
        $list = $this->GITAdapter->listContents('/', true);
        $expectedPaths = [
            ['type' => 'dir', 'path' => 'recursive'],
            ['type' => 'dir', 'path' => 'recursive/level-1'],
            ['type' => 'dir', 'path' => 'recursive/level-1/level-2'],
            ['type' => 'file', 'path' => 'LICENSE'],
            ['type' => 'file', 'path' => 'README.md'],
            ['type' => 'file', 'path' => 'recursive/recursive.testing.md'],
            ['type' => 'file', 'path' => 'recursive/level-1/level-2/.gitkeep'],
            ['type' => 'file', 'path' => 'test'],
            ['type' => 'file', 'path' => 'test2'],
        ];

        foreach ($list as $item) {
            $this->assertInstanceOf(StorageAttributes::class, $item);
            $this->assertContains(
                ['type' => $item['type'], 'path' => $item['path']], $expectedPaths
            );
        }
    }

    #[Test]
    public function it_can_retrieve_a_list_of_contents_of_sub_folder()
    {
        $list = $this->GITAdapter->listContents('/recursive', false);
        $expectedPaths = [
            ['type' => 'dir', 'path' => 'recursive/level-1'],
            ['type' => 'dir', 'path' => 'recursive/level-1/level-2'],
            ['type' => 'file', 'path' => 'recursive/recursive.testing.md'],
            ['type' => 'file', 'path' => 'recursive/level-1/level-2/.gitkeep'],
        ];

        foreach ($list as $item) {
            $this->assertInstanceOf(StorageAttributes::class, $item);
            $this->assertContains(
                ['type' => $item['type'], 'path' => $item['path']], $expectedPaths
            );
        }
    }

    #[Test]
    public function it_can_retrieve_a_list_of_contents_of_deep_sub_folder()
    {
        $list = $this->GITAdapter->listContents('/recursive/level-1/level-2', false);
        $expectedPaths = [
            ['type' => 'file', 'path' => 'recursive/level-1/level-2/.gitkeep'],
        ];

        foreach ($list as $item) {
            $this->assertInstanceOf(StorageAttributes::class, $item);
            $this->assertContains(
                ['type' => $item['type'], 'path' => $item['path']], $expectedPaths
            );
        }
    }

    #[Test]
    public function it_can_delete_a_directory()
    {
        $this->GITAdapter->createDirectory('/testing', new Config());
        $this->GITAdapter->write('/testing/testing.md', 'Testing delete directory', new Config());

        $this->GITAdapter->deleteDirectory('/testing');

        $this->assertFalse($this->GITAdapter->fileExists('/testing/.gitkeep'));
        $this->assertFalse($this->GITAdapter->fileExists('/testing/testing.md'));
    }

    #[Test]
    public function it_throws_when_delete_directory_failed()
    {
        $this->setInvalidProjectId();
        
        $this->expectException(FilesystemException::class);
        
        $this->GITAdapter->deleteDirectory('/testing');
    }

    #[Test]
    public function it_can_retrieve_size()
    {
        $size = $this->GITAdapter->fileSize('README.md');

        $this->assertInstanceOf(FileAttributes::class, $size);
        $this->assertEquals(37, $size->fileSize());
    }

    #[Test]
    public function it_can_retrieve_mimetype()
    {
        $metadata = $this->GITAdapter->mimeType('README.md');

        $this->assertInstanceOf(FileAttributes::class, $metadata);
        $this->assertEquals('text/markdown', $metadata->mimeType());
    }

    #[Test]
    public function it_can_not_retrieve_lastModified()
    {
        $lastModified = $this->GITAdapter->lastModified('README.md');

        $this->assertInstanceOf(FileAttributes::class, $lastModified);
        $this->assertEquals(1606750652, $lastModified->lastModified());
    }

    #[Test]
    public function it_throws_when_getting_visibility()
    {
        $this->expectException(UnableToSetVisibility::class);

        $this->GITAdapter->visibility('README.md');
    }

    #[Test]
    public function it_throws_when_setting_visibility()
    {
        $this->expectException(UnableToSetVisibility::class);

        $this->GITAdapter->setVisibility('README.md', 0777);
    }

    #[Test]
    public function it_can_check_directory_if_exists()
    {
        $dir = 'test-dir/test-dir2/test-dir3';
        $this->GITAdapter->createDirectory($dir, new Config());
        $this->assertTrue($this->GITAdapter->directoryExists($dir));
        $this->GITAdapter->deleteDirectory($dir);
    }

    #[Test]
    public function it_cannot_check_if_directory_exists()
    {
        $this->assertFalse($this->GITAdapter->directoryExists('test_non_existent_dir'));
    }
    
    private function setInvalidToken()
    {
        $client = $this->GITAdapter->getClient();
        $client->setPersonalAccessToken('123');
        $this->GITAdapter->setClient($client);
    }
    
    private function setInvalidProjectId()
    {
        $client = $this->GITAdapter->getClient();
        $client->setProjectId('123');
        $this->GITAdapter->setClient($client);
    }
}
