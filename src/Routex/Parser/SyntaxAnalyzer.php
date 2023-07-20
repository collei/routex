<?php
namespace Routex\Parser;

/**
 * Embodies the syntax analyzer.
 *
 * @author Collei Inc.
 */
class SyntaxAnalyzer
{
	/**
	 * @const array
	 */
	protected const ROUTEX_VALID_SEQUENCES = [
		Token::RT_VERB => [
			[ Token::RT_VERB, Token::RT_URI ],
			[ Token::RT_URI, Token::RT_HANDLER ],
			[ Token::RT_HANDLER, Token::RT_NAME ],
			[ Token::RT_NAME, Token::RT_NEWLINE ],
			[ Token::RT_NAME, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_KEY_MIDDLEWARE, Token::RT_MIDDLEWARE ],
			[ Token::RT_MIDDLEWARE, Token::RT_COMMA ],
			[ Token::RT_MIDDLEWARE, Token::RT_NEWLINE ],
			[ Token::RT_COMMA, Token::RT_MIDDLEWARE ],
		],
		Token::RT_KEYWORD_WITH => [
			[ Token::RT_KEYWORD_WITH, Token::RT_KEY_PREFIX ],
			[ Token::RT_KEYWORD_WITH, Token::RT_KEY_NAME ],
			[ Token::RT_KEYWORD_WITH, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_KEYWORD_WITH, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_KEY_PREFIX, Token::RT_URI ],
			[ Token::RT_KEY_NAME, Token::RT_NAME ],
			[ Token::RT_KEY_CONTROLLER, Token::RT_HANDLER ],
			[ Token::RT_KEY_MIDDLEWARE, Token::RT_MIDDLEWARE ],
			[ Token::RT_URI, Token::RT_KEY_PREFIX ],
			[ Token::RT_URI, Token::RT_KEY_NAME ],
			[ Token::RT_URI, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_URI, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_URI, Token::RT_NEWLINE ],
			[ Token::RT_NAME, Token::RT_KEY_PREFIX ],
			[ Token::RT_NAME, Token::RT_KEY_NAME ],
			[ Token::RT_NAME, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_NAME, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_NAME, Token::RT_NEWLINE ],
			[ Token::RT_HANDLER, Token::RT_KEY_PREFIX ],
			[ Token::RT_HANDLER, Token::RT_KEY_NAME ],
			[ Token::RT_HANDLER, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_HANDLER, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_HANDLER, Token::RT_NEWLINE ],
			[ Token::RT_MIDDLEWARE, Token::RT_KEY_PREFIX ],
			[ Token::RT_MIDDLEWARE, Token::RT_KEY_NAME ],
			[ Token::RT_MIDDLEWARE, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_MIDDLEWARE, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_MIDDLEWARE, Token::RT_COMMA ],
			[ Token::RT_MIDDLEWARE, Token::RT_NEWLINE ],
			[ Token::RT_COMMA, Token::RT_MIDDLEWARE ],
		],
		Token::RT_KEYWORD_WITHOUT => [
			[ Token::RT_KEYWORD_WITHOUT, Token::RT_KEY_PREFIX ],
			[ Token::RT_KEYWORD_WITHOUT, Token::RT_KEY_NAME ],
			[ Token::RT_KEYWORD_WITHOUT, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_KEYWORD_WITHOUT, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_KEYWORD_WITHOUT, Token::RT_NEWLINE ],
			[ Token::RT_KEY_PREFIX, Token::RT_KEY_NAME ],
			[ Token::RT_KEY_PREFIX, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_KEY_PREFIX, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_KEY_PREFIX, Token::RT_NEWLINE ],
			[ Token::RT_KEY_NAME, Token::RT_KEY_PREFIX ],
			[ Token::RT_KEY_NAME, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_KEY_NAME, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_KEY_NAME, Token::RT_NEWLINE ],
			[ Token::RT_KEY_CONTROLLER, Token::RT_KEY_PREFIX ],
			[ Token::RT_KEY_CONTROLLER, Token::RT_KEY_NAME ],
			[ Token::RT_KEY_CONTROLLER, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_KEY_CONTROLLER, Token::RT_NEWLINE ],
			[ Token::RT_KEY_MIDDLEWARE, Token::RT_KEY_NAME ],
			[ Token::RT_KEY_MIDDLEWARE, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_KEY_MIDDLEWARE, Token::RT_KEY_PREFIX ],
			[ Token::RT_KEY_MIDDLEWARE, Token::RT_NEWLINE ],
		],
	];

