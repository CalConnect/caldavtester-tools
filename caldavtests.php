<?php
/**
 * Analyse and display logs of CalDAVTester
 *
 * @author Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 *
 * @link https://www.calendarserver.org/CalDAVTester.html
 * @link https://github.com/CalConnect/caldavtester-tools
 */

// configuration
$caldavtester_dir = realpath('../CalDAVTester');
$caldavtester = 'PYTHONPATH=pycalendar/src '.$caldavtester_dir.'/testcaldav.py --print-details-onfail --observer jsondump';
$serverinfo = $caldavtester_dir.'/serverinfo.xml';
$testspath = 'scripts/tests/';	// must be stripped off when calling testcaldav.py
$db_path = $caldavtester_dir.'/results.sqlite';

// path to git sources to automatic get branch&revision, you can also hardcode something here
$git_source = realpath('../egroupware');
$branch = trim(exec("hash git && cd $git_source >/dev/null 2>&1 && git branch --no-color  2>/dev/null | sed -e '/^[^*]/d' -e \"s/* \(.*\)/\\1/\""));
$revision = exec("hash git && cd $git_source >/dev/null 2>&1 && git rev-parse --short HEAD &2>/dev/null");

if (!file_exists($caldavtester_dir) || !file_exists($caldavtester_dir.'/testcaldav.py'))
{
	usage(4, 'You need to configure $caldavtester_dir with the directory of your CalDAVTester directory!');
}

$db = setup_db($db_path);

// controller for command line
if (php_sapi_name() == 'cli')
{
	$options = getopt("h", array(
		'help',
		'branch:',	// specify branch, always required
		'revision::',// required only to import logs
		'results',	// aggregate results by scripts incl. success/failure
		'scripts::',// list existing scripts for given script, feature or (default) all
		'features',	// list features incl. enabled state in serverinfo.xml
		'import::',	// json to import
		'run::',		// run tests for given script, feature or (default) all
		'delete:',	// delete tests results for given script, feature or all
		'result-details::',	// list full results (optional of given script)
	));
	if (isset($options['h']) || isset($options['help']))
	{
		usage();
	}
	if (!isset($options['branch']))
	{
		$options['branch'] = $branch;
	}
	if (!isset($options['revision']))
	{
		$options['revision'] = $revision;
	}
	if (isset($options['import']))
	{
		if (empty($options['revision'])) usage(1, "Revision parameter --revision=<revision> is required!");
		if (!file_exists($_file)) usage(2, "File '$_file' not found!");
		import($options['branch'], $options['revision'], $options['import'])."\n";
	}
	elseif (isset($options['results']))
	{
		display_results($options['branch']);
	}
	elseif (isset($options['result-details']))
	{
		display_result_details($options['branch'], $options['result-details']);
	}
	elseif (isset($options['scripts']))
	{
		display_scripts($options['scripts']);
	}
	elseif (isset($options['features']))
	{
		display_features();
	}
	elseif (isset($options['delete']))
	{
		if (empty($options['delete'])) usage(3, "You need to specify what so delete: <script-name>, <feature> or all!");
		delete($options['branch'], $options['delete']);
	}
	elseif (isset($options['run']))
	{
		if (empty($options['revision'])) usage(1, "Revision parameter --revision=<revision> is required!");
		run($options['branch'], $options['revision'], $options['run']);
	}
	else
	{
		usage();
	}
	exit;
}

// run via webserver
display_results($_REQUEST['branch'], true);	// true = return html
exit;

/**
 * from here on only functions called from above controlers for cli or webserver
 */

/**
 * Display usage incl. optional error-message and exit(!)
 *
 * @param int $exit_code
 * @param string $error_msg
 */
