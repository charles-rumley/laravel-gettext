<?php

namespace Xinax\LaravelGettext\Exceptions;

use Exception;

class DomainCharsetSpecificationException extends Exception
{
    public function __construct($domain, $encoding, Exception $previous = null)
    {
        parent::__construct("Specifying charset $encoding for domain $domain has failed", 0, $previous);
    }
}
