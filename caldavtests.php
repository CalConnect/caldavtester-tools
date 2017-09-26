<?php
/**
 * Analyse and display logs of CalDAVTester
 *
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 *
 * @link https://www.calendarserver.org/CalDAVTester.html
 * @link https://github.com/CalConnect/caldavtester-tools
 */

// change to directory of script (not done automatic for cli), so all pathes are relative from here
chdir(__DIR__);

// configuration
$caldavtester_dir = realpath('..');
$caldavtester = "cd $caldavtester_dir; PYTHONPATH=pycalendar/src ./testcaldav.py --print-details-onfail --observer jsondump";
$serverinfo = $caldavtester_dir.'/serverinfo.xml';
$testspath = 'scripts/tests/';	// must be stripped off when calling testcaldav.py
$db_path = $caldavtester_dir.'/results.sqlite';

// path to git sources to automatic get branch&revision, you can also hardcode default branch&revision here
$git_sources = realpath($caldavtester_dir.'/../egroupware');
$branch = 'master';
$revision = '';
// to link revisions to commits, get autodetected for Github.com
$commit_url = '';	// equivalent of 'https://github.com/EGroupware/egroupware/commit/';
config_from_git($git_sources);

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
		'git-sources::',	// path to git sources to automatic determine branch&revision
		'serverinfo::',	// path to serverinfo.xml
		'testeroptions::',	// extra options to pass to caldavtester
		'gui::',
	));
	if (isset($options['h']) || isset($options['help']))
	{
		usage();
	}
	if (isset($options['serverinfo']))
	{
		if (!file_exists($options['serverinfo']))
		{
			usage(5, "Specified serverinfo '$options[serverinfo]' not found!");
		}
		$serverinfo = realpath($options['serverinfo']);
		$caldavtester .= ' -s '.escapeshellarg($serverinfo);
	}
	if (isset($options['testeroptions']))
	{
		$caldavtester .= ' '.$options['testeroptions'];
	}
	if (isset($options['git-sources']))
	{
		if (!@file_exists(realpath($options['git-sources'].'/.git')))
		{
			usage(6, "Specified Git sources '$options[serverinfo]/.git' not found!");
		}
		$git_sources = realpath($options['git-sources']);
		config_from_git($git_sources);
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
		import($options['branch'], $options['revision'], $options['import']);
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
	elseif (isset($options['gui']))
	{
		if (empty($options['gui'])) $options['gui'] = 'localhost:8080';
		$cmd = 'php -S '.escapeshellarg($options['gui']).' -t '.__DIR__.' '.__FILE__;
		error_log($cmd."\n");
		error_log("Go to http://$options[gui]/ or use Ctrl C to stop WebGUI.\n");
		exec($cmd);
	}
	else
	{
		usage();
	}
	exit;
}

// run via (buildin) webserver
if ($_SERVER['PHP_SELF'] != '/' && substr($_SERVER['PHP_SELF'], -4) !== '.php')
{
	return false;	// let cli webserver deal with static content
}
// fetch test scripts
if(!empty($_REQUEST['script']))
{
	header('Content-Type: application/xml; charset=utf-8');
	if (file_exists($script=$caldavtester_dir.'/'.$testspath.'/'.str_replace('../', '', $_REQUEST['script'])))
	{
		header('Content-Length: '.filesize($script));
		header('ETag: "'.filemtime($script).'"');
		readfile($script);
	}
	else
	{
		header("HTTP/1.1 404 Not Found");
	}
}
elseif (!empty($_REQUEST['result']))
{
	display_result_details(!empty($_REQUEST['branch']) ? $_REQUEST['branch'] : $branch,
		$_REQUEST['result'], true);
}
elseif (!empty($_REQUEST['run']))
{
	$output = array();
	exec($cmd='php ./caldavtests.php --serverinfo '.escapeshellarg($serverinfo).
		(!empty($options['testeroptions']) ? ' --testeroptions '.$options['testeroptions'] : '').
		(!empty($_REQUEST['branch']) ? escapeshellarg('--branch='.$_REQUEST['branch']).' ' : '').
		escapeshellarg('--run='.$_REQUEST['run']), $output, $ret);
	error_log($cmd.' returned '.$ret);

	// return results
	display_result_details(!empty($_REQUEST['branch']) ? $_REQUEST['branch'] : $branch,
		$_REQUEST['run'], true);
}
elseif (!empty($_REQUEST['update']) && isset($_REQUEST['notes']))
{
	if (preg_match('/^\d+-\d+-\d+-\d+$/', $_REQUEST['update']))
	{
		update($_REQUEST['update'], 'notes', $_REQUEST['notes']);
	}
}
else
{
	display_results($branch, true);	// true = return html
}
exit;

