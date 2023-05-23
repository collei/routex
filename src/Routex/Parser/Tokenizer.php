<?php
namespace Routex\Parser;

class Tokenizer
{
	protected const RGX_TOKENIZER = '/(\s*"(?>[^"]+)"\s*|\s*\'(?>[^\']+)\'\s*|\s+)/';
	protected const RGX_COMMENT = '/(?:\/\*[\s\S]*?\*\/)|(?:\/\/[^\r\n]*)|(?:#[^\r\n]*)/m';
	protected const RGX_COMMENT_ISOLATED = '/__X([0-9A-Fa-f]*)X__/';
	protected const RGX_NEWLINE_ISOLATED = '/__NEWLINE__/';

	protected const NEWLINE_VALUE = '__NEWLINE__';

	public const TOKEN_SPEC = [
		Token::RT_NEWLINE => self::RGX_NEWLINE_ISOLATED,
		Token::RT_COMMENTS => self::RGX_COMMENT_ISOLATED,
		Token::RT_KEYWORD_WITH => '/^(within|with)$/',
		Token::RT_KEYWORD_WITHOUT => '/^without$/',
		Token::RT_KEY_PREFIX => '/^prefix$/',
		Token::RT_KEY_NAME => '/^name$/',
		Token::RT_KEY_CONTROLLER => '/^controller$/',
		Token::RT_KEY_MIDDLEWARE => '/^middleware$/',
		Token::RT_VERB => '/^(?P<verb>(?>get|post|patch|put|head|options|delete)|any:\w+(?>\,\w+)*|any)$/',
		Token::RT_HANDLER => '/^(?>(?P<handler>\w+)(?>\@(?P<method>\w+))?)$/',
		Token::RT_NAME => '/^("([^"\\\\]*(\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(\\\\.[^\'\\\\]*)*)\')$/',
		Token::RT_URI => '/^(\/([\w\-.]+|{\??\w+(=[^}\s]+)?})(\/[\w\-.]+|\/{\??\w+(=[^}\s]+)?})*|\/)$/'
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
			foreach (self::TOKEN_SPEC as $id => $regex) {
				if (self::NEWLINE_VALUE == $token) {
					$classified[] = self::classifyToken(PHP_EOL, Token::RT_NEWLINE, 1);
					$recognized = true;
					break;
				}
				//
				if (preg_match($regex, $token)) {
					//
					if (Token::RT_COMMENTS == $id) {
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
			'token_name' => Token::TOKEN_NAMES[$id],
			'lines' => $lines
		];
	}

}


