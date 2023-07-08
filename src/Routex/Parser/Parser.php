<?php
namespace Routex\Parser;

use Routex\Engine\Route;
use Closure;

class Parser
{
	/**
	 * @const string
	 */
	protected const ROUTEX_ROUTE = '/^[ \t]*(?P<verb>(?>(?>get|post|patch|put|head|options|delete)(?>\|(?>get|post|patch|put|head|options|delete))*)|(?>get|post|patch|put|head|options|delete|any))[ \t]+(?P<uri>(\/?[\w\-.]+|\/?\{\??\w+(=[^\s\/]+)?\}|\/)+)[ \t]+(?P<handler>\w+(\@\w+)?)([ \t]+([\'"]?)(?P<name>[\w\-.]+)\8)?([ \t]+middleware[ \t]+(?P<middleware>(?>\-?\w+(?>[ \t]*\,[ \t]*\-?\w+)*)))?$/m';

	/**
	 * @const string
	 */
	protected const ROUTEX_GROUP_START = '/^[ \t]*with(([ \t]+prefix[ \t]+(?P<prefix>\S+))|([ \t]+controller[ \t]+(?P<controller>\w+))|([ \t]+name[ \t]+([\'"]?)(?P<name>[\w\-.]+)(\7))|([ \t]+middleware[ \t]+(?P<middleware>(?>\-?\w+(?>[ \t]*\,[ \t]*\-?\w+)*))))*/m';

	/**
	 * @const string
	 */
	protected const ROUTEX_GROUP_END = '/^[ \t]*without(([ \t]+(prefix))|([ \t]+(controller))|([ \t]+(name))|([ \t]+(middleware)))*.*$/';

	/**
	 * @const string
	 */
	protected const ROUTEX_COMMENTS = '/(?:\/\*[\s\S]*?\*\/)|(?:\/\/[^\r\n]*(\r\n|\n))|(?:#[^\r\n]*(\r\n|\n))/m';

	/**
	 * @const array
	 */
	protected const ROUTEX_CONTEXT_ARGS = [
		'prefix' => 3,
		'controller' => 5,
		'name' => 7,
		'middleware' => 9
	];

	/**
	 * @const array
	 */
	protected const ROUTEX_CURRENT_AGGREGATOR = [
		'prefix' => '/',
		'name' => '.',
		'middleware' => ',',
	];

	/**
	 * @var string
	 */
	protected $fileName;

	/**
	 * @var array
	 */
	protected $context = [];
	
	/**
	 * @var array
	 */
	protected $lines = [];

	/**
	 * @var array
	 */
	protected $routexes = [];

	/**
	 * Instantiate me.
	 *
	 * @param string $file
	 * @return void
	 */
	public function __construct($file)
	{
		$this->loadFile($file);
	}

	/**
	 * Executes parsing.
	 *
	 * @return this
	 */
	public function parse()
	{
		$this->parseRoutex();
		//
		return $this;
	}

	/**
	 * Obtain the route list as array of records (asssociative arrays).
	 *
	 * @return array
	 */
	public function routes()
	{
		return $this->routexes;
	}

	/**
	 * Obtain the route list as array of routes.
	 *
	 * @return array
	 */
	public function routesAsEngineRoutes()
	{
		$engineRoutes = [];
		//
		foreach ($this->routexes as $routex) {
			$middleware = is_array($routex['middleware'])
				? $routex['middleware']
				: array($routex['middleware']);
			//
			$engineRoutes[] = new Route(
				$routex['name'],
				explode('|', $routex['verb']),
				$routex['uri'],
				$routex['handler'],
				...$middleware
			);
		}
		//
		return $engineRoutes;
	}

	/**
	 * Obtain the route list as array of objects modelated according
	 * the passed $converter callback.
	 *
	 * @param \Closure $converter
	 * @return array
	 */
	public function routesAs(Closure $converter)
	{
		$results = [];
		//
		foreach ($this->routexes as $key => $route) {
			$results[] = $converter($route);
		}
		//
		return $results;
	}

	/**
	 * Loads the text from the specified file path.
	 *
	 * @param string $file
	 * @return void
	 */
	protected function loadFile($file)
	{
		$this->fileName = $file;
		//
		$this->lines = $this->cleanUpComments(file_get_contents($file));
	}

	/**
	 * Removes comments from the source.
	 *
	 * @param string $source
	 * @return void
	 */
	protected function cleanUpComments($source)
	{
		$text = preg_replace(
			self::ROUTEX_COMMENTS,
			PHP_EOL,
			$source
		);
		//
		$text = str_replace(["\r\n","\n","\r"], "\n", $text);
		$text = str_replace("\n", PHP_EOL, $text);
		//
		return array_filter(explode(PHP_EOL, $text));
	}