function usage($exit_code=0, $error_msg='')
{
	global $branch, $revision;

	if ($error_msg)
	{
		echo "\n\n$error_msg\n\n";
	}
	$cmd = basename($_SERVER['argv'][0]);
	echo "Usage: php $cmd\n";
	echo "--results [--branch=<branch> (default '$branch')]\n";
	echo "  Aggregate results by script incl. number of tests success/failure/percentage\n";
	echo "--run[=(<script-name>|<feature>|default(default)|all)] [--branch=<branch> (default '$branch')] [--revision <revision> (default '$revision')]\n";
	echo "  Run tests of given script, all scripts requiring given feature, default (enabled and not ignore-all taged) or all\n";
	echo "--result-details[=(<script-name>|<feature>|default|all(default)] [--branch=<branch> (default 'trunk')]\n";
	echo "  List result details incl. test success/failure/logs\n";
	echo "--delete=(<script>|<feature>|all) [--branch=(<branch>|all) (default '$branch')]\n";
	echo "  Delete test results of given script, all scripts requiring given feature or all\n";
	echo "--import=<json-to-import>  [--revision=<revision> (default '$revision')] [--branch=<branch> (default '$branch')]\n";
	echo "  Import a log as jsondump created with testcaldav.py --print-details-onfail --observer jsondump\n";
	echo "--scripts[=<script-name>|<feature>|default|all (default)]\n";
	echo "  List scripts incl. required features for given script, feature, default (enabled and not ignore-all taged) or all\n";
	echo "--features\n";
	echo "  List features incl. if they are enabled in serverinfo\n";
	echo "--help|-h\n";
	echo "  Display this help message\n";

	exit($exit_code);
}

/**
 * Display available scripts incl. results
 *
 * @param string $branch
 * @param boolean $html
 */
function display_results($branch, $html=false)
{
	$line = 0;
	foreach(get_script_results($branch) as $script)
	{
		if (!$line++)
		{
			if (!$html)
			{
				echo "Percent\tSuccess\tFailed\tScript\t(Features)\tFile\n";
			}
		}
		if (empty($script['description']))
		{
			$script['description'] = strtr($script['name'], array(
				'/' => ' ',
				'.xml' => '',
			));
		}
		if ($script['ignore-all']) $script['require-feature'][] = 'ignore-all';

		if (!$html)
		{
			echo "$script[percent]\t$script[success]\t$script[failed]\t$script[description] (".
				implode(', ', $script['require-feature']).")\t".$script['name']."\n";
			continue;
		}
		// todo html
	}
}

/**
 * Run (and record) tests for given feature, script, "default" or all tests
 *
 * @global string $caldavtester
 * @param string $branch
 * @param string $revision
 * @param string $what ='default'
 */
function run($branch, $revision, $what='default')
{
	global $caldavtester;

	if ($what === false) $what = 'default';	// default of optional argument

	foreach(scripts($what, true) as $script)
	{
		$cmd = $caldavtester.' '.escapeshellarg($script);
		error_log($cmd);
		if (($fp = popen($cmd, 'r')))
		{
			import($branch, $revision, $fp);
			fclose($fp);
		}
	}
}

/**
 * Get scripts for given script, feature, "default" or "all"
 *
 * Unless a script is explicit given or "all", disabled scripts or scripts with ignore-all are ignored.
 *
 * @param string|boolean $what ='default' script-name, feature, "default" or "all"
 * @param boolean $return_name ='all' return just arreay of names or name => array with full infomation pairs
 * @return array
 */
function scripts($what='all', $return_name=false)
{
	if ($what === false) $what = 'all';	// default of optinal argument

	$features = get_features();

	$scripts = array();
	foreach(get_scripts() as $script)
	{
		if ($what === 'all')
		{
			$scripts[$script['name']] = $script;
			continue;
		}

		if ($script['name'] === $what)
		{
			$scripts[$script['name']] = $script;
			break;
		}
		// only run these if explicitly called
		if ($script['ignore-all']) continue;

		// only run script, if feature is explicit given or enabled
		foreach($script['require-feature'] as $feature)
		{
			if ($feature !== $what && (!isset($features[$feature]) || !$features[$feature]['enabled'])) continue 2;
		}

		// run for default or scripts having given feature
		if ($what === 'default' || in_array($what, $script['require-feature']))
		{
			$scripts[$script['name']] = $script;
		}
	}
	return $return_name ? array_keys($scripts) : $scripts;
}

