<?php
namespace Routex\Parser;

/**
 * Embodies Token properties.
 *
 * @author Collei Inc.
 */
class Token
{
	/**
	 * @const int
	 */
	public const RT_UNKNOWN = 0; 

	/**
	 * @const int
	 */
	public const RT_NEWLINE = 1;

	/**
	 * @const int
	 */
	public const RT_COMMENTS = 2;

	/**
	 * @const int
	 */
	public const RT_COMMA = 3;

	/**
	 * @const int
	 */
	public const RT_KEYWORD_WITH = 11;

	/**
	 * @const int
	 */
	public const RT_KEYWORD_WITHOUT = 12;

	/**
	 * @const int
	 */
	public const RT_KEY_PREFIX = 13;

	/**
	 * @const int
	 */
	public const RT_KEY_NAME = 14;

	/**
	 * @const int
	 */
	public const RT_KEY_CONTROLLER = 15;

	/**
	 * @const int
	 */
	public const RT_KEY_MIDDLEWARE = 16;

	/**
	 * @const int
	 */
	public const RT_VERB = 31;

	/**
	 * @const int
	 */
	public const RT_HANDLER = 32;

	/**
	 * @const int
	 */
	public const RT_NAME = 33;

	/**
	 * @const int
	 */
	public const RT_URI = 34;

	/**
	 * @const int
	 */
	public const RT_MIDDLEWARE = 35;

	/**
	 * @const array
	 */
	public const TOKEN_NAMES = [
		self::RT_UNKNOWN => 'RT_UNKNOWN',
		self::RT_NEWLINE => 'RT_NEWLINE',
		self::RT_COMMENTS => 'RT_COMMENTS',
		self::RT_COMMA => 'RT_COMMA',
		self::RT_KEYWORD_WITH => 'RT_KEYWORD_WITH',
		self::RT_KEYWORD_WITHOUT => 'RT_KEYWORD_WITHOUT',
		self::RT_KEY_PREFIX => 'RT_KEY_PREFIX',
		self::RT_KEY_NAME => 'RT_KEY_NAME',
		self::RT_KEY_CONTROLLER => 'RT_KEY_CONTROLLER',
		self::RT_KEY_MIDDLEWARE => 'RT_KEY_MIDDLEWARE',
		self::RT_VERB => 'RT_VERB',
		self::RT_HANDLER => 'RT_HANDLER',
		self::RT_NAME => 'RT_NAME',
		self::RT_URI => 'RT_URI',
		self::RT_MIDDLEWARE => 'RT_MIDDLEWARE'
	];

	public const TOKEN_REGEX = [
		self::RT_NEWLINE => '/\r?\n/',
		self::RT_COMMENTS => '/(?:\/\*[\s\S]*?\*\/)|(?:\/\/[^\r\n]*)|(?:\#[^\r\n]*)/m',
		self::RT_COMMA => '/\s*\,\s*/',
		self::RT_KEYWORD_WITH => '/^(within|with)$/',
		self::RT_KEYWORD_WITHOUT => '/^without$/',
		self::RT_KEY_PREFIX => '/^prefix$/',
		self::RT_KEY_NAME => '/^name$/',
		self::RT_KEY_CONTROLLER => '/^controller$/',
		self::RT_KEY_MIDDLEWARE => '/^middleware$/',
		self::RT_VERB => '/^(?P<verb>(?>(?>get|post|patch|put|head|options|delete)(?>\|(?>get|post|patch|put|head|options|delete))*)|(?>get|post|patch|put|head|options|delete|any))$/',
		self::RT_URI => '/^(\/([\w\-.]+|{\??\w+(=[^}\s]+)?})(\/[\w\-.]+|\/{\??\w+(=[^}\s]+)?})*|\/)$/',
		self::RT_HANDLER => '/^(?>(?P<handler>[A-Za-z0-9_]+)(?>\@(?P<method>[A-Za-z0-9_]+))?)$/',
		self::RT_NAME => '/^("([^"\\\\]*(\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(\\\\.[^\'\\\\]*)*)\')$/',
		self::RT_MIDDLEWARE => '/^(?P<middleware>\-?[A-Za-z0-9_]+)$/',
	];

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $token;

	/**
	 * @var int
	 */
	protected $line;

	/**
	 * @var int
	 */
	protected $position;

	/**
	 * @var int
	 */
	protected $lineCount;

	/**
	 * Initializes a token.
	 *
	 * @param int $id
	 * @param string $token
	 * @return void
	 */
	public function __construct($id, $token)
	{
		$this->id($id);
		$this->token($token);
	}

	/**
	 * Retrieves properties.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get(string $name)
	{
		if (property_exists($this, $name)) {
			return $this->$name;
		}
		//
		if ('name' === $name) {
			return self::TOKEN_NAMES[$this->id] ?? self::TOKEN_NAMES[self::RT_UNKNOWN];
		}
	}

	/**
	 * Provides PHP debugger with info on the token instance.
	 *
	 * @return array
	 */
	public function __debugInfo()
	{
		return [
			'id' => $this->id,
			'token' => $this->token,
			'name' => $this->name,
			'line' => $this->line,
			'position' => $this->position
		];
	}

	/**
	 * Sets token $id.
	 *
	 * @param int $id
	 * @return $this
	 */
	public function id($id)
	{
		$this->id = $id;
		//
		return $this;
	}

	/**
	 * Sets token $token.
	 *
	 * @param string $token
	 * @return $this
	 */
	public function token($token)
	{
		$this->token = $token;
		//
		return $this;
	}

	/**
	 * Sets token $line.
	 *
	 * @param int $line
	 * @return $this
	 */
	public function line($line)
	{
		$this->line = $line;
		//
		return $this;
	}

	/**
	 * Sets token $lineCount.
	 *
	 * @param int $lineCount
	 * @return $this
	 */
	public function lineCount($lineCount)
	{
		$this->lineCount = $lineCount;
		//
		return $this;
	}

	/**
	 * Sets token $position.
	 *
	 * @param int $position
	 * @return $this
	 */
	public function position($position)
	{
		$this->position = $position;
		//
		return $this;
	}

	/**
	 * Token instance factory.
	 *
	 * @static
	 * @param int $id
	 * @param string $token
	 * @return instanceof self
	 */
	public static function make($id, $token)
	{
		return new self($id, $token);
	}
}

