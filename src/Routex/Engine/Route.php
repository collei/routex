<?php
namespace Routex\Engine;

/**
 * Embodies Route properties and operations.
 *
 * @author Collei Inc.
 */
class Route
{
	/**
	 * @const string
	 */
	public const ROUTE_VERBS = ['get','patch','post','put','delete','head','options'];

	/**
	 * @const string
	 */
	protected const PARAM_SOURCE = '/\{(\w+)(\?)?\}/';

	/**
	 * @const string
	 */
	protected const PARAM_COMPILED = '(?P<name>[^\s?#/]+)';

	/**
	 * @const string
	 */
	protected const URI_COMPILED_TAIL = '(?>\?(?P<__querystring>[^\s#]+)?)?(?>#(?P<__hash>[^\s]+)?)?';

	/**
	 * @property string
	 */
	protected $name;

	/**
	 * @property array
	 */
	protected $verbs;

	/**
	 * @property string
	 */
	protected $uri;

	/**
	 * @property string
	 */
	protected $uriRegex;

	/**
	 * @property mixed
	 */
	protected $handler;

	/**
	 * @property array
	 */
	protected $middleware;

	/**
	 * Compiles the Routex uri format to its regex capturer form.
	 *
	 * @static
	 * @param string $uri
	 * @return string
	 */
	protected static function compileUri($uri)
	{
		$porr = '/(?>\?(?P<querystring>[^\s#]*))?(?>#(?P<hash>[^\s]*))?/';
		//
		// remove duplicate slashes
		$uri = str_replace(['////','///','//'], '/', $uri);
		//
		// recover the two slash for the protocol part
		$uri = preg_replace('/(https?):\//', '$1://', $uri);
		//
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

	/**
	 * Matches the given $uri with the $pattern and collects parameters to
	 * $parameters, if any.
	 *
	 * @static
	 * @param string $pattern
	 * @param string $uri
	 * @param array &$parameters
	 * @return bool
	 */
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

	/**
	 * Transforms a multi-dimensional array in a one-dimensional
	 * array with all middleware names.
	 *
	 * @static
	 * @param array $middleware
	 * @return array
	 */
	protected static function normalizeMiddleware(array $middleware)
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

	/**
	 * Instantiate me.
	 *
	 * @static
	 * @param string $name
	 * @param string|array $verb
	 * @param string $uri
	 * @param string $handler
	 * @param string|array ...$middleware
	 * @return void
	 */
	public function __construct($name, $verb, $uri, $handler, ...$middleware)
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

	/**
	 * Return the value under the given name, if any.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		if (property_exists($this, $name)) {
			return $this->$name;
		}
	}

	/**
	 * Tells if the route matches the given verb.
	 *
	 * @param string $verb
	 * @return bool
	 */
	public function forVerb($verb)
	{
		return in_array(strtolower($verb), $this->verbs, true);
	}

	/**
	 * Tells if the route matches the given uri and verb.
	 * If it matches, gathers data from it and returns through
	 * the third parameter (passed as reference).
	 *
	 * @param string $verb
	 * @param string $request
	 * @param array &$data
	 * @return bool
	 */
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
