<?php

namespace SpaceMade\GIT\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use SpaceMade\GIT\Client;
use SpaceMade\GIT\GITAdapter;

abstract class TestCase extends BaseTestCase
{
    protected array $config;

    public function setUp(): void
    {
        $this->config = require(__DIR__.'/config/config.testing.php');
    }

    protected function getClientInstance(): Client
    {
        return new Client($this->config[ 'project-id' ], $this->config[ 'branch' ], $this->config[ 'base-url' ],
            $this->config[ 'personal-access-token' ]);
    }

    protected function getAdapterInstance(): GITAdapter
    {
        return new GITAdapter($this->getClientInstance());
    }
}
