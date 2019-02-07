<?php
/**
 * Configure/edit CalDAVTester serverinfo.xml
 *
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 *
 * @link https://github.com/CalConnect/caldavtester
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

// quiten undefined index notices
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
	if (isset($_FILES['upload']) && !empty($_FILES['upload']['tmp_name']))
	{
		if (!move_uploaded_file($_FILES['upload']['tmp_name'], $serverinfo))
		{
			echo "<p class='message'>Error uploading serverinfo.xml file!</p>\n";
		}
	}
	else
	{
		process_serverinfo();
	}
}
display_serverinfo();

function process_serverinfo()
{
	global $serverinfo;

	switch($button=key($_POST['button']))
	{
		case 'save':
		case 'apply':
		case 'download':
			$xml = save_serverinfo($_POST);
			if (file_exists($serverinfo) && is_writable(dirname($serverinfo)))
			{
				rename ($serverinfo, dirname($serverinfo).'/'.basename($serverinfo, '.xml').'.old.xml');
			}
			$xml->save($serverinfo);

			if ($button === 'download')
			{
				header('Content-Type: application/xml; charset=utf-8');
				header('Content-disposition: attachment; filename=serverinfo.xml');
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
	$serverinfo = null;
	for($n = count($data); isset($values[$n]); ++$n)
	{
		if (!empty($values[$n]))
		{
			if (!isset($serverinfo))
			{
				$serverinfo = $xml->getElementsByTagName('serverinfo')->item(0);
				$serverinfo->appendChild($xml->createTextNode("\n\t"));
				$serverinfo->appendChild($xml->createComment('Additional filters'));
				$serverinfo->appendChild($xml->createTextNode("\n"));
			}
			//error_log("$n: $name: ".toString($values[$n]));
			$serverinfo->appendChild($xml->createTextNode("\t"));
			$serverinfo->appendChild($xml->createElement($name, $values[$n]));
			$serverinfo->appendChild($xml->createTextNode("\n"));
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
			//error_log("updating $name: ".toString($data['value'])." --> ".toString($values[$name]));
			$data['node']->nodeValue = $values[$name];
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
			//error_log("updating $name: data[enabled]=".toString($data['enabled']).", checked=".toString($values[$name]));
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
	global $caldavtester_dir,$serverinfo;
	static $name2input_attrs = [
		'host' => 'title="you can NOT use localhost, use eg. \'docker.for.mac.localhost\' on a Mac"',
		'nonsslport' => 'type="number" min="1" step="1"',
		'sslport'    => 'type="number" min="1" step="1"',
		'waitcount'  => 'type="number" min="0" step="1"',
		'waitdelay'  => 'type="number" min="0" step=".05"',
		'waitsuccess' => 'type="number" min="0" step="1"',
	];

	html_header();
	if (!file_exists($serverinfo))
	{
		echo "<p class='message'>You need to create or upload a serverinfo.xml document to use CalDAV-Tester.</p>\n";
	}
	echo "<form method='POST' enctype='multipart/form-data'>\n<table class='serverinfo'>\n";

	// read serverinfo template from CalDAVTester sources
	$template = $caldavtester_dir.'/scripts/server/serverinfo.xml';
	$info = parse_serverinfo($template);

	// if we have an own serverinfo.xml, use it to overwrite values from the template
	if (realpath($template) !== realpath($serverinfo) && file_exists($serverinfo))
	{
		$own_info = parse_serverinfo($serverinfo);
	}

	// do we have values from the discovery
	if (isset($_POST['discovered']))
	{
		$discovered = json_decode($_POST['discovered'], true);
	}

	display_header('Access+Timeouts', 'access');
	foreach($info as $name => $data)
	{
		if (!empty($data['comment']))
		{
			echo "<tr class='comment'><td colspan='2'>".htmlspecialchars($data['comment'])."</td></tr>\n";
		}
		switch($name)
		{
			case 'features':
				display_header('Enable / disable supported features', $name);
				display_features($data, $own_info[$name], $discovered[$name]);
				break;

			case 'substitutions':
				display_header('Substitutions / WebDAV tree', $name);
				display_substitutions($data, $own_info[$name], $discovered[$name]);
				break;

			case 'calendardatafilter':
			case 'addressdatafilter':
				display_header('Filter out what validation should ignore', 'filter');
				// if we have own filters, use them (no merging currently!)
				if (isset($own_info[$name])) $data = $own_info[$name];
				// create a header
				echo "<tr class='$name datafilter-header'><td colspan='2'>".
					htmlspecialchars(ucfirst($name))."</td>";
				// one empty extra line to add a new filter
				$data[] = ['value' => ''];
				foreach($data as $n => $entry)
				{
					if (!empty($entry['comment']))
					{
						echo "<tr class='comment'><td colspan='2'>".
							htmlspecialchars($entry['comment'])."</td></tr>\n";
					}
					echo "<tr class='$name'><td></td>";
					echo "<td><input name='".htmlspecialchars($name.'['.$n.']')."' value='".
						htmlspecialchars($entry['value'])."'/></td></tr>\n";
				}
				break;

			default:
				$value = isset($discovered[$name]) ? $discovered[$name] :
					(isset($own_info[$name]) ? $own_info[$name]['value'] : $data['value']);
				echo "<tr><td>".htmlspecialchars($data['name'])."</td>\n";
				echo "\t<td><input id='".htmlspecialchars($name)."' name='".htmlspecialchars($name)."' ".
					(isset($name2input_attrs[$name]) ? $name2input_attrs[$name] : '').
					(isset($discovered[$name]) ? " class='discovered'" : '').
					" value='".htmlspecialchars($value)."'/></td></tr>\n";
				if ($name === 'host')
				{
					echo "<tr><td></td>\n";
					echo "\t<td><input type='button' value='Discover settings from above host' onclick='".
						'location.href="/discover.php?host="+encodeURIComponent(getElementById("host").value);'.
						"'/></td></tr>\n";
				}
				break;
		}
	}
	echo "</table>\n";
	echo "<div class='topmenu' id='serverinfomenu'>\n";
	foreach(['save' => 'Save', 'apply' => 'Apply', 'cancel' => 'Cancel', 'download' => 'Download'] as $name => $label)
	{
		echo "<input type='submit' name='button[$name]' value='$label'/>\n";
	}
	echo "<input type='button' value='Upload' onclick='jQuery(\"input[name=upload]\").click();' title='Upload your serverinfo.xml'/>\n";
	echo "<input type='file' name='upload' onchange='this.form.submit();' accept='.xml,application/xml' style='display:none'/>\n";
	echo "</div></form>\n";
	echo "</body>\n</html>\n";
}

function display_features(array $features, array $own_features=null, array $discovered=null)
{
	foreach($features as $name => $data)
	{
		if (!empty($data['comment']))
		{
			echo "<tr class='comment'><td colspan='2'>".htmlspecialchars($data['comment'])."</td></tr>\n";
		}
		// if we have own features, not existing features are considered disabled (commented out!)
		$value = isset($discovered[$name]) ? $discovered[$name] :
			(!isset($own_features) ? $data['enabled'] :
			(isset($own_features[$name]) ? $own_features[$name]['enabled'] : false));

		echo "<tr class='feature'><td><label><input name='features[".htmlspecialchars($name)."]' type='checkbox' ".
			(isset($discovered[$name]) ? " class='discovered' " : '').
			($value ? 'checked' : '')."/>".
			htmlspecialchars($name)."</label></td>\n";

		echo "\t<td class='description'><label for='".htmlspecialchars($name)."'>".
			htmlspecialchars($data['description'])."</label></td></tr>\n";
	}
}

function display_substitutions(array $substitutions, array $own_substitutions=null, array $discovered=null)
{
	foreach($substitutions as $name => $data)
	{
		if (!empty($data['comment']))
		{
			echo "<tr class='comment'><td colspan='2'>".htmlspecialchars($data['comment'])."</td></tr>\n";
		}
		if (isset($data['repeats']))
		{
			$count = isset($own_substitutions[$name]['count']) ?
				$own_substitutions[$name]['count'] : $data['count'];
			echo "<tr class='repeats'><td>Number:</td><td><input name='substitutions[repeats][$name]'".
				" type='number' min='2' max='100'".
				" value='".htmlspecialchars($count)."'/></td></tr>\n";

			display_substitutions($data['repeats'],
				isset($own_substitutions[$name]['repeats']) ? $own_substitutions[$name]['repeats'] : null,
				$discovered);
		}
		else
		{
			$value = isset($discovered[$name]) ? $discovered[$name] :
				(isset($own_substitutions[$name]) ? $own_substitutions[$name]['value'] : $data['value']);
			echo "<tr class='substitution'><td>".htmlspecialchars($name)."</td>\n";
			echo "\t<td><input name='substitutions[".htmlspecialchars($name).
				"]' value='".htmlspecialchars($value)."'".
				(isset($discovered[$name]) ? " class='discovered'" : '').
				"/></td></tr>\n";
		}
	}
}

function display_header($name, $id)
{
	static $active=null;

	if ($name !== $active)
	{
		echo "<tr id='".htmlspecialchars($id)."' class='accordionHeader'>".
			"<th colspan='2'>".htmlspecialchars($name)."</th></tr>\n";
		$active = $name;
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
					'comment' => $node->previousSibling->previousSibling->nodeName === '#comment' ?
						$node->previousSibling->previousSibling->nodeValue : null,
				);
				break;

			default:
				$values[$node->nodeName] = array(
					'name'  => $node->nodeName,
					'value' => $node->nodeValue,
					'node'  => $add_node ? $node : null,
					'comment' => $node->previousSibling->previousSibling->nodeName === '#comment' ?
						$node->previousSibling->previousSibling->nodeValue : null,
				);
		}
	}
	//echo "$path<pre>".print_r($values, true)."</pre>\n";
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
