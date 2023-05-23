<?php
namespace Routex\Parser;

class Tokenizer
{
	protected const RGX_TOKENIZER = '/(\s*"(?>[^"]+)"\s*|\s*\'(?>[^\']+)\'\s*|\s+)/';
	protected const RGX_COMMENT = '/(?:\/\*[\s\S]*?\*\/)|(?:\/\/[^\r\n]*)|(?:#[^\r\n]*)/m';
	protected const RGX_COMMENT_ISOLATED = '/__X([0-9A-Fa-f]*)X__/';
	protected const RGX_NEWLINE_ISOLATED = '/__NEWLINE__/';

	protected const NEWLINE_VALUE = '__NEWLINE__';

	public const ROUTEX_UNKNOWN = 0; 
	public const ROUTEX_NEWLINE = 1;
	public const ROUTEX_COMMENTS = 2;
	public const ROUTEX_KEYWORD_WITH = 11;
	public const ROUTEX_KEYWORD_WITHOUT = 12;
	public const ROUTEX_KEY_PREFIX = 13;
	public const ROUTEX_KEY_NAME = 14;
	public const ROUTEX_KEY_CONTROLLER = 15;
	public const ROUTEX_KEY_MIDDLEWARE = 16;
	public const ROUTEX_VERB = 31;
	public const ROUTEX_HANDLER = 32;
	public const ROUTEX_NAME = 33;
	public const ROUTEX_URI = 34;

	public const ROUTEX_TOKEN_SPEC = [
		self::ROUTEX_NEWLINE => self::RGX_NEWLINE_ISOLATED,
		self::ROUTEX_COMMENTS => self::RGX_COMMENT_ISOLATED,
		self::ROUTEX_KEYWORD_WITH => '/^(within|with)$/',
		self::ROUTEX_KEYWORD_WITHOUT => '/^without$/',
		self::ROUTEX_KEY_PREFIX => '/^prefix$/',
		self::ROUTEX_KEY_NAME => '/^name$/',
		self::ROUTEX_KEY_CONTROLLER => '/^controller$/',
		self::ROUTEX_KEY_MIDDLEWARE => '/^middleware$/',
		self::ROUTEX_VERB => '/^(?P<verb>(?>get|post|patch|put|head|options|delete)|any:\w+(?>\,\w+)*|any)$/',
		self::ROUTEX_HANDLER => '/^(?>(?P<handler>\w+)(?>\@(?P<method>\w+))?)$/',
		self::ROUTEX_NAME => '/^("([^"\\\\]*(\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(\\\\.[^\'\\\\]*)*)\')$/',
		self::ROUTEX_URI => '/^(\/([\w\-.]+|{\??\w+(=[^}\s]+)?})(\/[\w\-.]+|\/{\??\w+(=[^}\s]+)?})*|\/)$/'
	];

	public const ROUTEX_TOKEN_NAMES = [
		self::ROUTEX_UNKNOWN => 'ROUTEX_UNKNOWN',
		self::ROUTEX_NEWLINE => 'ROUTEX_NEWLINE',
		self::ROUTEX_COMMENTS => 'ROUTEX_COMMENTS',
		self::ROUTEX_KEYWORD_WITH => 'ROUTEX_KEYWORD_WITH',
		self::ROUTEX_KEYWORD_WITHOUT => 'ROUTEX_KEYWORD_WITHOUT',
		self::ROUTEX_KEY_PREFIX => 'ROUTEX_KEY_PREFIX',
		self::ROUTEX_KEY_NAME => 'ROUTEX_KEY_NAME',
		self::ROUTEX_KEY_CONTROLLER => 'ROUTEX_KEY_CONTROLLER',
		self::ROUTEX_KEY_MIDDLEWARE => 'ROUTEX_KEY_MIDDLEWARE',
		self::ROUTEX_VERB => 'ROUTEX_VERB',
		self::ROUTEX_HANDLER => 'ROUTEX_HANDLER',
		self::ROUTEX_NAME => 'ROUTEX_NAME',
		self::ROUTEX_URI => 'ROUTEX_URI'
	];

	public static function tokenize($text)
	{
		return self::classifyTokens(
			self::breakInTokens($text)
		);
	}

	protected static function isolateComments($source)
	{
		return preg_replace_callback(self::RGX_COMMENT, function($matches) {
			return '__X' . bin2hex($matches[0]) . 'X__';
		}, $source);
	}

	protected static function restoreComments($source)
	{
		return preg_replace_callback(self::RGX_COMMENT_ISOLATED, function($matches) {
			return hex2bin($matches[1]);
		}, $source);
	}

	protected static function breakInTokens($source)
	{
		$source = self::isolateComments($source);

		$source = str_replace(["\r\n","\n"], PHP_EOL, $source);
		$source = str_replace(PHP_EOL, self::NEWLINE_VALUE . ' ', $source);

		return preg_split(
			self::RGX_TOKENIZER,
			$source,
			0,
			PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
		);
	}

	protected static function tokenLines($literal)
	{
		if ($lines = explode(PHP_EOL, $literal)) {
			return count($lines) - 1;
		}
		//
		return 1;
	}

	protected static function classifyTokens(array $tokens)
	{
		$classified = [];
		//
		foreach ($tokens as $token) {
			$token = trim($token);
			$recognized = false;
			//
			if (empty($token)) {
				continue;
			}
			//
			foreach (self::ROUTEX_TOKEN_SPEC as $id => $regex) {
				if (self::NEWLINE_VALUE == $token) {
					$classified[] = self::classifyToken(PHP_EOL, self::ROUTEX_NEWLINE, 1);
					$recognized = true;
					break;
				}
				//
				if (preg_match($regex, $token)) {
					//
					if (self::ROUTEX_COMMENTS == $id) {
						$token = self::restoreComments($token);
						$lines = self::tokenLines($token);
					} else {
						$lines = 0;
					}
					//
					$classified[] = self::classifyToken($token, $id, $lines);
					$recognized = true;
					break;
				}
			}
			//
			if (!$recognized) {
				$classified[] = self::classifyToken($token, 0, null);
			}
		}
		//
		return $classified;
	}

	protected static function classifyToken($token, $id, $lines = null)
	{
		return [
			'token' => $token,
			'token_id' => $id,
			'token_name' => self::ROUTEX_TOKEN_NAMES[$id],
			'lines' => $lines
		];
	}

}


