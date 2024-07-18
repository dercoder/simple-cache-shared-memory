<?php

namespace DerCoder\SimpleCache\SharedMemory\Tests;

use Psr\SimpleCache\InvalidArgumentException;

class BasicTest extends TestCase
{
    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testSetAndGet(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertEquals('value1', $this->cache->get('key1'));

        $this->cache->set('key2', [1, 2, 3], 60);
        $this->assertEquals([1, 2, 3], $this->cache->get('key2'));

        $this->assertNull($this->cache->get('key3'));
        $this->cache->set('key3', ['some' => 'data'], 60);
        $this->assertEquals(['some' => 'data'], $this->cache->get('key3'));
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testHasAndDelete(): void
    {
        $this->assertNull($this->cache->get('key3'));
        $this->assertFalse($this->cache->has('key3'));

        $this->cache->set('key3', ['some' => 'data'], 60);
        $this->assertTrue($this->cache->has('key3'));

        $this->cache->delete('key3');
        $this->assertFalse($this->cache->has('key3'));
    }
}
