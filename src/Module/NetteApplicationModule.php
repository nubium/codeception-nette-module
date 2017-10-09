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
use Codeception\Lib\Framework;
use Codeception\Module\Nette;
use Codeception\Scenario;
use Codeception\Test\Interfaces\ScenarioDriven;
use Codeception\TestInterface;
use Nette\Caching\Storages\FileStorage;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Loaders\RobotLoader;
use Tests\Functional\Support\SignedNetteConnector;

/**
 * @author Jáchym Toušek <enumag@gmail.com>
 */
class NetteApplicationModule extends Framework
{
    protected $config = [
        'followRedirects' => true,
    ];

    protected $requiredFields = ['internalDomains'];

    /**
     * @var string
     */
    private $path;

    public function _initialize()
    {
        parent::_initialize();

        $this->internalDomains = $this->config['internalDomains'];
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
            return $this->getModule(NetteDIModule::class)->getContainer($test);
        });
        $this->client->followRedirects($this->config['followRedirects']);

        parent::_before($test);
    }

    protected function createNetteConnector():NetteConnector {
        return new NetteConnector();
    }

    public function _after(TestInterface $test)
    {
        parent::_after($test);

        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];
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
}
