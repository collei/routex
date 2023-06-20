<?php
require './vendor/autoload.php';

use Routex\Parser\Tokenizer;
use Routex\Parser\SyntaxAnalyzer;
use Routex\Parser\Parser;
use Routex\Engine\Route;

$directions = [
	'/book/contacts/{contact}/phones/{phone?}',
	'/schule/{type}/{name?}/{student?}',
];

$sample_uris = [
	'/schule/gymmnasium/Friedlichert/12485',
	'/schule/Realschulle/Kerch?identifier=GA&number=834586&region=Schubert#SlavaUkraine',
	'/schule/hauptschule?type=LEGO-IV',
	'/book/contacts/1376/phones/79857644323',
	'/book/contacts/1376/phones?dull=no',
];

$samples = [];

foreach ($directions as $direction) {
	$compiled = Route::compileUri($direction);
	//
	$sample = [
		'orig' => $direction,
		'dest' => $compiled,
		'units' => []
	];
	//
	foreach ($sample_uris as $uri) {
		$resp = Route::matchUri($compiled, $uri, $data);
		//
		$sample['units'][] = $resp ? compact('uri','resp','data') : compact('uri','resp');
	}
	//
	$samples[] = $sample;
}

?>
<fieldset>
	<legend>Realschulle</legend>
	<fieldset><textarea style="width:95% !important" rows="20"><?php echo print_r($samples,true); ?></textarea></fieldset>
</fieldset>
<?php

$example = './web.routex';

$routes = new Parser($example);

?>
<fieldset>
	<legend>Parsed contents</legend>
	<fieldset><pre><?php echo print_r($routes,true); ?></pre></fieldset>
</fieldset>
<?php

$result = $routes->parse()->routes();

?>
<fieldset>
	<legend>Routes themselves</legend>
	<fieldset><pre><?php echo print_r($result,true); ?></pre></fieldset>
</fieldset>
<?php

$tokens = Tokenizer::tokenize(
	$source = file_get_contents($example)
);

$lines = SyntaxAnalyzer::breakInLines($tokens);

SyntaxAnalyzer::syntaxCheck($lines, $errors);

?>
<fieldset>
	<legend>Syntax Errors (if any)</legend>
	<fieldset><pre><?php echo print_r($errors,true); ?></pre></fieldset>
</fieldset>
<fieldset>
	<legend>Parsed Tokens</legend>
	<fieldset>
		<table width="100%">
<?php
//
foreach ($lines as $n => $line) {
	//
	?>
			<tr>
				<th width="2%"><?=($n + 1)?></td>
	<?php
	//
	foreach ($line as $token) {
		$item = $token;
		//
		?>
				<td width="10%"><pre><?=(print_r($item,true))?></pre></td>
		<?php
		//
	}
	//
	?>
			</tr>
	<?php
	//
}
//
?>
		</table>
	</fieldset>
</fieldset>
<?php

