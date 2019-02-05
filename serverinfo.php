<?php
/**
 * Configure/edit CalDAVTester serverinfo.xml
 *
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 *
 * @link https://www.calendarserver.org/CalDAVTester.html
 * @link https://github.com/CalConnect/caldavtester-tools
 */

if (php_sapi_name() == 'cli')
{
        die("Use caldavtests.php for command line usage!\n");
}
// change to directory of script (not done automatic for cli), so all pathes are relative from here
chdir(__DIR__);

// configuration
$caldavtester_dir = realpath('..');
$serverinfo = $caldavtester_dir.'/serverinfo.xml';

// quiten undefiend index notices
error_reporting(error_reporting() & ~E_NOTICE);

// read config file, if exists
$config_file = __DIR__.'/.caldavtests.json';
if (file_exists($config_file) && ($conf = json_decode(file_get_contents($config_file), true)))
{
	foreach($conf as $name => $value)
	{
		$$name = $value;
	}
}

if (!file_exists($caldavtester_dir) || !file_exists($caldavtester_dir.'/testcaldav.py'))
{
	die('You need to configure $caldavtester_dir with the directory of your CalDAVTester directory!');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
	process_serverinfo();
}
display_serverinfo();

function process_serverinfo()
{
	switch($button=key($_POST['button']))
	{
		case 'save':
		case 'apply':
		case 'download':
			$xml = save_serverinfo($_POST);
			//if ($button === 'download')
			{
				header('Content-Type: application/xml; charset=utf-8');
				if ($button === 'download') header('Content-disposition: attachment; filename=serverinfo.xml');
				echo $xml->saveXML();
				exit;
			}
			if ($button !== 'save') break;
			// fall through
		case 'cancel':
			header('Location: /');
			exit;
	}
}

function save_serverinfo(array $values)
{
	global $caldavtester_dir;

	$xml = $serverinfo = null;
	foreach(parse_serverinfo($caldavtester_dir.'/scripts/server/serverinfo.xml', true, $xml) as $name => $data)
	{
		switch($name)
		{
			case 'features':
				save_features($data, $values[$name]);
				break;

			case 'substitutions':
				save_substitutions($data, $values[$name]);
				break;

			case 'calendardatafilter':
			case 'addressdatafilter':
				save_datafilter($xml, $name, $data, $values[$name]);
				break;

			default:
				$data['node']->nodeValue = $values[$name];
				break;
		}
	}
	return $xml;
}

function save_datafilter($xml, $name, $data, $values)
{
	foreach($data as $n => $entry)
	{
		if ($values[$n] !== '')
		{
			$entry['node']->nodeValue = $values[$n];
		}
		elseif (isset($entry['node']))
		{
			$entry['node']->parentNode->removeChild($entry['node']);
		}
	}
	$serverinfo = $xml->getElementsByTagName('serverinfo')->item(0);
	for($n = count($data); isset($values[$n]); ++$n)
	{
		if (!empty($values[$n]))
		{
			error_log("$n: $name: ".toString($values[$n]));
			$serverinfo->appendChild($xml->createElement($name, $values[$n]));
		}
	}
}

function save_substitutions(array $substitutions, array $values)
{
	foreach($substitutions as $name => $data)
	{
		if (isset($data['repeats']))
		{
			$data['node']->setAttribute('count', $values['repeats'][$name]);

			save_substitutions($data['repeats'], $values);
		}
		elseif ($data['value'] !== $values[$name])
		{
			$data['node']->value = $values[$name];
		}
	}
}

function save_features(array $features, array $values)
{
	foreach($features as $name => $data)
	{
		// has status changed
		if (($data['node']->getAttribute('enable') !== 'false') !== !empty($values[$name]))
		{
			error_log("$name: data[enabled]=".toString($data['enabled']).", checked=".toString($values[$name]));
			// enabled, attr may exists
			if (!empty($values[$name]))
			{
				$data['node']->removeAttribute('enable');
			}
			// disabled, add attr
			else
			{
				$data['node']->setAttribute('enable', 'false');
			}
		}
	}
}

