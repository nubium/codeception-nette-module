<?php

/*
 * This file is part of the Arachne
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Codeception\DI;

use Arachne\Codeception\Connector\Restful\HttpResponseFactory;
use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\Http\IResponse;
use Nette\PhpGenerator\ClassType;

/**
 * @author Jáchym Toušek <enumag@gmail.com>
 */
class HttpExtension extends CompilerExtension
{
    protected $defaults = [
        'drahakConnector' => false,
    ];

    public function loadConfiguration()
    {
        $this->validateConfig($this->defaults);
    }

    public function beforeCompile()
    {
        $config = $this->config;

        $builder = $this->getContainerBuilder();

        $request = $builder->getByType('Nette\Http\IRequest') ?: 'httpRequest';
        if ($builder->hasDefinition($request)) {
            $builder->getDefinition($request)
                ->setClass('Nette\Http\Request')
                ->setFactory('Arachne\Codeception\Http\Request');
        }

        $response = $builder->getByType('Nette\Http\IResponse') ?: 'httpResponse';
        if ($builder->hasDefinition($response)) {
            if (!$config['drahakConnector']) {
                $builder->getDefinition($response)
                    ->setClass('Nette\Http\IResponse')
                    ->setFactory('Arachne\Codeception\Http\Response');
            } else {
                $builder->addDefinition($this->prefix('httpResponseFactory'))
                    ->setClass(HttpResponseFactory::class);
                $builder->getDefinition('httpResponse')
                    ->setClass('Nette\Http\IResponse')
                    ->setFactory($this->prefix('@httpResponseFactory') . '::createHttpResponse');
            }
        }
    }

    public function afterCompile(ClassType $class)
    {
        $config = $this->config;

        if (!$config['drahakConnector']) {
            return;
        }

        $initialize = $class->getMethod('initialize');
        $builder = $this->getContainerBuilder();

        $initialize->addBody(sprintf('$response = $this->getByType(%s::class);', IResponse::class));
        $initialize->addBody(sprintf('$responseFactory = $this->getByType(%s::class);', HttpResponseFactory::class));

        $initialize->addBody($builder->formatPhp(
            sprintf(
                '$this->getByType("%s")->onResponse[] = function ($app) use ($response, $responseFactory) { $response->setCode($responseFactory->getCode($response->getCode())); };',
                Application::class, IResponse::class, HttpResponseFactory::class
            ),
            []
        ));
    }

}
