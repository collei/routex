<?php
namespace Routex\Parser;

class SyntaxAnalyzer
{
	public static function linearize(array $tokens)
	{
		$last = null;
		$current = null;
		//
		$lines = [];
		$line = [];
		//
		foreach ($tokens as $token) {
			list($last, $current) = array($current, $token);
			//
			if (is_null($last)) {
				continue;
			}
			//
			if (Tokenizer::ROUTEX_NEWLINE == $current['token_id']) {
				$lines[] = $line;
				$line = [];
			} elseif (Tokenizer::ROUTEX_COMMENTS == $current['token_id']) {
				if (($count = $current['lines']) > 0) {
					for ($i = 0; $i < $count; $i++) {
						$lines[] = [];
					}
				}
			} else {
				$line[] = $current;
			}
		}
		//
		return $lines;
	} 

}

