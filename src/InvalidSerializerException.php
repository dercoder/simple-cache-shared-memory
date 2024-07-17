<?php

namespace DerCoder\SimpleCache\SharedMemory;

use InvalidArgumentException;

class InvalidSerializerException extends InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException
{

}
