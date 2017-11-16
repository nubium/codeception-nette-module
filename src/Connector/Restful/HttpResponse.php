<?php
namespace Arachne\Codeception\Connector\Restful;

use Arachne\Codeception\Http\Response;

class HttpResponse extends Response
{
	protected $code = null;

	public function reset()
	{
		parent::reset();
		$this->code = null;
	}
}
