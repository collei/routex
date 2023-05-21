<?php
namespace Routex\Parser;

class Parser
{
	protected const ROUTEX_ROUTE = '/^[ \t]*(?P<verb>get|post|patch|put|head|options|delete|any:\S+|any)[ \t]+(?P<uri>(\/?[\w\-.]+|\/?\{\??\w+(=[^\s\/]+)?\}|\/)+)[ \t]+(?P<handler>\w+(\@\w+)?)([ \t]+([\'"]?)(?P<name>[\w\-.]+)\8)?$/m';
	protected const ROUTEX_GROUP_START = '/^[ \t]*with(([ \t]+prefix[ \t]+(?P<prefix>\S+))|([ \t]+controller[ \t]+(?P<controller>\w+))|([ \t]+name[ \t]+([\'"]?)(?P<name>[\w\-.]+)(\7))|([ \t]+middleware[ \t]+(?P<middleware>[\w\-,]+)))*/m';
	protected const ROUTEX_GROUP_END = '/^[ \t]*without(([ \t]+(prefix))|([ \t]+(controller))|([ \t]+(name))|([ \t]+(middleware)))*.*$/';
	protected const ROUTEX_COMMENTS = '/(?:\/\*[\s\S]*?\*\/)|(?:\/\/[^\r\n]*(\r\n|\n))|(?:#[^\r\n]*(\r\n|\n))/m';

	protected const ROUTEX_CONTEXT_ARGS = [
		'prefix' => 3,
		'controller' => 5,
		'name' => 7,
		'middleware' => 9
	];

	protected const ROUTEX_CURRENT_AGGREGATOR = [
		'prefix' => '/',
		'name' => '.',
	];

	protected $fileName;
	protected $context;
	
	protected $lines = [];
	protected $routexes = [];

	public function __construct($file)
	{
		$this->loadFile($file);
	}

	public function parse()
	{
		$this->parseRoutex();
		//
		return $this;
	}

	public function routes()
	{
		return $this->routexes;
	}

	protected function loadFile($file)
	{
		$this->fileName = $file;
		//
		$this->lines = $this->cleanUpComments(file_get_contents($file));
	}

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

	protected function parseRoutexStatement($line, $number)
	{
		if (preg_match(self::ROUTEX_ROUTE, $line, $args)) {
			$routex = ['verb'=>'','uri'=>'','handler'=>'','name'=>''];
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

	protected function contextReset()
	{
		$this->context = [];
		//
		foreach (self::ROUTEX_CONTEXT_ARGS as $key => $index) {
			$this->context[$key] = [];
		};
	}

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

	protected static function getLastOf($array)
	{
		return end($array);
	}

	protected function contextIncrease($args)
	{
		foreach (self::ROUTEX_CONTEXT_ARGS as $key => $index) {
			if ($val = ($args[$key] ?? null)) {
				$this->context[$key][] = $val;
			}
		}
	}

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


