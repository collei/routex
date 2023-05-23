<?php
namespace Routex\Parser;

class Token
{
	public const RT_UNKNOWN = 0; 
	public const RT_NEWLINE = 1;
	public const RT_COMMENTS = 2;
	public const RT_KEYWORD_WITH = 11;
	public const RT_KEYWORD_WITHOUT = 12;
	public const RT_KEY_PREFIX = 13;
	public const RT_KEY_NAME = 14;
	public const RT_KEY_CONTROLLER = 15;
	public const RT_KEY_MIDDLEWARE = 16;
	public const RT_VERB = 31;
	public const RT_HANDLER = 32;
	public const RT_NAME = 33;
	public const RT_URI = 34;

	public const TOKEN_NAMES = [
		self::RT_UNKNOWN => 'RT_UNKNOWN',
		self::RT_NEWLINE => 'RT_NEWLINE',
		self::RT_COMMENTS => 'RT_COMMENTS',
		self::RT_KEYWORD_WITH => 'RT_KEYWORD_WITH',
		self::RT_KEYWORD_WITHOUT => 'RT_KEYWORD_WITHOUT',
		self::RT_KEY_PREFIX => 'RT_KEY_PREFIX',
		self::RT_KEY_NAME => 'RT_KEY_NAME',
		self::RT_KEY_CONTROLLER => 'RT_KEY_CONTROLLER',
		self::RT_KEY_MIDDLEWARE => 'RT_KEY_MIDDLEWARE',
		self::RT_VERB => 'RT_VERB',
		self::RT_HANDLER => 'RT_HANDLER',
		self::RT_NAME => 'RT_NAME',
		self::RT_URI => 'RT_URI'
	];

	protected $id;
	protected $token;
	protected $line;
	protected $position;

	public function __get(string $name)
	{
		if (property_exists($this, $name)) {
			return $this->$name;
		}
		//
		if ('name' === $name) {
			return self::TOKEN_NAMES[$this->id];
		}
	}

	public function __call(string $name, array $arguments)
	{
		if (property_exists($this, $name) && ('id' !== $name)) {
			return $this->$name = $arguments[0] ?? null;
		}
	}

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

}
