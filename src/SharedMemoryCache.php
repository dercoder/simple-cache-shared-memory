<?php

namespace DerCoder\SimpleCache\SharedMemory;

use DateInterval;
use DateTime;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

class SharedMemoryCache implements CacheInterface
{
    const SERIALIZER_PHP = 'php';
    const SERIALIZER_IGBINARY = 'igbinary';
    const HASH_ALGORITHM_CRC32 = 'crc32';

    /**
     * @var resource
     */
    private $shm;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var int
     */
    protected $key;

    /**
     * @var string
     */
    protected $hashAlgorithm;

    /**
     * @var string
     */
    protected $serializer;

    /**
     * @var int
     */
    protected $compressionLevel;

    /**
     * @var int
     */
    protected $permissions;

    /**
     * @param string|int $size
     * @param array      $options
     *
     * @throws Exception
     */
    public function __construct($size, array $options = [])
    {
        $this->size = ini_parse_quantity($size);
        $options = array_merge([
            'key'              => ftok(__FILE__, 'S'),
            'hashAlgorithm'    => self::HASH_ALGORITHM_CRC32,
            'serializer'       => self::SERIALIZER_PHP,
            'compressionLevel' => 6,
            'permissions'      => 0666
        ], $options);

        $this->key = $options['key'];
        $this->hashAlgorithm = $options['hashAlgorithm'];
        $this->serializer = $options['serializer'];
        $this->compressionLevel = $options['compressionLevel'];
        $this->permissions = $options['permissions'];

        if ($this->serializer === self::SERIALIZER_IGBINARY && !extension_loaded('igbinary')) {
            throw new Exception('Igbinary extension is not installed');
        }

        $this->createSharedMemorySegment();
    }

    /**
     * Disconnect from shared memory segment.
     */
    public function __destruct()
    {
        if ($this->shm) {
            shm_detach($this->shm);
        }
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        if (!$this->has($key)) {
            return $default;
        }

        $data = shm_get_var($this->shm, $this->getHashKey($key));
        $data = $this->unserialize(
            $this->decompress($data)
        );

        $expires = $data['expires'];
        $payload = $data['payload'];

        if ($expires instanceof DateTime && $expires <= new DateTime()) {
            $this->delete($key);
            return $default;
        }

        return $payload;
    }

    /**
     * @param string                $key
     * @param mixed                 $value
     * @param null|int|DateInterval $ttl
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function set($key, $value, $ttl = null): bool
    {
        $data = $this->compress(
            $this->serialize([
                'payload' => $value,
                'expires' => $this->calculateExpiration($ttl)
            ])
        );

        return shm_put_var($this->shm, $this->getHashKey($key), $data);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function delete($key): bool
    {
        return shm_remove_var($this->shm, $this->getHashKey($key));
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $this->removeSharedMemorySegment();
        $this->createSharedMemorySegment();
        return true;
    }

    /**
     * @return bool
     */
    public function destroy(): bool
    {
        $this->removeSharedMemorySegment();
        return true;
    }

    /**
     * @param $keys
     * @param $default
     *
     * @return iterable
     * @throws InvalidArgumentException
     */
    public function getMultiple($keys, $default = null)
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * @param $values
     * @param $ttl
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $result = true;

        if (!$values) {
            return false;
        }

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param $keys
     *
     * @return bool
     */
    public function deleteMultiple($keys): bool
    {
        $result = true;

        if (!$keys) {
            return false;
        }

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key): bool
    {
        return shm_has_var($this->shm, $this->getHashKey($key));
    }

    /**
     * @return void
     */
    protected function createSharedMemorySegment(): void
    {
        if ($this->shm) {
            return;
        }

        if (!$this->shm = shm_attach($this->key, $this->size, $this->permissions)) {
            throw new RuntimeException('Failed to attach shared memory segment');
        }
    }

    /**
     * @return void
     */
    protected function removeSharedMemorySegment(): void
    {
        if (!$this->shm) {
            return;
        }

        if (!shm_remove($this->shm)) {
            throw new RuntimeException('Failed to remove shared memory segment');
        }

        unset($this->shm);
    }

    /**
     * @param string $key
     *
     * @return int
     */
    protected function getHashKey(string $key): int
    {
        $hash = hash($this->hashAlgorithm, $key);
        return hexdec($hash);
    }

    /**
     * @param null|int|DateInterval $ttl
     *
     * @return DateTime|null
     * @throws InvalidArgumentException
     */
    protected function calculateExpiration($ttl): ?DateTime
    {
        if ($ttl === null) {
            return null;
        } elseif (is_int($ttl) && $ttl > 0) {
            return (new DateTime())->add(new DateInterval("PT{$ttl}S"));
        } elseif ($ttl instanceof DateInterval) {
            return (new DateTime())->add($ttl);
        }

        throw new InvalidTtlException('Invalid TTL specified');
    }

    /**
     * @param string $data
     *
     * @return string
     */
    protected function compress(string $data): string
    {
        if (!$data = gzcompress($data, $this->compressionLevel)) {
            throw new RuntimeException('Failed to compress data');
        }

        return $data;
    }

    /**
     * @param string $data
     *
     * @return string
     */
    protected function decompress(string $data): string
    {
        if (!$data = gzuncompress($data)) {
            throw new RuntimeException('Failed to decompress data');
        }

        return $data;
    }

    /**
     * @param string $data
     *
     * @return mixed
     * @throws InvalidSerializerException
     */
    protected function unserialize(string $data)
    {
        switch ($this->serializer) {
            case self::SERIALIZER_PHP:
                return unserialize($data);
            case self::SERIALIZER_IGBINARY:
                return igbinary_unserialize($data);
            default:
                throw new InvalidSerializerException('Invalid serializer specified');
        }
    }

    /**
     * @param mixed $data
     *
     * @return string|null
     */
    protected function serialize($data): ?string
    {
        switch ($this->serializer) {
            case self::SERIALIZER_PHP:
                return serialize($data);
            case self::SERIALIZER_IGBINARY:
                return igbinary_serialize($data);
            default:
                throw new InvalidSerializerException('Invalid serializer specified');
        }
    }
}
