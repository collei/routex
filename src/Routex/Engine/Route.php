<?php
namespace Routex\Engine;

class Route
{
	public const ROUTE_VERBS = ['get','patch','post','put','delete','head','options'];

	protected $name;
	protected $verb;
	protected $uri;
	protected $compiledUri;
	protected $handler;
	protected $middleware;

	public static function compileUri($uri)
	{
		$uri = str_replace(['////','///','//'], '/', $uri);
		$param_source = '/\{(\w+)(\?)?\}/';
		$param_compiled = '(?P<name>[^\s?#/]+)';
		$replacer = array('from' => [], 'to' => []);
		//
		if (preg_match_all($param_source, $uri, $matches, PREG_SET_ORDER) > 0) {
			foreach ($matches as $match) {
				$replacer['from'][] = $match[0];
				$replacer['to'][] = str_replace('name', $match[1], $param_compiled) .
					(('?' == ($match[2] ?? '')) ? '?' : '');
			}
		}
		//
		return str_replace(
			')?/(', ')?/?(', str_replace($replacer['from'], $replacer['to'], $uri)
		);
	}

	public function __construct($name, $verb, $uri, $handler)
	{
		$this->name = $name;
		$this->uri = $uri;
		$this->handler = $handler;
		//
		$this->compiledUri = self::compileUri($uri);
		$this->uriParameters = [];
		//
		if (is_array($verb)) {
			$this->verb = $verb;
		} else {
			$verb = strtolower($verb);
			//
			$this->verb = ('any' == $verb || '*' == $verb) ? self::ROUTE_VERBS : array($verb);
		}
	}

	public function __get($name)
	{
		if (property_exists($this, $name)) {
			return $this->$name;
		}
	}

	public function addMiddleware($middleware)
	{
		if (! in_array($middleware, $this->middleware, true)) {
			$this->middleware[] = $middleware;
		}
		//
		return $this;
	}

	public function prependMiddleware($middleware)
	{
		if (! in_array($middleware, $this->middleware, true)) {
			array_unshift($this->middleware, $middleware);
		}
		//
		return $this;
	}

	public function matches($request, $verb = null)
	{

	}

}