	/**
	 * Break the token array in discrete lines, removing newlines
	 * from their ends.
	 *
	 * @param array $tokens
	 * @return array
	 */
	public static function breakInLines(array $tokens)
	{
		$emptyLine = [['token' => NULL, 'token_name' => 'EMPTY_LINE']];
		$context = null;
		$last = null;
		$current = null;
		//
		$lines = [];
		$line = [];
		//
		foreach ($tokens as $token) {
			list($last, $current) = array($current, $token);
			//
			// defines the context for the current line being started.
			if (is_null($context) && (
				Token::RT_VERB == $current->id ||
				Token::RT_KEYWORD_WITH == $current->id ||
				Token::RT_KEYWORD_WITHOUT == $current->id
			)) {
				$context = $current->id;
			}
			//
			if (is_null($last)) {
				// insert an empty line for the counter, if any
				if (Token::RT_NEWLINE == $current->id) {
					$lines[] = $emptyLine;
				}
				//
				continue;
			}
			//
			if (Token::RT_NEWLINE == $current->id) {
				$lines[] = $line ?: $emptyLine;
				$line = [];
				$context = null;
			} elseif (Token::RT_COMMENTS == $current->id) {
				// includes empty lines for later counting
				// while debugging in case of syntax error
				if (($count = $current->lineCount) > 0) {
					for ($i = 0; $i < $count; $i++) {
						$lines[] = $emptyLine;
					}
				}
			} else {
				// let's fix the wrongly classified token
				// to the correct token type whenever the user
				// does specify url without an initial slash. 
				if (
					Token::RT_HANDLER == $current->id &&
					Token::RT_VERB == ($last->id ?? 0) &&
					Token::RT_VERB == $context
				) {
					$current->id(Token::RT_URI);
				}
				//
				$line[] = $current;
			}
		}
		//
		return $lines;
	}

	/**
	 * Drives syntax check through all token lines.
	 *
	 * @param array $lines
	 * @param array|null &$errors
	 * @return bool
	 */
	public static function syntaxCheck(array $lines, array &$errors = null)
	{
		$errors = [];
		//
		foreach ($lines as $index => $line_tokens) {
			if (! self::syntaxCheckLine($line_tokens, $error)) {
				$linenumber = 1 + $index;
				$line = "line {$linenumber}: " . self::lineFromTokens($line_tokens);
				$errors[] = compact('line','error');
			}
		}
		//
		return empty($errors);
	}

	/**
	 * Drives syntax check through the given line.
	 *
	 * @param array $line
	 * @param mixed &$error
	 * @return bool
	 */
	protected static function syntaxCheckLine(array $line, &$error = null)
	{
		$last = $current = $context = null;
		//
		foreach ($line as $position => $token) {
			list($last, $current) = array($current, $token);
			//
			if (is_null($last)) {
				$context = $token->id ?? 0;
				continue;
			}
			//
			if (! self::isValidSequence($last, $current, $context, $expected)) {
				$error = sprintf(
					'After (%s) was expected (%s) but found (%s).',
					$last->token,
					implode(' or ', $expected),
					$current->token
				);
				//
				return false;
			}
		}
		//
		return true;
	}

	/**
	 * Converts the token lines back to a cleaned parsed source.
	 *
	 * @param array $tokenLines
	 * @return string
	 */
	public static function sourceFromLines(array $tokenLines)
	{
		$source = [];
		$indentation = 0;
		//
		foreach ($tokenLines as $key => $tokenLine) {
			$tokenId = $tokenLine[0] instanceof Token ? $tokenLine[0]->id : 0;
			//
			if (Token::RT_KEYWORD_WITHOUT == $tokenId) {
				--$indentation;
			}
			//
			$source[$key] = str_repeat("\t", $indentation) . self::lineFromTokens($tokenLine);
			//
			if (Token::RT_KEYWORD_WITH == $tokenId) {
				++$indentation;
			}
		}
		//
		return implode(PHP_EOL, $source);
	}

	/**
	 * Converts a token sequence into a line.
	 *
	 * @param array $tokens
	 * @return string
	 */
	protected static function lineFromTokens(array $tokens)
	{
		$line = array_map(function($token) {
			return $token->token ?? $token['token'] ?? '';
		}, $tokens);
		//
		return str_replace(' , ', ', ', implode(' ', $line));
	}

	/**
	 * Check if the given token sequence is valid for the given context.
	 *
	 * @param \Routex\Parser\Token $lastToken
	 * @param \Routex\Parser\Token $currentToken
	 * @param int $context
	 * @param array|null &$expected
	 * @return bool
	 */
	protected static function isValidSequence(
		$lastToken, $currentToken, $context, array &$expected = null
	) {
		$partial = array($lastToken->id, $currentToken->id);
		$sequences = self::ROUTEX_VALID_SEQUENCES[$context] ?? [];
		//
		if (empty($sequences)) {
			return false;
		}
		//
		$expected = [];
		//
		foreach ($sequences as $p => $sequence) {
			if ($partial[0] === $sequence[0]) {
				if ($partial === $sequence) {
					$expected = [];
					return true;
				} else {
					if (!empty($sequence[1])) {
						$expected[] = Token::TOKEN_NAMES[$sequence[1]]
							?? Token::TOKEN_NAMES[Token::RT_UNKNOWN];
					}
				}
			}
		}
		//
		return false;
	}
}

