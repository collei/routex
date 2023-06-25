<?php
namespace Routex\Engine;

class Route
{
	public const ROUTE_VERBS = ['get','patch','post','put','delete','head','options'];

	protected const PARAM_SOURCE = '/\{(\w+)(\?)?\}/';
	protected const PARAM_COMPILED = '(?P<name>[^\s?#/]+)';

	protected const URI_COMPILED_TAIL = '(?>\?(?P<__querystring>[^\s#]+)?)?(?>#(?P<__hash>[^\s]+)?)?';

	protected $name;
	protected $verbs;
	protected $uri;
	protected $uriRegex;
	protected $handler;
	protected $middleware;

	protected static function compileUri($uri)
	{
		$porr = '/(?>\?(?P<querystring>[^\s#]*))?(?>#(?P<hash>[^\s]*))?/';

		$uri = str_replace(['////','///','//'], '/', $uri);
		$replacer = array('from' => [], 'to' => []);
		//
		if (preg_match_all(self::PARAM_SOURCE, $uri, $matches, PREG_SET_ORDER) > 0) {
			foreach ($matches as $match) {
				$replacer['from'][] = $match[0];
				$replacer['to'][] = str_replace('name', $match[1], self::PARAM_COMPILED) .
					(('?' == ($match[2] ?? '')) ? '?' : '');
			}
		}
		//
		return '/' . str_replace(
			[')?/(','/(','/'], [')?/?(','/?(','\/'], str_replace($replacer['from'], $replacer['to'], $uri)
		) . self::URI_COMPILED_TAIL . '/';
	}

	protected static function matchUri($pattern, $uri, &$parameters)
	{
		$parameters = [];
		//
		if (1 == preg_match($pattern, $uri, $data)) {
			foreach ($data as $key => $value) {
				if ('__querystring' == $key) {
					parse_str($value, $values);
					//
					$parameters[$key] = $values;
				} elseif (is_string($key)) {
					$parameters[$key] = $value;
				}
			}
			//
			return true;
		}
		//
		return false;
	}

	protected static function normalizeMiddleware($middleware)
	{
		$normalized = [];
		//
		foreach ($middleware as $key => $value) {
			if (is_array($value)) {
				$normalized = $normalized + self::normalizeMiddleware($value);
			} else {
				$normalized[] = $value;
			}
		}
		//
		return $normalized;
	}

	public function __construct($name, $verb, $uri, $handler, $middleware = [])
	{
		$this->name = $name;
		$this->uri = $uri;
		$this->handler = $handler;
		$this->middleware = self::normalizeMiddleware($middleware);
		//
		$this->uriRegex = self::compileUri($uri);
		$this->uriParameters = [];
		//
		if (is_array($verb)) {
			$this->verbs = $verb;
		} else {
			$verb = strtolower($verb);
			//
			$this->verbs = ('any' == $verb || '*' == $verb) ? self::ROUTE_VERBS : array($verb);
		}
	}

	public function __get($name)
	{
		if (property_exists($this, $name)) {
			return $this->$name;
		}
	}

	public function forVerb($verb)
	{
		return in_array(strtolower($verb), $this->verbs, true);
	}

	public function matches($verb, $request, &$data)
	{
		if (! $this->forVerb($verb)) {
			return false;
		}
		//
		$data = [];
		//
		return self::matchUri($this->uriRegex, $request, $data);
	}

}
