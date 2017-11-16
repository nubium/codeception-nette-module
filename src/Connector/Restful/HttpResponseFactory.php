<?php
namespace Arachne\Codeception\Connector\Restful;

use Drahak\Restful\Http\ResponseFactory;

class HttpResponseFactory extends ResponseFactory
{
	public function createHttpResponse($code = null)
	{
		$response = new HttpResponse();
		return $response;
	}

	public function getCode($code = null): int
	{
		return parent::getCode($code);
	}
}
