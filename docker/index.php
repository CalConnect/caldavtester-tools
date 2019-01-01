<?php
/**
 * Index for docker container
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

if (isset($_FILES['serverinfo']))
{
	if (!move_uploaded_file($_FILES['serverinfo']['tmp_name'], $serverinfo))
	{
		echo "<p>Error uploading serverinfo.xml file!</p>\n";
	}
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
	exit;
}

die("<pre><b>No serverinfo.xml found!</b>\n\nYou need to supply it as volume under /data:\n\ndocker run -p8080:80 -v &lt;/dir/of/serverinfo.xml>:/data quay.io/egroupware/caldavtester\n\n".
	"<form method='POST' enctype='multipart/form-data'>Or upload it now <input type='file' name='serverinfo' onchange='this.form.submit();'/></form>");