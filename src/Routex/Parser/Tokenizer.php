<?php
namespace Routex\Parser;

/**
 * Bears Routex tokenizer.
 *
 * @author Collei Inc.
 */
class Tokenizer
{
	/**
	 * @const string
	 */
	protected const RGX_TOKENIZER = '/(\s*"(?>[^"]+)"\s*|\s*\'(?>[^\']+)\'\s*|\s*\,\s*|\s+)/';

	/**
	 * @const string
	 */
	protected const RGX_COMMENT = Token::TOKEN_REGEX[Token::RT_COMMENTS];

	/**
	 * @const string
	 */
	protected const RGX_COMMENT_ISOLATED = '/__X([0-9A-Fa-f]*)X__/';

	/**
	 * @const string
	 */
	protected const RGX_NEWLINE = Token::TOKEN_REGEX[Token::RT_NEWLINE];

	/**
	 * @const string
	 */
	protected const RGX_NEWLINE_ISOLATED = '/__NEWLINE__/';

	/**
	 * @const string
	 */
	protected const PLACEHOLDER_NEWLINE = '__NEWLINE__';

	/**
	 * @const array
	 */
	protected const TOKEN_KEYS = [
		Token::RT_KEY_PREFIX,
		Token::RT_KEY_NAME,
		Token::RT_KEY_CONTROLLER,
		Token::RT_KEY_MIDDLEWARE,
	];

	/**
	 * @const array
	 */
	public const TOKEN_SPEC = [
		Token::RT_NEWLINE => self::RGX_NEWLINE_ISOLATED,
		Token::RT_COMMENTS => self::RGX_COMMENT_ISOLATED,
		Token::RT_COMMA => Token::TOKEN_REGEX[Token::RT_COMMA],
		Token::RT_KEYWORD_WITH => Token::TOKEN_REGEX[Token::RT_KEYWORD_WITH],
		Token::RT_KEYWORD_WITHOUT => Token::TOKEN_REGEX[Token::RT_KEYWORD_WITHOUT],
		Token::RT_KEY_PREFIX => Token::TOKEN_REGEX[Token::RT_KEY_PREFIX],
		Token::RT_KEY_NAME => Token::TOKEN_REGEX[Token::RT_KEY_NAME],
		Token::RT_KEY_CONTROLLER => Token::TOKEN_REGEX[Token::RT_KEY_CONTROLLER],
		Token::RT_KEY_MIDDLEWARE => Token::TOKEN_REGEX[Token::RT_KEY_MIDDLEWARE],
		Token::RT_URI => Token::TOKEN_REGEX[Token::RT_URI],
		Token::RT_VERB => Token::TOKEN_REGEX[Token::RT_VERB],
		Token::RT_HANDLER => Token::TOKEN_REGEX[Token::RT_HANDLER],
		Token::RT_NAME => Token::TOKEN_REGEX[Token::RT_NAME],
		Token::RT_MIDDLEWARE => Token::TOKEN_REGEX[Token::RT_MIDDLEWARE],
	];

	/**
	 * Break the given $text in tokens.
	 *
	 * @static
	 * @param string $text
	 * @return array
	 */
	public static function tokenize($text)
	{
		return self::classifyTokens(
			self::breakInTokens($text)
		);
	}

	/**
	 * Help to protect comments against unwanted parsing by encoding them.
	 *
	 * @static
	 * @param string $source
	 * @return string
	 */
	protected static function isolateComments($source)
	{
		return preg_replace_callback(self::RGX_COMMENT, function($matches) {
			return '__X' . bin2hex($matches[0]) . 'X__';
		}, $source);
	}

	/**
	 * Restore previously protected comments (reverts isolateComments() method).
	 *
	 * @static
	 * @param string $source
	 * @return string
	 */
	protected static function restoreComments($source)
	{
		return preg_replace_callback(self::RGX_COMMENT_ISOLATED, function($matches) {
			return hex2bin($matches[1]);
		}, $source);
	}

	/**
	 * Help to protect newlines against unwanted parsing.
	 *
	 * @static
	 * @param string $source
	 * @return string
	 */
	protected static function isolateNewlines($source)
	{
		return str_replace(
			["\r\n", "\n", PHP_EOL],
			[PHP_EOL, PHP_EOL, self::PLACEHOLDER_NEWLINE . ' '],
			$source
		);
	}

	/**
	 * Restore previously protected newlines.
	 *
	 * @static
	 * @param string $source
	 * @return string
	 */
	protected static function restoreNewlines($source)
	{
		return str_replace(self::PLACEHOLDER_NEWLINE, PHP_EOL, $source);
	}

	/**
	 * Parses the given $source into an array of string (brute tokens).
	 *
	 * @static
	 * @param string $source
	 * @return array
	 */
	protected static function breakInTokens($source)
	{
		$source = self::isolateComments($source);
		$source = self::isolateNewlines($source);
		//
		return preg_split(
			self::RGX_TOKENIZER,
			$source,
			0,
			PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
		);
	}

	/**
	 * Counts how many lines the given $literal token has.
	 *
	 * @static
	 * @param string $literal
	 * @return int
	 */
	protected static function tokenLines($literal)
	{
		if ($lines = preg_split(self::RGX_NEWLINE, $literal)) {
			return count($lines) - 1;
		}
		//
		return 1;
	}

	/**
	 * Drives token classification through the given token array.
	 *
	 * @static
	 * @param array $tokens
	 * @return array
	 */
	protected static function classifyTokens(array $tokens)
	{
		$classified = [];
		$current_key = null; 
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
				if (self::PLACEHOLDER_NEWLINE == $token) {
					$classified[] = self::classifyToken(PHP_EOL, Token::RT_NEWLINE, 1);
					$recognized = true;
					$current_key = null;
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
					if (in_array($id, self::TOKEN_KEYS)) {
						$current_key = $id;
					}
					//
					if ((Token::RT_KEY_MIDDLEWARE == $current_key) && (Token::RT_HANDLER == $id)) {
						$id = Token::RT_MIDDLEWARE;
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

	/**
	 * Classifies the given token according to internal token spec.
	 *
	 * @static
	 * @param string $token
	 * @param int $id
	 * @param int|null $lines
	 * @return \Routex\Parser\Token
	 */
	protected static function classifyToken($token, $id, $lines = null)
	{
		return Token::make($id, $token)->lineCount($lines);
	}
}

