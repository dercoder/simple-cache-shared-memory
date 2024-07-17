<?php

namespace DerCoder\SimpleCache\SharedMemory;

use DateInterval;
use DateTime;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

class SharedMemoryCache implements CacheInterface
{
    const SERIALIZER_PHP = 'php';
    const SERIALIZER_IGBINARY = 'igbinary';
    const HASH_CRC32 = 'crc32';

    /**
     * @var resource
     */
    private $shm;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param string|int $size
     * @param array      $options
     */
    public function __construct($size, array $options = [])
    {
        $this->size = ini_parse_quantity($size);
        $this->options = array_merge([
            'key'         => ftok(__FILE__, 'S'),
            'hash'        => self::HASH_CRC32,
            'serializer'  => self::SERIALIZER_PHP,
            'permissions' => 0666
        ], $options);

        if (!$this->shm = shm_attach($this->options['key'], $this->size, $this->options['permissions'])) {
            throw new RuntimeException('Failed to attach shared memory segment');
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
        if (!$result = shm_get_var($this->shm, $this->getHashKey($key))) {
            return $default;
        }

        $data = $this->unserialize($result);
        $expires = $data['expires'];
        $payload = $data['payload'];

        if ($expires <= new DateTime()) {
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
        $data = $this->serialize([
            'payload' => $value,
            'expires' => $this->calculateExpiration($ttl)
        ]);

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

    public function clear()
    {
        // TODO: Implement clear() method.
    }

    public function getMultiple($keys, $default = null)
    {
        // TODO: Implement getMultiple() method.
    }

    public function setMultiple($values, $ttl = null)
    {
        // TODO: Implement setMultiple() method.
    }

    public function deleteMultiple($keys)
    {
        // TODO: Implement deleteMultiple() method.
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
     * @param string $key
     *
     * @return int
     */
    protected function getHashKey(string $key): int
    {
        $hash = hash($this->options['hash'], $key);
        return hexdec($hash);
    }

    /**
     * @param null|int|DateInterval $ttl
     *
     * @throws InvalidArgumentException
     */
    private function calculateExpiration($ttl): DateTime
    {
        if (is_int($ttl)) {
            return (new DateTime())->add(new DateInterval("PT{$ttl}S"));
        } elseif ($ttl instanceof DateInterval) {
            return (new DateTime())->add($ttl);
        }

        throw new InvalidTtlException('Invalid TTL specified');
    }

    /**
     * @param string $data
     *
     * @return mixed
     * @throws InvalidSerializerException
     */
    private function unserialize(string $data)
    {
        switch ($this->options['serializer']) {
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
    private function serialize($data): ?string
    {
        switch ($this->options['serializer']) {
            case self::SERIALIZER_PHP:
                return serialize($data);
            case self::SERIALIZER_IGBINARY:
                return igbinary_serialize($data);
            default:
                throw new InvalidSerializerException('Invalid serializer specified');
        }
    }
}