/**
 * from here on only functions called from above controlers for cli or webserver
 */

/**
 * Guess config from git clone
 *
 * @param string $git_sources
 */
function config_from_git($git_sources)
{
	global $branch, $revision, $commit_url;

	// only run git stuff, if sources exist, are git and git cli is available
	if (@file_exists($git_sources.'/.git') && !exec("hash git 2>/dev/null"))
	{
		$branch = trim(exec("cd $git_sources >/dev/null 2>&1 && git branch --no-color  2>/dev/null | sed -e '/^[^*]/d' -e \"s/* \(.*\)/\\1/\""));
		$revision = exec("cd $git_sources >/dev/null 2>&1 && git rev-parse --short HEAD &2>/dev/null");
		$matches = null;
		if (empty($commit_url) &&
			($remote = exec("cd $git_sources >/dev/null 2>&1 && git remote -v &2>/dev/null")) &&
			preg_match('/(git@github.com:|https:\/\/github.com\/)(.*)\.git/i', $remote, $matches))
		{
			$commit_url = 'https://github.com/'.$matches[2].'/commit/';
		}
	}
}

/**
 * Display usage incl. optional error-message and exit(!)
 *
 * @param int $exit_code
 * @param string $error_msg
 */
function usage($exit_code=0, $error_msg='')
{
	global $branch, $revision, $serverinfo, $git_sources;

	if ($error_msg)
	{
		echo "\n\n$error_msg\n\n";
	}
	$cmd = basename($_SERVER['argv'][0]);
	echo "Usage: php $cmd\n";
	echo "--results [--branch=<branch> (default '$branch')]\n";
	echo "  Aggregate results by script incl. number of tests success/failure/percentage\n";
	echo "--run[=(<script-name>|<feature>|default(default)|all)] [--branch=<branch> (default '$branch')] [--revision=<revision> (default '$revision')]\n";
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
	echo "--serverinfo\n";
	echo "  Path to serverinfo.xml to use, default '$serverinfo'\n";
	echo "--testeroptions <some-options>\n";
	echo "  Pass arbitrary options to caldavtester.py, eg. '--ssl'\n";
	echo "--git-sources\n";
	echo "  Path to sources to use Git to automatic determine branch&revision, default '$git_sources'\n";
	echo "--gui[=[<bind-addr> (default localhost)][:port (default 8080)]]\n";
	echo "  Run WebGUI: point your browser at given address, default http://localhost:8080/\n";
	echo "--help|-h\n";
	echo "  Display this help message\n";

	exit($exit_code);
}

/**
 * Display available scripts incl. results
 *
 * @param string $branch
 * @param boolean $html =false
 */
function display_results($branch, $html=false)
{
	global $commit_url;

	if (!$html)
	{
		echo "Percent\tSuccess\tFailed\tScript\t(Features)\tFile\tUpdated\tTime\n";
	}
	else
	{
		$etag = check_send_etag($branch);
		html_header();
		echo "<table class='results' data-commit-url='".htmlspecialchars($commit_url)."' data-etag='".htmlspecialchars($etag)."'>\n";
		echo "<tr class='header'><th></th><th>Percent</th><th>Success</th><th>Failed</th><th>Script (Features)</th><th>File</th><th>Updated</th><th>Time</th></tr>\n";
	}
	foreach(get_script_results($branch) as $script)
	{
		if (empty($script['description']))
		{
			$script['description'] = strtr($script['name'], array(
				'/' => ' ',
				'.xml' => '',
			));
		}
		if ($script['ignore-all']) $script['require-feature'][] = 'ignore-all';

		$script['time'] = isset($script['time']) ? number_format($script['time'], 2, '.', '') : '';

		if (!$html)
		{
			echo "$script[percent]\t$script[success]\t$script[failed]\t$script[description] (".
				implode(', ', $script['require-feature']).")\t$script[name]\t$script[updated]\t$script[time]\n";
			continue;
		}
		// todo html
		if (empty($script['name']))
		{
			echo "<tr class='footer'><td></td>";
		}
		else
		{
			echo "<tr id='".htmlspecialchars($script['name'])."' class='".
				($script['percent']==100.0?'green':($script['percent'] < 50.0 ? 'red' : 'yellow'))."'>".
				"<td class='expand'></td>";
		}
		echo "<td class='percent'>".htmlspecialchars($script['percent']).
			"</td><td class='success'>".htmlspecialchars($script['success']).
			"</td><td class='failed'>".htmlspecialchars($script['failed']).
			"</td><td>".htmlspecialchars($script['description']).
				($script['require-feature'] ? ' ('.htmlspecialchars(implode(', ', $script['require-feature'])).')' : '').
			"</td><td class='script'>".htmlspecialchars($script['name']).
			"</td><td class='updated'>".htmlspecialchars(substr($script['updated'], 0, -3)).
			"</td><td class='time'>".htmlspecialchars($script['time']).
			"</td><tr>\n";
	}
	if ($html)
	{
		echo "</table>\n</body>\n</html>\n";
	}
}