function display_serverinfo()
{
	global $caldavtester_dir;
	static $name2input_attrs = [
		'nonsslport' => 'type="number" min="1" step="1"',
		'sslport'    => 'type="number" min="1" step="1"',
		'waitcount'  => 'type="number" min="0" step="1"',
		'waitdelay'  => 'type="number" min="0" step=".05"',
		'waitsuccess' => 'type="number" min="0" step="1"',
	];

	html_header();
	echo "<form method='POST'>\n<table class='serverinfo'>\n";

	foreach(parse_serverinfo($caldavtester_dir.'/scripts/server/serverinfo.xml') as $name => $data)
	{
		if (!empty($data['comment']))
		{
			echo "<tr class='comment'><td colspan='2'>".htmlspecialchars($data['comment'])."</td></tr>\n";
		}
		switch($name)
		{
			case 'features':
				display_features($data);
				break;

			case 'substitutions':
				display_substitutions($data);
				break;

			case 'calendardatafilter':
			case 'addressdatafilter':
				// one empty extra line to add a new filter
				$data[] = ['value' => ''];
				foreach($data as $n => $entry)
				{
					echo "<tr class='$name".($n ? '' : ' datafilter-header').
						"'><td>".htmlspecialchars($n ? '' : $name)."</td>";
					echo "<td><input name='".htmlspecialchars($name.'['.$n.']')."' value='".
						htmlspecialchars($entry['value'])."'/></td></tr>\n";
				}
				break;

			default:
				echo "<tr><td>".htmlspecialchars($data['name'])."</td>\n";
				echo "\t<td><input name='".htmlspecialchars($data['name']).
					"' ".(isset($name2input_attrs[$name]) ? $name2input_attrs[$name] : '').
					"' value='".htmlspecialchars($data['value'])."'/></td></tr>\n";
				break;
		}
	}
	echo "</table>\n";
	echo "<div class='buttons'>\n";
	foreach(['save' => 'Save', 'apply' => 'Apply', 'download' => 'Download', 'cancel' => 'Cancel'] as $name => $label)
	{
		echo "<input type='submit' name='button[$name]' value='$label'/>\n";
	}
	echo "</div></form>\n";
	echo "</body>\n</html>\n";
}

function display_features(array $features)
{
	foreach($features as $name => $data)
	{
		if (!empty($data['comment']))
		{
			echo "<tr class='comment'><td colspan='2'>".htmlspecialchars($data['comment'])."</td></tr>\n";
		}
		echo "<tr class='feature'><td><label><input name='features[".htmlspecialchars($name)."]' type='checkbox' ".
			($data['enabled'] ? 'checked' : '')."/>".
			htmlspecialchars($name)."</label></td>\n";

		echo "\t<td class='description'><label for='".htmlspecialchars($name)."'>".
			htmlspecialchars($data['description'])."</label></td></tr>\n";
	}
}

function display_substitutions(array $substitutions)
{
	foreach($substitutions as $name => $data)
	{
		if (!empty($data['comment']))
		{
			echo "<tr class='comment'><td colspan='2'>".htmlspecialchars($data['comment'])."</td></tr>\n";
		}
		if (isset($data['repeats']))
		{
			echo "<tr class='repeats'><td>Number:</td><td><input name='substitutions[repeats][$name]".
				"' type='number' min='2' max='100'".
				" value='".htmlspecialchars($data['count'])."'/></td></tr>\n";

			display_substitutions($data['repeats']);
		}
		else
		{
			echo "<tr class='substitution'><td>".htmlspecialchars($name)."</td>\n";
			echo "\t<td><input name='substitutions[".htmlspecialchars($name).
				"]' value='".htmlspecialchars($data['value'])."'/></td></tr>\n";
		}
	}
}

/**
 * Get features defined in serverinfo and there enabled/disabled state
 *
 * @param string $path
 * @param boolean $add_node
 * @param DOMDocument& $xml =null on return
 * @return array
 * @throws Exception on error
 */
function parse_serverinfo($path, $add_node=false, &$xml=null)
{
	$xml = new DOMDocument();
	if (!$xml->load($path))
	{
		throw new Exception("Can not open '$path' for parsing!");
	}
	$values = [];
	foreach($xml->getElementsByTagName('serverinfo')->item(0)->childNodes as $node)
	{
		switch($node->nodeName)
		{
			case '#text':
				continue 2;

			case '#comment':
				break;

			case 'features':
				$values[$node->nodeName] = get_features($node->childNodes, $add_node);
				break;

			case 'substitutions':
				$values[$node->nodeName] = get_substitutions($node->childNodes, $add_node);
				break;

			case 'calendardatafilter':
			case 'addressdatafilter':
				$values[$node->nodeName][] = array(
					'name'  => $node->nodeName,
					'value' => $node->nodeValue,
					'node'  => $add_node ? $node : null,
				);
				break;

			default:
				$values[$node->nodeName] = array(
					'name'  => $node->nodeName,
					'value' => $node->nodeValue,
				);
				if ($node->previousSibling->previousSibling->nodeName === '#comment')
				{
					$values[$node->nodeName]['comment'] = $node->previousSibling->previousSibling->nodeValue;
				}
				if ($add_node) $values[$node->nodeName]['node'] = $node;
		}
	}
	//echo "<pre>".print_r($values, true)."</pre>\n";
	return $values;
}