/**
 * Display available scripts incl. description and required features
 *
 * Unless a script is explicit given or "all", disabled scripts or scripts with ignore-all are ignored.
 *
 * @param string $what ="all" script-name, feature, "default" or "all"
 */
function display_scripts($what='all')
{
	if ($what === false) $what = 'all';

	echo str_pad("Script", 39)."\tDescription (Required features)\n";
	foreach(scripts($what) as $script)
	{
		if (empty($script['description']))
		{
			$script['description'] = strtr($script['name'], array(
				'/' => ' ',
				'.xml' => '',
			));
		}
		if ($script['ignore-all']) $script['require-feature'][] = 'ignore-all';

		echo str_pad($script['name'], 39)."\t".$script['description']." (".implode(', ', $script['require-feature']).")\n";
	}
}

/**
 * Display all recorded results
 *
 * @global PDO $db
 * @param string $branch
 * @param string $what ="all" script-name, feature, "default" or "all" (see scripts)
 * @param boolean $html
 */
function display_result_details($branch, $what='all', $html=false)
{
	global $db;

	$select = $db->prepare('SELECT results.*,scripts.label AS script_label,scripts.details AS script_details,suites.label AS suite_label
FROM results
JOIN labels AS scripts ON results.script=scripts.id
JOIN labels AS suites ON results.suite=suites.id
WHERE branch=:branch'.limit_script_sql($what).'
ORDER BY script,suite,test');
	$select->setFetchMode(PDO::FETCH_ASSOC);
	if ($select->execute(array(
		'branch' => label2id($branch),
	)))
	{
		foreach($select as $result)
		{
			if (!$html)
			{
				echo "\n$result[script_label]\t$result[suite_label]\t$result[test]\t$result[success]\t$result[failed]\t$result[first_failed]\n$result[details]\n";
				continue;
			}
		}
	}
}


/**
 * Delete recorded results for given branch and script(s)
 *
 * @global PDO $db
 * @param string $branch
 * @param string $what ="all" script-name, feature, "default" or "all" (see scripts)
 */
function delete($branch, $what)
{
	global $db;

	$delete = $db->prepare($sql='DELETE FROM results WHERE branch=:branch'.limit_script_sql($what));

	$delete->execute(array(
		'branch' => label2id($branch),
	));
	echo $delete->rowCount()." results deleted.\n";
}

/**
 * Return sql to limit scripts to given script-name, feature, "default" or all
 *
 * @param string $what ="all"
 * @return string
 */
function limit_script_sql($what="all")
{
	$limit_script_sql = '';
	if ($what !== false && $what !== 'all')
	{
		$script_ids = array();
		foreach(scripts($what, true) as $name)
		{
			$script_ids[] = label2id($name);
		}
		$limit_script_sql = ' AND script IN ('.implode(',', $script_ids).')';
	}
	return $limit_script_sql;
}

/**
 * Query script results from db
 *
 * @global PDO $db
 * @param string $branch
 * @return Iterator{array} with values for keys name, description, success, failure, percent
 */
function get_script_results($branch)
{
	global $db;
	$select = $db->prepare(
'SELECT script,scripts.details AS description,
	scripts.label AS name,COUNT(success) AS success,COUNT(failed) AS failed,
	ROUND(100.0*COUNT(success)/COUNT(*),1) AS percent
FROM results
JOIN labels AS scripts ON results.script=scripts.id
JOIN labels AS suites ON results.suite=suites.id
WHERE branch=:branch
GROUP BY script
ORDER BY percent DESC,description ASC');
	$select->setFetchMode(PDO::FETCH_ASSOC);
	if (!$select->execute(array(
		'branch' => label2id($branch),
	)))
	{
		throw new Exception('Error executing query!');
	}

	// merge in features and calculate total
	$scripts = get_scripts();
	$success = $failed = 0;
	$results = array();
	foreach($select as $script)
	{
		if (isset($scripts[$script['name']]))
		{
			$script = array_merge($script, $scripts[$script['name']]);
		}
		else
		{
			throw new Exception("Results for unknown script-name '$script[name]' found!");
		}
		$success += $script['success'];
		$failed  += $script['failed'];
		$results[$script['name']] = $script;
	}
	// add total to results
	if ($results)
	{
		$results['total'] = array(
			'percent' => number_format(100.0*$success/($success+$failed),1),
			'success' => $success,
			'failed'  => $failed,
			'description' => 'Total',
			'name' => '',
			'ignore-all' => false,
			'require-feature' => array(),
		);
	}
	return $results;
}

/**
 * Import given file into SQLite database
 *
 * @global PDO $db
 * @param string $_branch
 * @param string $_revision
 * @param string|resource $_file path or open filepointer
 */
function import($_branch, $_revision, $_file)
{
	global $db, $testspath;

	$scripts = json_decode(is_resource($_file) ? stream_get_contents($_file) : file_get_contents($_file), true);
	//print_r($scripts);

	$branch = label2id($_branch, '***branch***');
	$revision = is_numeric($_revision) ? $_revision : label2id($_revision, '***revision***');

	$updated = $inserted = $succieded = $failed = $new_failures = 0;
	$insert = $update = $select = null;
	foreach($scripts as $script)
	{
		// strip $testspath
		if (strpos($script['name'], $testspath) === 0) $script['name'] = substr($script['name'], strlen($testspath));

		$script_id = label2id($script['name'], $script['details']);
		foreach($script['tests'] as $suite)
		{
			$suite_id = label2id($suite['name'], '***suite***');
			if (!$suite['tests']) echo "$script[name] ($script_id)\t$suite[name] ($suite_id)\t$suite[result]\n";
			foreach($suite['tests'] as $test)
			{
				echo "$script[name] ($script_id)\t$suite[name] ($suite_id)\t$test[name]\t$test[result]\n$test[details]";
				if (!isset($select))
				{
					$select = $db->prepare('SELECT * FROM results WHERE branch=:branch AND script=:script AND suite=:suite AND test=:test');
					$select->setFetchMode(PDO::FETCH_ASSOC);
				}
				if ($select->execute($where = array(
					'branch' => $branch,
					'script' => $script_id,
					'suite'  => $suite_id,
					'test'   => $test['name'],
				)) && ($result = $select->fetch()))
				{
					//print_r($result);
				}
				$data = array('updated' => date('Y-m-d H:i:s'));
				if (!$test['result'])	// success
				{
					$data['success'] = $revision;
					$data['failed'] = $data['first_failed'] = $data['details'] = null;
					$succieded++;
				}
				else	// failure
				{
					$data['failed'] = $revision;
					$data['details'] = $test['details'];
					if (!$result || !$result['first_failed'])
					{
						$data['first_failed'] = $revision;
						$new_failures++;
					}
					$failed++;
				}
				if ($result)
				{
					if (!isset($update)) $update = $db->prepare('UPDATE results SET success=:success,first_failed=:first_failed,failed=:failed,details=:details,updated=:updated WHERE branch=:branch AND script=:script AND suite=:suite AND test=:test');
					$update->execute(array_merge($result, $data));
					$updated++;
				}
				else
				{
					if (!isset($insert)) $insert = $db->prepare('INSERT INTO results (branch,script,suite,test,success,first_failed,failed,details,updated) VALUES (:branch,:script,:suite,:test,:success,:first_failed,:failed,:details,:updated)');
					$insert->execute(array_merge(array('success' => null), $where, $data));
					$inserted++;
				}
			}
		}
	}
	error_log("$new_failures new failures, $failed total failures, $succieded tests succieded ($updated tests updated, $inserted newly inserted)");
}

/**
 * Get all available scripts incl. required features
 *
 * @return array script-name (relativ to $caldavtester_dir.'/'.$testspath) => array of required features pairs
 */
function get_scripts()
{
	global $caldavtester_dir, $testspath;

	$scripts = array();
	foreach(glob($caldavtester_dir.'/'.$testspath.'*/*.xml') as $path)
	{
		$name = substr($path, strlen($caldavtester_dir.'/'.$testspath));
		$xml = simplexml_load_file($path);
		$features = array();
		foreach($xml->{'require-feature'}[0] as $feature)
		{
			$features[] = (string)$feature;
		}
		$scripts[$name] = array(
			'name' => $name,
			'description' => preg_replace('/[ \t\n]+/', ' ', (string)$xml->description),
			'require-feature' => $features,
			'ignore-all' => (string)$xml['ignore-all'] === 'yes',
		);
	}
	return $scripts;
}

/**
 * Display features
 */
function display_features()
{
	$features = get_features();

	$max_name_len = 32;
	foreach($features as $feature)
	{
		if (($len = strlen($feature['name'])) > $max_name_len) $max_name_len = $len;
	}
	echo "Enabled\t".str_pad('Name', $max_name_len)."\tDescription\n";
	foreach($features as $feature)
	{
		echo ($feature['enabled']?'yes':'no')."\t".str_pad($feature['name'], $max_name_len)."\t$feature[description]\n";
	}
}

/**
 * Get features defined in serverinfo and there enabled/disabled state
 *
 * @return array feature => array(name, enabled, description)
 */
function get_features()
{
	global $serverinfo;

	if (!($xml = file_get_contents($serverinfo)))
	{
		throw new execption("Serverinfo '$serverinfo' NOT found!");
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
 * Get or create id for label
 *
 * @param string $label
 * @param string $details =null
 * @return int numeric id for given label
 */
function label2id($label, $details=null)
{
	global $db;
	static $labels = array();
	static $select = null;
	static $insert = null;
	static $update = null;

	$id =& $labels[$label];
	if (!isset($id))
	{
		if (!isset($select)) $select = $db->prepare('SELECT id,details FROM labels WHERE label=:label');
		if ($select->execute(array(
			'label' => $label,
		)))
		{
			$id = $select->fetchColumn();
			// are new details are given --> update them (probably only applying to scripts)
			if (isset($details) && $details !== (string)$select->fetchColumn(1))
			{
				if (!isset($update)) $update = $db->prepare('UPDATE labels SET details=:details WHERE id=:id');
				$update->execute(array(
					'id'  => $id,
					'details' => (string)$details !== '' ? $details : null,
				));
			}
		}
		if (!isset($id) || !($id > 0))
		{
			if (!isset($insert)) $insert = $db->prepare('INSERT INTO labels (label,details) VALUES (:label,:details)');
			if ($insert->execute(array(
				'label'  => $label,
				'details' => (string)$details !== '' ? $details : null,
			)))
			{
				$id = $db->lastInsertId('id');
			}
		}
		if (!isset($id) || !($id > 0))
		{
			throw new Exception ("Could not get an ID for label '$label'!");
		}
	}
	return $id;
}

/**
 * Open or if necessare create SQLite database
 *
 * @param string $_db_path
 * @return \PDO
 */
function setup_db($_db_path)
{
	$create_table = !file_exists($_db_path);
	$db = new PDO('sqlite:'.$_db_path);
	if ($create_table)
	{
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$db->exec('CREATE TABLE IF NOT EXISTS labels (
			id integer primary key,
			label varchar(128),
			details varchar(255)
		)');
		$db->exec("INSERT INTO labels (id,label,details) VALUES(1,'1.0','***version***')");
		$db->exec('CREATE TABLE IF NOT EXISTS results (
			branch integer,
			script integer,
			suite integer,
			test integer,
			success integer DEFAULT NULL,
			first_failed integer DEFAULT NULL,
			failed integer DEFAULT NULL,
			details text,
			updated timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY(branch,script,suite,test)
		)');
	}
	//error_log('schema_version='.$db->query('SELECT label FROM labels WHERE id=1')->fetchColumn());

	return $db;
}

/**
 * Check if we have a $git_source path set and can get a revision and path from there
 */
function default_revision()
{

}