/**
 * Output html header incl. body tag
 */
function html_header()
{
	echo "<html>\n<head>\n";
	echo "\t<title>CalDAVTester GUI</title>\n";
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
 * Check if request contains a If-None-Match header and send 314 Not Matched or ETag header
 *
 * Function does NOT return, if If-None-Match matches current ETag.
 *
 * @param string $branch
 * @param string $what =null eg. script-name, see limit_script_sql
 * @return string etag send as header
 */
function check_send_etag($branch, $what=null)
{
	global $db;

	$branch_id = label2id($branch);

	// using MAX(update)+SUM(time) as ETag
	$get_etag = $db->prepare($sql="SELECT MAX(updated)||' '||COALESCE(SUM(time),'') FROM results WHERE branch=:branch".
		(empty($what) ? '' : limit_script_sql($what)));
	$etag = $get_etag->execute(array('branch' => $branch_id)) ? $get_etag->fetchColumn() : null;
	//error_log(__METHOD__."('$branch', '$what') sql='$sql', etag='$etag'");

	// process If-None-Match header used to poll running script results
	if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && substr($_SERVER['HTTP_IF_NONE_MATCH'], 1, -1) == $etag)
	{
		header('HTTP/1.1 304 Not Modified');
		exit;
	}
	header('ETag: "'.$etag.'"');

	return $etag;
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

	if ($html) $etag = check_send_etag($branch, $what);

	$select = $db->prepare("SELECT results.*,scripts.label AS script_label,scripts.details AS script_details,suites.label AS suite_label,
branch.label AS branch_label,
COALESCE(success.label,success) AS success_revision,
COALESCE(failed.label,failed) AS failed_revision,
COALESCE(first_failed.label,first_failed) AS first_failed_revision
FROM results
JOIN labels AS branch ON results.branch=branch.id AND branch.details='***branch***'
JOIN labels AS scripts ON results.script=scripts.id
JOIN labels AS suites ON results.suite=suites.id
LEFT JOIN labels AS success ON results.success=success.id AND success.details='***revision***'
LEFT JOIN labels AS failed ON results.failed=failed.id AND failed.details='***revision***'
LEFT JOIN labels AS first_failed ON results.first_failed=first_failed.id AND first_failed.details='***revision***'
WHERE branch=:branch".limit_script_sql($what).'
ORDER BY script,suite,test');
	$select->setFetchMode(PDO::FETCH_ASSOC);
	if ($select->execute(array(
		'branch' => label2id($branch),
	)))
	{
		if ($html)
		{
			echo "<table class='details' data-etag='".htmlspecialchars($etag)."'>\n";
			echo "<tr class='header'><th class='expandAll'></th><th>Script</th><th>Suite</th><th>Test</th><th>Branch</th>".
				"<th>Last success</th><th>Failed</th><th>First failed</th><th>Time</th><th title='Notes' class='notes'>N</th></tr>\n";
		}
		else
		{
			echo "Script\t\t\tSuite\t\t\tTest\tBranch\tLast success\tFailed\tFirst failed\tTime\n\n";
		}
		foreach($select as $result)
		{
			$result['time'] = isset($result['time']) ? number_format($result['time'], 2, '.', '') : '';
			if (!$html)
			{
				echo "\n$result[script_label]\t$result[suite_label]\t$result[test]\t$result[branch_label]\t$result[success_revision]\t$result[failed_revision]\t$result[first_failed_revision]\t$result[time]\n";
				if (!empty($result['details'])) echo "$result[details]\n";
				continue;
			}
			if (!empty($result['failed']))
			{
				echo '<tr class="red">';
			}
			elseif (!empty($result['success']))
			{
				echo '<tr class="green">';
			}
			else
			{
				echo '<tr class="ignored">';
			}
			if (!empty($result['details']) || !empty($result['protocol']))
			{
				echo '<td class="expand">';
			}
			else
			{
				echo '<td>';
			}

			echo "</td><td>".htmlspecialchars($result['script_label']).
				"</td><td>".htmlspecialchars($result['suite_label']).
				"</td><td>".htmlspecialchars($result['test']).
				"</td><td>".htmlspecialchars($result['branch_label']).
				"</td><td class='revision'>".htmlspecialchars($result['success_revision']).
				"</td><td class='revision'>".htmlspecialchars($result['failed_revision']).
				"</td><td class='revision'>".htmlspecialchars($result['first_failed_revision']).
				"</td><td class='time' title='".htmlspecialchars($result['updated'])."'>".htmlspecialchars($result['time']).
				"</td><td class='".(empty($result['notes']) ? 'noNotes' : 'haveNotes')."'>".
				"</td></tr>\n";

			if (!empty($result['details']) || !empty($result['protocol']))
			{
				echo '<tr style="display:none" class="details"><td></td><td colspan="8" class="output">'.htmlspecialchars($result['details']).htmlspecialchars($result['protocol'])."</td></tr>\n";
			}
			echo '<tr style="display:none" class="notes" id="'.htmlspecialchars($result['branch'].'-'.$result['script'].'-'.$result['suite'].'-'.$result['test']).
				'"><td/><td colspan="7"><textarea class="notes">'.htmlspecialchars($result['notes'])."</textarea></td>".
				"<td colspan='2' class='updateNotes'><button>Update</button></tr>\n";
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
	ROUND(100.0*COUNT(success)/(COUNT(success)+COUNT(failed)),1) AS percent,
	SUM(time) AS time,MAX(updated) AS updated
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
			'updated' => '',
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
 * @return string|null content of json file, if not understood, eg. Python trace on error
 */
function import($_branch, $_revision, $_file)
{
	global $db, $testspath;

	static $prefix = '[{"result": null, "tests":';

	$json = is_resource($_file) ? stream_get_contents($_file) : file_get_contents($_file);
	if (substr($json, 0, strlen($prefix)) !== $prefix)
	{
		error_log($json);
		return $json;
	}
	$scripts = json_decode($json, true);
	//print_r($scripts);

	$branch = label2id($_branch, '***branch***');
	$revision = is_numeric($_revision) ? $_revision : label2id($_revision, '***revision***');

	$updated = $inserted = $succieded = $failed = $ignored = $new_failures = 0;
	$insert = $update = $select = $update_suite = null;
	foreach($scripts as $script)
	{
		// strip $testspath
		if (strpos($script['name'], $testspath) === 0) $script['name'] = substr($script['name'], strlen($testspath));

		$script_id = label2id($script['name'], $script['details']);
		foreach($script['tests'] as $suite)
		{
			$suite_id = label2id($suite['name'], '***suite***');
			echo "$script[name] ($script_id)\t$suite[name] ($suite_id)\t$suite[result]\n";
			// whole suite is disabled --> update existing tests as disabled
			if (!$suite['tests'] && !empty($suite['details']))
			{
				echo $suite['details']."\n";
				if (!isset($update_suite)) $update_suite = $db->prepare('UPDATE results SET success=null,first_failed=null,failed=null,details=:details,updated=:updated,time=:time,protocol=null WHERE branch=:branch AND script=:script AND suite=:suite');
				if (!$update_suite->execute($bind=array(
					'branch' => $branch,
					'script' => $script_id,
					'suite'  => $suite_id,
					'details' => $suite['details'],
					'updated' => empty($suite['time']) ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $suite['time']),
					'time'   => 0,
				)))
				{
					error_log(__LINE__.': Update failed: '.implode(' ', $update->errorInfo()).': '.json_encode($bind));
				}
				elseif (($rc=$update_suite->rowCount()))
				{
					$updated += $rc;
					$ignored += $rc;
				}
				else	// add a fake test=1 to record disabled suite
				{
					$suite['tests'][] = array(
						'details' => $suite['details'],	// eg. "Missing feature: ..."
						'name' => "1",
						'time' => $suite['time'],
						'result' => $suite['result'],
					);
				}
			}
			foreach($suite['tests'] as $test)
			{
				echo "$script[name] ($script_id)\t$suite[name] ($suite_id)\t$test[name]\t$test[result]\n";
				if (!empty($test['details'])) echo "$test[details]\n";
				if (!empty($test['protocol']))
				{
					$test['protocol'] = implode("\n", (array)$test['protocol']);
					echo "$test[protocol]\n";
				}
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
				$data = array(
					'updated' => empty($test['time']) ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $test['time']),
					'time' => empty($test['time']) ? null : $test['time']-$suite['time'],
					'details' => empty($test['details']) ? null : $test['details'],
					'protocol' => empty($test['protocol']) ? null : $test['protocol'],
				);
				if (!$test['result'])	// 0=success
				{
					$data['success'] = $revision;
					$data['failed'] = $data['first_failed'] = null;
					$succieded++;
				}
				elseif ($test['result'] == 3)	// 3=ignored, eg. missing feature
				{
					$data['success'] = $data['failed'] = $data['first_failed'] = null;
					$ignored++;
				}
				else	// 1=failed or 2=error (internal error in tester or test)
				{
					$data['failed'] = $revision;
					if (!$result || !$result['first_failed'])
					{
						$data['first_failed'] = $revision;
						$new_failures++;
					}
					$failed++;
				}
				if ($result)
				{
					if (!isset($update)) $update = $db->prepare('UPDATE results SET success=:success,first_failed=:first_failed,failed=:failed,details=:details,updated=:updated,time=:time,protocol=:protocol WHERE branch=:branch AND script=:script AND suite=:suite AND test=:test');
					if (!$update->execute($bind=array_merge(array('first_failed' => $result['first_failed']), $where, $data)))
					{
						error_log(__LINE__.': Update failed: '.implode(' ', $update->errorInfo()).': '.json_encode($bind));
					}
					else
					{
						$updated++;
					}
				}
				else
				{
					if (!isset($insert)) $insert = $db->prepare('INSERT INTO results (branch,script,suite,test,success,first_failed,failed,details,updated,time,protocol) VALUES (:branch,:script,:suite,:test,:success,:first_failed,:failed,:details,:updated,:time,:protocol)');
					if (!$insert->execute($bind=array_merge(array('success' => null), $where, $data)))
					{
						error_log(__LINE__.': Insert failed: '.implode($insert->errorInfo()).': '.json_encode($bind));
					}
					else
					{
						$inserted++;
					}
				}
			}
		}
	}
	error_log("\n$new_failures new failures, $failed total failures, $succieded tests succieded, $ignored tests ignored ($updated tests updated, $inserted newly inserted)");
}

/**
 * Update notes or other fields of given result
 *
 * @global PDO $db
 * @param string $ids branch-script-suite-test
 * @param string $name name of field/column
 * @param string $notes
 */
function update($ids, $name, $value)
{
	global $db;
	static $update=null;

	if (!isset($update)) $update = $db->prepare('UPDATE results SET notes=:notes WHERE branch=:branch AND script=:script AND suite=:suite AND test=:test');

	list($branch, $script, $suite, $test) = explode('-', $ids);
	$update->execute(array(
		'branch' => $branch,
		'script' => $script,
		'suite'  => $suite,
		'test'   => $test,
		$name    => $value,
	));
	error_log(__METHOD__."('$ids', '$name', '$value')");
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
		$db->exec("INSERT INTO labels (id,label,details) VALUES(1,'1.3','***version***')");
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
			time real DEFAULT NULL,
			protocol text,
			notes text,
			PRIMARY KEY(branch,script,suite,test)
		)');
	}
	// update schema, if necessary
	switch($db->query('SELECT label FROM labels WHERE id=1')->fetchColumn())
	{
		case '1.0':
			$db->exec('ALTER TABLE results ADD COLUMN time real DEFAULT NULL');
			// fall through
		case '1.1':
			$db->exec('ALTER TABLE results ADD COLUMN protocol text');
			// fall through
		case '1.2':
			$db->exec('ALTER TABLE results ADD COLUMN notes text');
			// update version
			$db->exec("UPDATE labels SET label='1.3' WHERE id=1");
	}
	//error_log('schema_version='.$db->query('SELECT label FROM labels WHERE id=1')->fetchColumn());

	return $db;
}
