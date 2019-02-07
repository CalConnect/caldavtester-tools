<?php
/**
 * Index for docker container
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

// read config file, if exists
$config_file = __DIR__.'/.caldavtests.json';
if (file_exists($config_file) && ($conf = json_decode(file_get_contents($config_file), true)))
{
	foreach($conf as $name => $value)
	{
		$$name = $value;
	}
}
else
{
	die("Config-file $config_file NOT found!\n");
}

// if we have a serverinfo.xml, redirect to caldavtests.php
if (file_exists($serverinfo))
{
	if (!is_writable($serverinfo))
	{
		die("File $serverinfo is NOT writable!");
	}
	if (file_exists($db_path) && !is_writable($db_path) || !file_exists($db_path) && !is_writable(dirname($db_path)))
	{
		die("Sqlite database file $db_path can NOT be written!");
	}
	header('Location: /caldavtests.php');
}
// if not, redirect to serverinfo.php to create or upload one
else
{
	header('Location: /serverinfo.php');
}