	/**
	 * Executes the actual parsing of the source, line by line.
	 *
	 * @return void
	 */
	protected function parseRoutex()
	{
		$this->routexes = [];
		//
		$this->contextReset();
		//
		foreach ($this->lines as $number => $line) {
			$this->parseRoutexStatement(trim($line), 1 + $number);
		}
	}

	/**
	 * Parses the given source line.
	 *
	 * @param string $line
	 * @param int $number
	 * @return void
	 */
	protected function parseRoutexStatement($line, $number)
	{
		if (preg_match(self::ROUTEX_ROUTE, $line, $args)) {
			$routex = ['verb'=>'','uri'=>'','handler'=>'','name'=>'','middleware'=>[]];
			$current = $this->contextCurrent();
			//
			foreach ($routex as $key => $value) {
				if ('uri' == $key) {
					$glue = self::ROUTEX_CURRENT_AGGREGATOR['prefix'];
					$prefix = $current['prefix'];
					$uri = (empty($prefix) ? '' : ($prefix.$glue)).($args[$key] ?? '');
					$routex[$key] = str_replace($glue.$glue, $glue, $uri);
				} elseif ('handler' == $key) {
					$controller = $current['controller'];
					$handler = empty($controller)
						? ($args[$key] ?? '')
						: (isset($args[$key]) ? ($controller.'@'.$args[$key]) : $controller);
					$routex[$key] = $handler;
				} elseif ('middleware' == $key) {
					$middleware = explode(',', str_replace(
						[' ','	'], '', $current['middleware'] . (isset($args[$key]) ? ','.$args[$key] : '')
					));
					$toBeRemoved = [];
					foreach ($middleware as $item) {
						if (substr($item, 0, 1) === '-') {
							$toBeRemoved[] = $item;
							$toBeRemoved[] = substr($item, 1);
						}
					}
					$routex[$key][] = array_filter($middleware, function($item) use ($toBeRemoved) {
						return ! in_array($item, $toBeRemoved, true);
					});
				} elseif ('name' == $key) {
					$glue = self::ROUTEX_CURRENT_AGGREGATOR['name'];
					$name = $current[$key];
					$routex[$key] = (empty($name) ? '' : ($name.$glue)).($args[$key] ?? '');
				} else {
					$routex[$key] = $args[$key] ?? $current[$key] ?? '';
				}
			}
			//
			$this->routexes[] = $routex;
			//
			return;
		}
		//
		if (preg_match(self::ROUTEX_GROUP_END, $line, $args)) {
			$this->contextDecrease($args);
			//
			return;
		}
		//
		if (preg_match(self::ROUTEX_GROUP_START, $line, $args)) {
			$this->contextIncrease($args);
			//
			return;
		}
		//
		if (!empty(trim($line))) {
			$this->routexes[] = [
				'error' => [
					'description' => 'Invalid statement',
					'file' => $this->fileName,
					'line' => $number,
					'source' => $line
				]
			];
		}
	}

	/**
	 * Reset the context.
	 *
	 * @return void
	 */
	protected function contextReset()
	{
		$this->context = [];
		//
		foreach (self::ROUTEX_CONTEXT_ARGS as $key => $index) {
			$this->context[$key] = [];
		};
	}

	/**
	 * Gather the current context.
	 *
	 * @return array
	 */
	protected function contextCurrent()
	{
		$current = [];
		//
		foreach (self::ROUTEX_CONTEXT_ARGS as $key => $index) {
			if ($glue = (self::ROUTEX_CURRENT_AGGREGATOR[$key] ?? null)) {
				$current[$key] = implode($glue, $this->context[$key]);
			} else {
				$current[$key] = self::getLastOf($this->context[$key]);
			}
		};
		//
		return $current;
	}

	/**
	 * Returns the last array element without modifying the array itself.
	 *
	 * @return mixed
	 */
	protected static function getLastOf($array)
	{
		return end($array);
	}

	/**
	 * Increase the context.
	 *
	 * @param mixed args
	 * @return void
	 */
	protected function contextIncrease($args)
	{
		foreach (self::ROUTEX_CONTEXT_ARGS as $key => $index) {
			if ($val = ($args[$key] ?? null)) {
				$this->context[$key][] = $val;
			}
		}
	}

	/**
	 * Decrease the context.
	 *
	 * @param mixed args
	 * @return void
	 */
	protected function contextDecrease($args)
	{
		$removed = [];
		//
		foreach (self::ROUTEX_CONTEXT_ARGS as $key => $index) {
			if ($val = ($args[$index] ?? null)) {
				$removed[] = $key;
				array_pop($this->context[$key]);
			}
		}
		//
		if (empty($removed)) {
			foreach (self::ROUTEX_CONTEXT_ARGS as $key => $index) {
				array_pop($this->context[$key]);
			}
		}
	}

}


