<?php

namespace Xinax\LaravelGettext;

use Exception;
use Xinax\LaravelGettext\Adapters\AdapterInterface;
use Xinax\LaravelGettext\Config\Models\Config;
use Xinax\LaravelGettext\Exceptions\DomainBindingException;
use Xinax\LaravelGettext\Exceptions\DomainCharsetSpecificationException;
use Xinax\LaravelGettext\Exceptions\UndefinedDomainException;
use Xinax\LaravelGettext\Session\SessionHandler;

class Gettext
{
    /**
     * Config container
     * @type \Xinax\LaravelGettext\Config\Models\Config
     */
    protected $configuration;

    /**
     * Current encoding
     * @type String
     */
    protected $encoding;

    /**
     * Current locale
     * @type String
     */
    protected $locale;

    /**
     * Framework adapter
     * @type \Xinax\Adapters\LaravelAdapter
     */
    protected $adapter;

    /**
     * File system helper
     * @var FileSystem
     */
    protected $fileSystem;

    /**
     * @var String
     */
    protected $domain;

    /**
     * Domains we've bound with gettext
     *
     * @var String[]
     */
    protected $boundDomains = [];

    /**
     * @param Config $config
     * @param SessionHandler $sessionHandler
     * @param AdapterInterface $adapter
     * @param FileSystem $fileSystem
     * @throws Exceptions\LocaleNotSupportedException
     * @throws \Exception
     */
    public function __construct(
        Config $config,
        SessionHandler $sessionHandler,
        AdapterInterface $adapter,
        FileSystem $fileSystem
    ) {
        // Sets the package configuration and session handler
        $this->configuration = $config;
        $this->session = $sessionHandler;
        $this->adapter = $adapter;
        $this->fileSystem = $fileSystem;

        // General domain
        $this->domain = $this->configuration->getDomain();

        // Encoding is set from configuration
        $this->encoding = $this->configuration->getEncoding();

        // Sets defaults for boot
        $locale = $this->session->get($this->configuration->getLocale());

        $this->setLocale($locale);
    }

    /**
     * Sets the current locale code
     */
    public function setLocale($locale)
    {
        if (!$this->isLocaleSupported($locale)) {
            throw new Exceptions\LocaleNotSupportedException(
                sprintf('Locale %s is not supported', $locale)
            );
        }

        try {
            $customLocale = $this->configuration->getCustomLocale() ? "C." : $locale . ".";
            $gettextLocale = $customLocale . $this->encoding;

            // All locale functions are updated: LC_COLLATE, LC_CTYPE,
            // LC_MONETARY, LC_NUMERIC, LC_TIME and LC_MESSAGES
            putenv("LC_ALL=$gettextLocale");
            putenv("LANGUAGE=$gettextLocale");
            setlocale(LC_ALL, $gettextLocale);

            $this->locale = $locale;
            $this->session->set($locale);

            // bind to all domains
            foreach ($this->configuration->getAllDomains() as $domain) {
                $this->addDomain($domain);
            }

            // set default domain
            $this->withDefaultDomain($this->domain);

            // Laravel built-in locale
            if ($this->configuration->isSyncLaravel()) {
                $this->adapter->setLocale($locale);
            }

            return $this->getLocale();
        } catch (\Exception $e) {
            $this->locale = $this->configuration->getFallbackLocale();
            $exceptionPosition = $e->getFile() . ":" . $e->getLine();
            throw new \Exception($exceptionPosition . $e->getMessage());

        }
    }

    /**
     * Returns the current locale string identifier
     *
     * @return String
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Returns a boolean that indicates if $locale
     * is supported by configuration
     *
     * @return boolean
     */
    public function isLocaleSupported($locale)
    {
        if ($locale) {
            return in_array($locale, $this->configuration->getSupportedLocales());
        }

        return false;
    }

    /**
     * Return the current locale
     *
     * @return mixed
     */
    public function __toString()
    {
        return $this->getLocale();
    }


    /**
     * Gets the Current encoding.
     *
     * @return mixed
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Sets the Current encoding.
     *
     * @param mixed $encoding the encoding
     * @return self
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
        return $this;
    }

    private function bindToDomain($domain, $encoding, $domainPath)
    {
        if (bindtextdomain($domain, $domainPath) != realpath($domainPath)) {
            throw new DomainBindingException($domain, $domainPath);
        }

        if (bind_textdomain_codeset($domain, $encoding) != $encoding) {
            throw new DomainCharsetSpecificationException($domain, $encoding);
        }

        return $domain;
    }

    public function withDefaultDomain($domain)
    {
        $this->addDomain($domain);
        $this->domain = textdomain($domain);

        return $this;
    }

    /**
     * Adds the domain as a gettext binding
     *
     * @param   String                      $domain
     * @throws  UndefinedDomainException    If domain is not defined
     * @return  self
     */
    public function addDomain($domain)
    {
        if (in_array($domain, $this->boundDomains)) {
            // we've already bound this domain
            return $this;
        }

        $this->assertDomainIsDefined($domain);

        $customLocale = $this->configuration->getCustomLocale() ? "/" . $this->locale : "";

        $domainPath = $this->fileSystem->getDomainPath() . $customLocale;

        array_push($this->boundDomains, $this->bindToDomain($domain, $this->encoding, $domainPath));

        return $this;
    }

    /**
     * Throw an exception if we do not have a domain defined
     *
     * @param $domain
     * @throws UndefinedDomainException
     */
    private function assertDomainIsDefined($domain)
    {
        if (!in_array($domain, $this->configuration->getAllDomains())) {
            throw new UndefinedDomainException("Domain '$domain' is not registered");
        }
    }

    /**
     * Returns the current domain
     *
     * @return String
     */
    public function getDomain()
    {
        return $this->domain;
    }
}
