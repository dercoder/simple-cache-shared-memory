<?php

namespace DerCoder\SimpleCache\SharedMemory\Tests;

use DerCoder\SimpleCache\SharedMemory\InvalidTtlException;
use Psr\SimpleCache\InvalidArgumentException;

class MultipleTest extends TestCase
{
    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testSetMultipleWithValidDataAndTtl(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $ttl = 2; // 2 seconds

        $this->assertTrue($this->cache->setMultiple($data, $ttl));

        // Check if the values are set correctly
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $this->cache->get($key));
        }

        // Wait for TTL to expire
        sleep($ttl + 1);

        // Check if the values have been deleted
        foreach ($data as $key => $value) {
            $this->assertNull($this->cache->get($key));
        }
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testSetMultipleWithValidDataAndNoTtl(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];

        $this->assertTrue($this->cache->setMultiple($data));

        // Check if the values are set correctly
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $this->cache->get($key));
        }

        // Clear the cache
        $this->assertTrue($this->cache->clear());

        // Check if the values have been deleted
        foreach ($data as $key => $value) {
            $this->assertNull($this->cache->get($key));
        }
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testSetMultipleWithEmptyData(): void
    {
        $data = [];
        $this->assertFalse($this->cache->setMultiple($data));

        // Check if the cache is empty
        $this->assertCount(0, $this->cache->getMultiple(array_keys($data)));
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testSetMultipleWithInvalidTtl(): void
    {
        $data = ['key1' => 'value1'];
        $ttl = -60; // Negative TTL

        $this->expectException(InvalidTtlException::class);
        $this->cache->setMultiple($data, $ttl);
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testSetMultipleWithInvalidTtlType(): void
    {
        $data = ['key1' => 'value1'];
        $ttl = 'invalid_ttl'; // Non-integer and non-DateInterval TTL

        $this->expectException(InvalidTtlException::class);
        $this->cache->setMultiple($data, $ttl);
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testGetMultipleWithExistingKeys(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'], $result);
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testGetMultipleWithNonExistingKeys(): void
    {
        $result = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');
        $this->assertEquals(['key1' => 'default', 'key2' => 'default', 'key3' => 'default'], $result);
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testGetMultipleWithEmptyKeysArray(): void
    {
        $result = $this->cache->getMultiple([], 'default');
        $this->assertEquals([], $result);
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testGetMultipleWithMixOfExistingAndNonExistingKeys(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2', 'key3' => 'default'], $result);
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function testGetMultipleWithLargeNumberOfKeys(): void
    {
        for ($i = 1; $i <= 1000; $i++) {
            $this->cache->set("key$i", "value$i");
        }

        $keys = array_map(function ($i) {
            return "key$i";
        }, range(1, 1000));

        $result = $this->cache->getMultiple($keys, 'default');

        $expectedResult = [];
        for ($i = 1; $i <= 1000; $i++) {
            $expectedResult["key$i"] = "value$i";
        }

        $this->assertEquals($expectedResult, $result);
    }
}