function get_features(DOMNodeList $nodes, $add_node=false)
{
	$values = [];
	foreach($nodes as $node)
	{
		if ($node->nodeName !== 'feature') continue;

		$values[$node->nodeValue] = [
			'name' => $node->nodeValue,
			'enabled' => $node->getAttribute('enable') !== 'false',
			'description' => $node->getAttribute('description'),
		];
		if ($node->previousSibling->previousSibling->nodeName === '#comment')
		{
			$values[$node->nodeValue]['comment'] = $node->previousSibling->previousSibling->nodeValue;
		}
		if ($add_node) $values[$node->nodeValue]['node'] = $node;
	}
	return $values;
}

function get_substitutions(DOMNodeList $nodes, $add_node=false)
{
	$substitutions = [];
	foreach($nodes as $node)
	{
		if ($node->nodeName === 'repeat')
		{
			$substitution = [
				'count' => $node->getAttribute('count'),
				'repeats' => get_substitutions($node->childNodes, $add_node),
			];
			if ($add_node) $substitution['node'] = $node;
		}
		elseif ($node->nodeName === 'substitution')
		{
			$key = $value = null;
			foreach($node->childNodes as $subnode)
			{
				if (in_array($subnode->nodeName, ['key', 'value']))
				{
					${$subnode->nodeName} = $subnode->nodeValue;
					if ($subnode->nodeName == 'value') $value_node = $subnode;
				}
			}
			$substitution = [
				'name'  => $key,
				'value' => $value,
			];
			if ($add_node) $substitution['node'] = $value_node;
		}
		else
		{
			continue;
		}

		if ($node->previousSibling->previousSibling->nodeName === '#comment')
		{
			$substitution['comment'] = $node->previousSibling->previousSibling->nodeValue;
		}

		if (!empty($substitution['name']))
		{
			$substitutions[$substitution['name']] = $substitution;
		}
		else
		{
			$substitutions[] = $substitution;
		}
	}
	return $substitutions;
}

/**
 * Get features defined in serverinfo and there enabled/disabled state
 *
 * @return array feature => array(name, enabled, description)
 */
function parse_serverinfo_simple_xml($path)
{
	if (!($xml = simplexml_load_file($path)))
	{
		throw new Exception("Can not open '$path' for parsing!");
	}
	$features = array();
	foreach($xml->features->feature as $feature)
	{
		$features[(string)$feature] = array(
			'name' => (string)$feature,
			'enabled' => (string)$feature['enable'] !== 'false',
			'description' => (string)$feature['description'],
		);
	}
	print_r($features);
	return $features;
}

/**
 * Get features defined in serverinfo and there enabled/disabled state
 *
 * @return array feature => array(name, enabled, description)
 */
function get_features_old()
{
	global $serverinfo;

	if (!($xml = file_get_contents($serverinfo)))
	{
		throw new Exception("Serverinfo '$serverinfo' NOT found!");
	}
	$matches = null;
	preg_match_all('|(<!--\s*)?<feature>([^<]+)</feature>\s*(<!--\s*(.*)\s*-->)?|m', $xml, $matches, PREG_SET_ORDER);
	$features = array();
	foreach($matches as $feature)
	{
		$features[$feature[2]] = array(
			'name' => $feature[2],
			'enabled' => !$feature[1],
			'description' => !empty($feature[4]) ? $feature[4] : $feature[2],
		);
	}
	return $features;
}

/**
 * Output html header incl. body tag
 */
function html_header()
{
	echo "<html>\n<head>\n";
	echo "\t<title>CalDAVTester GUI: serverinfo.xml</title>\n";
	if (file_exists(__DIR__.'/jquery.js'))
	{
		echo "\t<script src='jquery.js'></script>\n";
	}
	else
	{
		echo "\t<script src='https://code.jquery.com/jquery-2.2.4.min.js' integrity='sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=' crossorigin='anonymous'></script>\n";
	}
	echo "\t<script src='gui.js'></script>\n";
	echo "\t<link type='text/css' href='gui.css' rel='StyleSheet'/>\n";
	echo "</head>\n<body>\n";
}

/**
 * Format array or other types as (one-line) string, eg. for error_log statements
 *
 * @param mixed $var variable to dump
 * @return string
 */
function toString($var)
{
	switch (($type = gettype($var)))
	{
		case 'boolean':
			return $var ? 'TRUE' : 'FALSE';
		case 'string':
			return "'$var'";
		case 'integer':
		case 'double':
		case 'resource':
			return $var;
		case 'NULL':
			return 'NULL';
		case 'object':
		case 'array':
			return str_replace(array("\n",'    '/*,'Array'*/),'',print_r($var,true));
	}
	return 'UNKNOWN TYPE!';
}
