<?php

/*
 * This file is part of the Arachne
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Codeception\Module;

use Arachne\Codeception\Connector\NetteConnector;
use Codeception\Exception\ExternalUrlException;
use Codeception\Lib\Framework;
use Codeception\Module\Nette;
use Codeception\Scenario;
use Codeception\Step;
use Codeception\Test\Interfaces\ScenarioDriven;
use Codeception\TestInterface;
use Nette\Caching\Storages\FileStorage;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Loaders\RobotLoader;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Functional\Support\SignedNetteConnector;

/**
 * @author Jáchym Toušek <enumag@gmail.com>
 */
class NetteApplicationModule extends Framework
{
    protected $config = [
        'followRedirects' => true,
    ];

    protected $requiredFields = ['internalDomains', 'server'];

    protected $overridenServices = [];

    protected $activeApplication = null;

    /**
     * @var string
     */
    private $path;

    private $internalDomainsByApp = [];

    /**
     * @var mixed[]
     */
    private $server = [];

    public function _initialize()
    {
        parent::_initialize();

        $this->internalDomainsByApp = $this->config['internalDomains'];
        $this->server = $this->config['server'];
    }

    public function _beforeStep(Step $step)
    {
        parent::_beforeStep($step);

        $this->client->setOverridenServices($this->overridenServices);
    }

    public function _beforeSuite($settings = [])
    {
        $this->path = $settings['path'];
    }

    public function _before(TestInterface $test)
    {
        $this->configFiles = null;
        $this->client = $this->createNetteConnector();
        $this->client->setContainerAccessor(function () use ($test) {
            return $this->getModule(NetteDIModule::class)->createContainer($test);
        });
        $this->client->followRedirects($this->config['followRedirects']);

        $appAnnotation = $test->getMetadata()->getParam('app');
        $this->activeApplication = is_array($appAnnotation) ? $appAnnotation[0] : 'ulozto';

        // each testcase can have different internal domains according to its @app
        // @see self::redirectIfNecessary
        $this->internalDomains = null;

        parent::_before($test);
    }

    protected function createNetteConnector():NetteConnector {
        return new NetteConnector($this->server);
    }

    public function _after(TestInterface $test)
    {
        parent::_after($test);

        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];

        $this->overridenServices = [];
    }

    /**
     * @param bool $followRedirects
     */
    public function followRedirects($followRedirects)
    {
        $this->client->followRedirects($followRedirects);
    }

    /**
     * @param string $url
     */
    public function seeRedirectTo($url)
    {
        if ($this->client->isFollowingRedirects()) {
            $this->fail('Method seeRedirectTo only works when followRedirects option is disabled');
        }
        $request = $this->getModule(NetteDIModule::class)->grabService(IRequest::class);
        $response = $this->getModule(NetteDIModule::class)->grabService(IResponse::class);
        if ($response->getHeader('Location') !== $request->getUrl()->getHostUrl().$url && $response->getHeader('Location') !== $url) {
            $this->fail('Couldn\'t confirm redirect target to be "'.$url.'", Location header contains "'.$response->getHeader('Location').'".');
        }
    }

    public function debugContent()
    {
        $this->debugSection('Content', $this->client->getInternalResponse()->getContent());
    }

    /**
     * @return string
     */
    public function grabResponseContent()
    {
        return $this->client->getInternalResponse()->getContent();
    }

    /**
     * @param string $name
     * @param mixed|null $fakeService
     */
    public function overrideContainerService($name, $fakeService = null)
    {
        if (is_null($fakeService) && isset($this->services[$name])) {
            unset($this->overridenServices[$name]);

            return;
        }
        $this->overridenServices[$name] = $fakeService;
    }

    protected function getInternalDomains(): array
    {
        if (!isset($this->activeApplication) || !isset($this->internalDomainsByApp[$this->activeApplication])) {
            throw new \RuntimeException('Unknown or incorrectly set active application ('.$this->activeApplication.').');
        }
        return $this->internalDomainsByApp[$this->activeApplication];
    }

    /**
     * @param Crawler $result
     * @param int $maxRedirects
     * @param int $redirectCount
     * @return mixed
     * @throws ExternalUrlException
     */
    protected function redirectIfNecessary($result, $maxRedirects, $redirectCount)
    {
        $status = $this->client->getInternalResponse()->getStatus();
        $location = $this->client->getInternalResponse()->getHeader('Location');
        if ($status >= 300 && $status < 400) {
            if (preg_match('#^(//|https?://(?!localhost))#', $location)) {
                $hostname = parse_url($location, PHP_URL_HOST);
                if (!$this->isInternalDomain($hostname)) {
                    throw new ExternalUrlException(get_class($this) . " can't open external URL: " . $location);
                }
            }
        }
        return parent::redirectIfNecessary($result, $maxRedirects, $redirectCount);
    }
}
