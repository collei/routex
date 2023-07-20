<?php
namespace Routex\Engine;

use Routex\Parser\Parser;
use Routex\Parser\SyntaxAnalyzer;
use Routex\Parser\Tokenizer;

/**
 * Embodies Route file loader with parsing and syntax analysis.
 *
 * @author Collei Inc.
 */
class RouteLoader
{
	/**
	 * @var string
	 */
	protected $fileName;

	/**
	 * @var array
	 */
	protected $routes;

	/**
	 * @var array
	 */
	protected $errors;

	/**
	 * Initializes the loader.
	 *
	 * @return void
	 */
	public function __construct()
	{
		//
	}

	/**
	 * Loads souce from file and fetches the routes from it, if any,
	 * provided there are no syntax errors.
	 *
	 * @param string $fileName
	 * @return $this
	 */
	public function loadFile(string $fileName)
	{
		$this->fileName = $fileName;
		//
		return $this->loadSourceIntoRoutes();
	}

	/**
	 * Returns the file name.
	 *
	 * @return string
	 */
	public function getFileName()
	{
		return $this->fileName;
	}

	/**
	 * Returns the loaded routes.
	 *
	 * @return array
	 */
	public function getRoutes()
	{
		return $this->routes;
	}

	/**
	 * Returns the errors, if any.
	 *
	 * @return array
	 */
	public function getErrors()
	{
		if ($this->hasErrors()) {
			return $this->errors;
		}
		//
		return null;
	}

	/**
	 * Returns if there are errors.
	 *
	 * @return bool
	 */
	public function hasErrors()
	{
		return ! empty($this->errors);
	}

	/**
	 * Executes routex file loading and parsing.
	 *
	 * @return $this
	 */
	protected function loadSourceIntoRoutes()
	{
		$this->routes = $this->errors = [];
		//
		$tokens = Tokenizer::tokenize(
			$source = file_get_contents($this->fileName)
		);
		//
		$lines = SyntaxAnalyzer::breakInLines($tokens);
		//
		if (SyntaxAnalyzer::syntaxCheck($lines, $errors)) {
			$cleanedSource = SyntaxAnalyzer::sourceFromLines($lines);
			$routes = new Parser($example);
			//
			$this->routes = $routes->parse()->routesAsEngineRoutes();
		} else {
			$this->errors = $errors;
		}
		//
		return $this;
	}
}

