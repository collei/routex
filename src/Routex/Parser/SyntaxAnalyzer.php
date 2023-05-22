<?php
namespace Routex\Parser;

class SyntaxAnalyzer
{
	public static function linearize(array $tokens)
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

}

