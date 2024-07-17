<?php

namespace DerCoder\SimpleCache\SharedMemory;

use InvalidArgumentException;

class InvalidTtlException extends InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException
{

}
