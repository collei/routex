<?php
namespace Routex\Parser;

class SyntaxAnalyzer
{
	protected const ROUTEX_VALID_SEQUENCES = [
		Token::RT_VERB => [
			[ Token::RT_VERB, Token::RT_URI ],
			[ Token::RT_URI, Token::RT_HANDLER ],
			[ Token::RT_HANDLER, Token::RT_NAME ],
			[ Token::RT_NAME, Token::RT_NEWLINE ],
		],
		Token::RT_KEYWORD_WITH => [
			[ Token::RT_KEYWORD_WITH, Token::RT_KEY_PREFIX ],
			[ Token::RT_KEYWORD_WITH, Token::RT_KEY_NAME ],
			[ Token::RT_KEYWORD_WITH, Token::RT_KEY_CONTROLLER ],
			[ Token::RT_KEYWORD_WITH, Token::RT_KEY_MIDDLEWARE ],
			[ Token::RT_KEY_PREFIX, Token::RT_URI ],
			[ Token::RT_KEY_NAME, Token::RT_NAME ],
			[ Token::RT_KEY_CONTROLLER, Token::RT_HANDLER ],
			[ Token::RT_KEY_MIDDLEWARE, Token::RT_HANDLER ],
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

	protected static function lineFromTokens(array $tokens)
	{
		$line = array_map(function($token) {
			return $token->token ?? $token;
		}, $tokens);
		//
		return implode(' ', $line);
	}

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

