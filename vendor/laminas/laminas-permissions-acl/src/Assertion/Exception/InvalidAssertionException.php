<?php
namespace Laminas\Permissions\Acl\Assertion\Exception;

use Laminas\Permissions\Acl\Exception\ExceptionInterface;

class InvalidAssertionException extends \InvalidArgumentException implements ExceptionInterface
{
}
