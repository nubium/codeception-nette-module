<?php

/*
 * This file is part of the Arachne
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Codeception\Connector;

use Arachne\Codeception\Http\Request as HttpRequest;
use Arachne\Codeception\Http\Response as HttpResponse;
use Exception;
use Nette\Application\Application;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

/**
 * @author Jáchym Toušek <enumag@gmail.com>
 */
class NetteConnector extends Client
{
    /**
     * @var callable
     */
    protected $containerAccessor;

    public function setContainerAccessor(callable $containerAccessor)
    {
        $this->containerAccessor = $containerAccessor;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function doRequest($request)
    {
        $_COOKIE = $request->getCookies();
        $_SERVER = $request->getServer();
        $_FILES = $request->getFiles();

        $_SERVER['HTTP_HOST'] = parse_url($request->getUri(), PHP_URL_HOST);
        $_SERVER['REQUEST_METHOD'] = $method = strtoupper($request->getMethod());
        $_SERVER['REQUEST_URI'] = preg_replace('~https?://[^/]+~', '', $request->getUri());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        \Ulozto\Files\FilesSimpleModel::$files = [];
        \Ulozto\Files\FoldersSimpleModel::$folders = [
            'users' => [],
        ];
        \Nodus\Live\Models\LiveSheetModel::$videos = [];

        if ($method === 'HEAD' || $method === 'GET') {
            $_GET = $request->getParameters();
            $_POST = [];
        } else {
            $_GET = [];
            $_POST = $request->getParameters();
        }

        $container = call_user_func($this->containerAccessor);

        $httpRequest = $container->getByType(IRequest::class);
        $httpResponse = $container->getByType(IResponse::class);
        if (!$httpRequest instanceof HttpRequest || !$httpResponse instanceof HttpResponse) {
            throw new Exception('Arachne\Codeception\DI\HttpExtension is not used or conflicts with another extension.');
        }
        $httpRequest->reset();
        $httpResponse->reset();

        try {
            ob_start();
            $container->getByType(Application::class)->run();
            $content = ob_get_clean();
        } catch (Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $code = $httpResponse->getCode();
        $headers = $httpResponse->getHeaders();

        return new Response($content, $code, $headers);
    }
}
