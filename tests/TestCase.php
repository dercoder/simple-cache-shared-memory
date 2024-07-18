<?php

namespace DerCoder\SimpleCache\SharedMemory\Tests;

use DerCoder\SimpleCache\SharedMemory\SharedMemoryCache;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SharedMemoryCache
     */
    protected $cache;

    public function setUp(): void
    {
        parent::setUp();
        $this->cache = new SharedMemoryCache('1M');
        $this->cache->clear();
    }
}
