<?php
namespace Routex\Parser;

class SyntaxAnalyzer
{
	protected const ROUTEX_VALID_SEQUENCES = [
		Tokenizer::ROUTEX_VERB => [
			[ Tokenizer::ROUTEX_VERB, Tokenizer::ROUTEX_URI ],
			[ Tokenizer::ROUTEX_URI, Tokenizer::ROUTEX_HANDLER ],
			[ Tokenizer::ROUTEX_HANDLER, Tokenizer::ROUTEX_NAME ],
			[ Tokenizer::ROUTEX_NAME, NULL ],
		],
		Tokenizer::ROUTEX_KEYWORD_WITH => [
			[ Tokenizer::ROUTEX_KEYWORD_WITH, Tokenizer::ROUTEX_KEY_PREFIX ],
			[ Tokenizer::ROUTEX_KEYWORD_WITH, Tokenizer::ROUTEX_KEY_NAME ],
			[ Tokenizer::ROUTEX_KEYWORD_WITH, Tokenizer::ROUTEX_KEY_CONTROLLER ],
			[ Tokenizer::ROUTEX_KEYWORD_WITH, Tokenizer::ROUTEX_KEY_MIDDLEWARE ],
			[ Tokenizer::ROUTEX_KEY_PREFIX, Tokenizer::ROUTEX_URI ],
			[ Tokenizer::ROUTEX_KEY_NAME, Tokenizer::ROUTEX_NAME ],
			[ Tokenizer::ROUTEX_KEY_CONTROLLER, Tokenizer::ROUTEX_HANDLER ],
			[ Tokenizer::ROUTEX_KEY_MIDDLEWARE, Tokenizer::ROUTEX_HANDLER ],
			[ Tokenizer::ROUTEX_URI, Tokenizer::ROUTEX_KEY_PREFIX ],
			[ Tokenizer::ROUTEX_URI, Tokenizer::ROUTEX_KEY_NAME ],
			[ Tokenizer::ROUTEX_URI, Tokenizer::ROUTEX_KEY_CONTROLLER ],
			[ Tokenizer::ROUTEX_URI, Tokenizer::ROUTEX_KEY_MIDDLEWARE ],
			[ Tokenizer::ROUTEX_URI, NULL ],
			[ Tokenizer::ROUTEX_NAME, Tokenizer::ROUTEX_KEY_PREFIX ],
			[ Tokenizer::ROUTEX_NAME, Tokenizer::ROUTEX_KEY_NAME ],
			[ Tokenizer::ROUTEX_NAME, Tokenizer::ROUTEX_KEY_CONTROLLER ],
			[ Tokenizer::ROUTEX_NAME, Tokenizer::ROUTEX_KEY_MIDDLEWARE ],
			[ Tokenizer::ROUTEX_NAME, NULL ],
			[ Tokenizer::ROUTEX_HANDLER, Tokenizer::ROUTEX_KEY_PREFIX ],
			[ Tokenizer::ROUTEX_HANDLER, Tokenizer::ROUTEX_KEY_NAME ],
			[ Tokenizer::ROUTEX_HANDLER, Tokenizer::ROUTEX_KEY_CONTROLLER ],
			[ Tokenizer::ROUTEX_HANDLER, Tokenizer::ROUTEX_KEY_MIDDLEWARE ],
			[ Tokenizer::ROUTEX_HANDLER, NULL ],
		],
		Tokenizer::ROUTEX_KEYWORD_WITHOUT => [
			[ Tokenizer::ROUTEX_KEYWORD_WITHOUT, Tokenizer::ROUTEX_KEY_PREFIX ],
			[ Tokenizer::ROUTEX_KEYWORD_WITHOUT, Tokenizer::ROUTEX_KEY_NAME ],
			[ Tokenizer::ROUTEX_KEYWORD_WITHOUT, Tokenizer::ROUTEX_KEY_CONTROLLER ],
			[ Tokenizer::ROUTEX_KEYWORD_WITHOUT, Tokenizer::ROUTEX_KEY_MIDDLEWARE ],
			[ Tokenizer::ROUTEX_KEYWORD_WITHOUT, NULL ],
			[ Tokenizer::ROUTEX_KEY_PREFIX, Tokenizer::ROUTEX_KEY_NAME ],
			[ Tokenizer::ROUTEX_KEY_PREFIX, Tokenizer::ROUTEX_KEY_CONTROLLER ],
			[ Tokenizer::ROUTEX_KEY_PREFIX, Tokenizer::ROUTEX_KEY_MIDDLEWARE ],
			[ Tokenizer::ROUTEX_KEY_PREFIX, NULL ],
			[ Tokenizer::ROUTEX_KEY_NAME, Tokenizer::ROUTEX_KEY_PREFIX ],
			[ Tokenizer::ROUTEX_KEY_NAME, Tokenizer::ROUTEX_KEY_CONTROLLER ],
			[ Tokenizer::ROUTEX_KEY_NAME, Tokenizer::ROUTEX_KEY_MIDDLEWARE ],
			[ Tokenizer::ROUTEX_KEY_NAME, NULL ],
			[ Tokenizer::ROUTEX_KEY_CONTROLLER, Tokenizer::ROUTEX_KEY_PREFIX ],
			[ Tokenizer::ROUTEX_KEY_CONTROLLER, Tokenizer::ROUTEX_KEY_NAME ],
			[ Tokenizer::ROUTEX_KEY_CONTROLLER, Tokenizer::ROUTEX_KEY_MIDDLEWARE ],
			[ Tokenizer::ROUTEX_KEY_CONTROLLER, NULL ],
			[ Tokenizer::ROUTEX_KEY_MIDDLEWARE, Tokenizer::ROUTEX_KEY_NAME ],
			[ Tokenizer::ROUTEX_KEY_MIDDLEWARE, Tokenizer::ROUTEX_KEY_CONTROLLER ],
			[ Tokenizer::ROUTEX_KEY_MIDDLEWARE, Tokenizer::ROUTEX_KEY_PREFIX ],
			[ Tokenizer::ROUTEX_KEY_MIDDLEWARE, NULL ],
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
				Tokenizer::ROUTEX_VERB == $current['token_id'] ||
				Tokenizer::ROUTEX_KEYWORD_WITH == $current['token_id'] ||
				Tokenizer::ROUTEX_KEYWORD_WITHOUT == $current['token_id']
			)) {
				$context = $current['token_id'];
			}
			//
			if (is_null($last)) {
				// insert an empty line for the counter, if any
				if (Tokenizer::ROUTEX_NEWLINE == $current['token_id']) {
					$lines[] = $emptyLine;
				}
				//
				continue;
			}
			//
			if (Tokenizer::ROUTEX_NEWLINE == $current['token_id']) {
				$lines[] = $line ?: $emptyLine;
				$line = [];
				$context = null;
			} elseif (Tokenizer::ROUTEX_COMMENTS == $current['token_id']) {
				// includes empty lines for later counting
				// while debugging in case of syntax error
				if (($count = $current['lines']) > 0) {
					for ($i = 0; $i < $count; $i++) {
						$lines[] = $emptyLine;
					}
				}
			} else {
				// let's fix the wrongly classified token
				// to the correct token type whenever the user
				// does specify url without an initial slash. 
				if (
					Tokenizer::ROUTEX_HANDLER == $current['token_id'] &&
					Tokenizer::ROUTEX_VERB == ($last['token_id'] ?? 0) &&
					Tokenizer::ROUTEX_VERB == $context
				) {
					$current['token_id'] = Tokenizer::ROUTEX_URI;
					$current['token_name'] = Tokenizer::ROUTEX_TOKEN_NAMES[Tokenizer::ROUTEX_URI];
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
				$context = $token['token_id'] ?? 0;
				continue;
			}
			//
			if (! self::isValidSequence($last, $current, $context, $expected)) {
				$error = sprintf(
					'After (%s) was expected (%s) but found (%s).',
					$last['token'],
					implode(' or ', $expected),
					$current['token']
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
			return $token['token'] ?? $token;
		}, $tokens);
		//
		return implode(' ', $line);
	}

	protected static function isValidSequence(
		$lastToken, $currentToken, $context, array &$expected = null
	) {
		$partial = array($lastToken['token_id'], $currentToken['token_id']);
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
						$expected[] = Tokenizer::ROUTEX_TOKEN_NAMES[$sequence[1]]
							?? Tokenizer::ROUTEX_TOKEN_NAMES[Tokenizer::ROUTEX_UNKNOWN];
					}
				}
			}
		}
		//
		return false;
	}

}

