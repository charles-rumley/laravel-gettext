<?php

namespace Xinax\LaravelGettext\Exceptions;

use Exception;

class DomainBindingException extends Exception
{
    public function __construct($domain, $domainPath, Exception $previous = null)
    {
        parent::__construct("Binding to domain $domain at path $domainPath has failed", 0, $previous);
    }
}
