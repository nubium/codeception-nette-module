<?php

/*
 * This file is part of the Arachne
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Codeception\Module;

use Api2\Configurator\ConfiguratorFactory;
use Codeception\Module;
use Codeception\Step;
use Codeception\TestInterface;
use Nette\Caching\Storages\IJournal;
use Nette\Caching\Storages\SQLiteJournal;
use Nette\Configurator;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Nette\Http\Session;
use Nette\Utils\FileSystem;
use Nodus\Diagnostics\Debugger;
use ReflectionProperty;

class NetteDIModule extends Module
{
    /**
     * @var callable[]
     */
    public $onCreateContainer = [];

    protected $config = [
        'configFiles' => [],
        'appDir' => null,
        'logDir' => null,
        'wwwDir' => null,
        'debugMode' => null,
        'configurator' => Configurator::class,
        'removeDefaultExtensions' => false,
    ];

    protected $requiredFields = [
        'tempDir',
    ];

    /**
     * @var string
     */
    private $path;

    /**
     * @var array
     */
    private $configFiles;

    /**
     * @var Container
     */
    private $container;

    public function _beforeSuite($settings = [])
    {
        $this->path = $settings['path'];
    }

    public function _before(TestInterface $test)
    {
        $tempDir = $this->path.'/'.$this->config['tempDir'];
        FileSystem::delete(realpath($tempDir));
        FileSystem::createDir($tempDir);
    }

    public function _afterStep(Step $step)
    {
        if (is_null($this->container)) {
            return;
        }

        $this->container = null;
    }

    public function _after(TestInterface $test)
    {
        if ($this->container) {
            try {
                $session = $this->container->getByType(Session::class);
                if ($session->isStarted()) {
                    $session->destroy();
                }
            } catch (MissingServiceException $e) {
            }

            try {
                $journal = $this->container->getByType(IJournal::class);
                if ($journal instanceof SQLiteJournal) {
                    $property = new ReflectionProperty(SQLiteJournal::class, 'pdo');
                    $property->setAccessible(true);
                    $property->setValue($journal, null);
                }
            } catch (MissingServiceException $e) {
            }

//            FileSystem::delete(realpath($this->container->getParameters()['tempDir']));
        }
    }

    public function useConfigFiles(array $configFiles)
    {
        if ($this->container) {
            $this->fail('Can\'t set configFiles after the container is created.');
        }
        $this->configFiles = $configFiles;
    }

    /**
     * @return Container
     */
    public function getContainer(TestInterface $test):Container
    {
        if (!$this->container) {
            $this->createContainer($test);
        }

        return $this->container;
    }

    /**
     * @param string $service
     *
     * @return object
     */
    public function grabService($service)
    {
        try {
            return $this->getContainer()->getByType($service);
        } catch (MissingServiceException $e) {
            $this->fail($e->getMessage());
        }
    }

    public function createContainer(TestInterface $test): Container
    {
        $config = $test->getMetadata()->getParam('config');

        $configurator = $this->createConfigurator($test);
        if ($this->config['removeDefaultExtensions']) {
            $configurator->defaultExtensions = [
                'extensions' => 'Nette\DI\Extensions\ExtensionsExtension',
            ];
        }

        if ($this->config['logDir']) {
            $logDir = $this->path.'/'.$this->config['logDir'];
            FileSystem::createDir($logDir);
            $configurator->enableDebugger($logDir);
        }

//        $tempDir = $this->path.'/'.$this->config['tempDir'];
//        FileSystem::delete($tempDir);
//        FileSystem::createDir($tempDir);
//        $configurator->setTempDirectory($tempDir);

        if ($this->config['debugMode'] !== null) {
            $configurator->setDebugMode($this->config['debugMode']);
        }

        $configFiles = is_array($this->configFiles) ? $this->configFiles : $this->config['configFiles'];
        foreach ($configFiles as $file) {
            $configurator->addConfig(FileSystem::isAbsolute($file) ? $file : $this->path.'/'.$file);
        }

        // specific test custom config
        if (is_array($config)) {
            foreach ($config as $configItem) {
                if ($configItem === "") {
                    $filename = substr($test->getMetadata()->getFilename(), 0, -4).'.neon';
                    $configurator->addConfig($filename);
                } else {
                    $items = explode(' ', $configItem);
                    $filename = dirname($test->getMetadata()->getFilename()) . '/' . array_pop($items);
                    $configurator->addConfig($filename);
                }
            }
        }

        $this->container = $configurator->createContainer();

        foreach ($this->onCreateContainer as $callback) {
            $callback($this->container);
        }

        return $this->container;
    }

    private function createConfigurator(TestInterface $test): Configurator
    {
        $appAnnotation = $test->getMetadata()->getParam('app');
        $application = is_array($appAnnotation) ? $appAnnotation[0] : 'ulozto';

        switch ($application) {

            case 'pornfile':
                $configurator = new \PornFile\Application\Configurator(true, false);
                \Nette\Utils\FileSystem::createDir(LOG_DIR . '/pornfile');
                $configurator->enableDebugger(LOG_DIR . '/pornfile');
                $configurator->addParameters([
                    'appDir' => WWW_DIR . '/PornFile/App',
                    'wwwDir' => WWW_DIR,
                ]);
                break;

            case 'admin':
                $configurator = new \Admin\Application\Configurator(true, false);
                \Nette\Utils\FileSystem::createDir(LOG_DIR . '/admin');
                $configurator->enableDebugger(LOG_DIR . '/admin');
                $configurator->addParameters([
                    'appDir' => WWW_DIR . '/Admin/App',
                    'wwwDir' => WWW_DIR,
                ]);
                break;

            case 'api':
                $configurator = new \Api\Application\Configurator(true, false);
                \Nette\Utils\FileSystem::createDir(LOG_DIR . '/api');
                $configurator->enableDebugger(LOG_DIR . '/api');
                $configurator->addParameters([
                    'appDir' => WWW_DIR . '/Api/App',
                    'wwwDir' => WWW_DIR,
                ]);
                break;

            case 'api2':
                \Nette\Utils\FileSystem::createDir(LOG_DIR . '/api2');
                $configuratorFactory = new ConfiguratorFactory();
                $configurator = $configuratorFactory->createForDocker('test', LOG_DIR . '/api2');
                $configurator->enableDebugger(LOG_DIR . '/api2');
                break;

            case '':
            case 'ulozto':
                $configurator = new \Ulozto\Application\Configurator(true, false);
                \Nette\Utils\FileSystem::createDir(LOG_DIR . '/ulozto');
                $configurator->enableDebugger(LOG_DIR . '/ulozto');
                $configurator->addParameters([
                    'appDir' => WWW_DIR . '/Ulozto/App',
                    'wwwDir' => WWW_DIR,
                ]);
            break;

            default:
                throw new \InvalidArgumentException('Unknown application code: '.$application. '. Allowed values are ulozto, pornfile, admin, api.');
        }

        error_reporting(E_ALL ^ E_USER_DEPRECATED);
        return $configurator;
    }

}
