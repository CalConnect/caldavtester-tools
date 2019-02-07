<?php
/**
 * Auto-configure CalDAVTester serverinfo.xml
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

// start output buffering, to be able to send authentication request
ob_start();

html_header();
echo "<pre>\n";

/**
 * Echo out exception message
 */
set_exception_handler(function($ex)
{
	header('HTTP/1.1 500 Internal Server Error');
	echo "\n\n<b>".htmlspecialchars($ex->getMessage())."</b>\n";
	exit;
});

if (empty($_REQUEST['host']))
{
	throw new Exception("Missing host (hostname[:port] or url) parameter!");
}
$url = $_REQUEST['host'];
$credentials = [];
if (isset($_SERVER['PHP_AUTH_USER']))
{
	$credentials['Authorization'] = 'Basic '.base64_encode($_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW']);
}
if (!preg_match('|^https?://|', $url))
{
	$url = "http://$url/.well-known/caldav";
}

$root = propfind($url, $credentials, $status, $headers, false);	// false: no flush

// stop all output-buffering, to send flushed output *now* immediatly to the browser
while(ob_get_level())
{
	ob_end_flush();
}

$scheme_host = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST);
if (($port = parse_url($url, PHP_URL_PORT))) $scheme_host .= ':'.$port;

$dav_ns = 'DAV:';
$caldav_ns = 'urn:ietf:params:xml:ns:caldav';
$carddav_ns = 'urn:ietf:params:xml:ns:carddav';

$current_user_principal = $root->getElementsByTagNameNS($dav_ns, 'current-user-principal')->item(0)->nodeValue;
$principal = propfind($current_user_principal, $credentials);

$calendar_home_set = $principal->getElementsByTagNameNS($caldav_ns, 'calendar-home-set')->item(0)->nodeValue;
$calendar_home = propfind($calendar_home_set, $credentials);

$root_path = parse_url($url, PHP_URL_PATH);
if (substr($root_path, -1) !== '/') $root_path .= '/';
// todo check url is to caldav root, not eg. a calendar or principal

$discovered = [
	'host' => parse_url($url, PHP_URL_HOST),
	'nonsslport' => parse_url($url, PHP_URL_PORT) ? parse_url($url, PHP_URL_PORT) : 80,
	'substitutions' => [
		'' => $scheme_host.'/',	// used to remove scheme and host from substitutions in case host returns it
		'$root:' => $scheme_host.$root_path,
		'$principalcollection:' => $scheme_host.$root->getElementsByTagNameNS($dav_ns, 'principal-collection-set')->item(0)->nodeValue,
		/*'$calendar',
		'$tasks',
		'$addressbook',*/
	]
];

// base substitutions on each other for better readability, eg. on $root:
foreach(array_reverse(array_keys($discovered['substitutions'])) as $replace)
{
	foreach($discovered['substitutions'] as $name => &$value)
	{
		if ($replace !== $name && strpos($value, $discovered['substitutions'][$replace]) === 0)
		{
			$value = str_replace($discovered['substitutions'][$replace], $replace.'/', $value);
		}
	}
}
unset($discovered['substitutions']['']);

echo "\n<h1 id='discovered'>Discovered serverinfo:</h1>\n";
echo htmlspecialchars(json_encode($discovered, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

echo "<form method='POST' action='/serverinfo.php'>\n";
echo "<input type='hidden' name='discovered' value='".htmlspecialchars(json_encode($discovered, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))."'/>\n";
echo "<div class='topmenu' id='discovermenu'>\n";
echo "<input type='submit' name='button[insert]' value='Insert discovered values in serverinfo'/>\n";
echo "<input type='button' value='Cancel' onclick='location.href=\"/serverinfo.php\";'/>\n";
echo "</div>\n";
echo "</form>\n";
echo "<script>document.getElementById('discovered').scrollIntoView();</script>\n";

exit();

/**
 * Send a propfind request and returning DOMDocument of parsed XML
 *
 * Follows 3xx redirects eg. for "/.well-known/caldav" (max. 3 tries).
 *
 * @param string $url
 * @param array $credentials
 * @param string& $status =null on return HTTP status eg. "207 Multistatus"
 * @param array& $headers =null on return response headers as lowercase name => value pairs
 * @param boolean $flush =true flush after output
 * @return DOMDocument
 * @throws Exception on error
 */
function propfind(&$url, array $credentials, &$status=null, array &$headers=null, $flush=true)
{
	global $scheme_host;

	for($try=1; $try <= 3; ++$try)
	{
		echo "<b>Sending PROPFIND to: $url</b>\n\n";
		if ($flush) flush();

		if (!($fp = http_open(($url[0] == '/' ? $scheme_host : '').$url, 'PROPFIND', '', ['Depth' => 0]+$credentials)))
		{
			throw new Exception("Can't connect :(");
		}
		$response = stream_get_contents($fp);
		fclose($fp);

		echo htmlspecialchars($response)."\n";
		if ($flush) flush();

		$body = parse_http_response($response, $headers);
		//var_dump($headers); flush();
		$matches = null;
		if (preg_match('|^HTTP/\d\.\d (\d+ .*)$|', $headers[0], $matches))
		{
			$status = $matches[1];
		}
		if ($status[0] === '3' && isset($headers['location']))
		{
			$url = $headers['location'];
		}
		else
		{
			break;
		}
	}

	if ($status == 401)
	{
		header('HTTP/1.0 401 Unauthorized');
		header('WWW-Authenticate: '.(isset($headers['www-authenticate']) ?
			$headers['www-authenticate'] : 'Basic realm="Password required"'));
		echo "Please enter valid credentials for the CalDAV server";
		exit;
	}
	elseif ($status != 207)
	{
		throw new Exception("Wrong HTTP status '$status' to PROPFIND request!");
	}

	$xml = new DOMDocument();
	if (!($xml->loadXML($body)))
	{
		throw new Exception("Error parsing XML response:\n$body\n");
	}
	return $xml;
}

/**
 * Open HTTP request
 *
 * @param string|array $url string with url or already passed like return from parse_url
 * @param string $method ='GET'
 * @param string $body =''
 * @param array $header =array() additional header like array('Authorization' => 'Basic '.base64_endoce("$user:$pw))
 * @param resource $context =null to set eg. ssl context like ca
 * @param float $timeout =.2 0 for async connection
 * @return resource|boolean socket still in blocking mode
 */
function http_open($url, $method='GET', $body='', array $header=array(), $context=null, $timeout=.2)
{
	$parts = is_array($url) ? $url : parse_url($url);
	$addr = ($parts['scheme'] == 'https'?'ssl://':'tcp://').$parts['host'].':';
	$addr .= isset($parts['port']) ? (int)$parts['port'] : ($parts['scheme'] == 'https' ? 443 : 80);
	if (!isset($context)) $context = stream_context_create ();
	$errno = $errstr = null;
	if (!($sock = stream_socket_client($addr, $errno, $errstr, $timeout,
		$timeout ? STREAM_CLIENT_CONNECT : STREAM_CLIENT_ASYNC_CONNECTC, $context)))
	{
		error_log(__METHOD__."('$url', ...) stream_socket_client('$addr', ...) $errstr ($errno)");
		return false;
	}
	$request = $method.' '.$parts['path'].(empty($parts['query'])?'':'?'.$parts['query'])." HTTP/1.1\r\n".
		"Host: ".$parts['host'].(empty($parts['port'])?'':':'.$parts['port'])."\r\n".
		"User-Agent: ".basename(__FILE__)."\r\n".
		"Cache-Control: no-cache\r\n".
		"Pragma:no-cache\r\n".
		"Connection: close\r\n";

	// Content-Length header is required for methods containing a body
	if (in_array($method, array('PUT','POST','PATCH')))
	{
		$header['Content-Length'] = strlen($body);
	}
	foreach($header as $name => $value)
	{
		$request .= $name.': '.$value."\r\n";
	}
	$request .= "\r\n";
	//if ($method != 'GET') error_log($request.$body);

	if (fwrite($sock, $request.$body) === false)
	{
		error_log(__METHOD__."('$url', ...) error sending request!");
		fclose($sock);
		return false;
	}
	return $sock;
}

/**
 * Parse body from HTTP response and dechunk it if necessary
 *
 * @param string $response
 * @param array& $headers =null headers on return, lowercased name => value pairs
 * @return string body of response
 */
function parse_http_response($response, array &$headers=null)
{
	list($header, $body) = explode("\r\n\r\n", $response, 2);
	$headers = array();
	foreach(explode("\r\n", $header) as $line)
	{
		$parts = preg_split('/:\s*/', $line, 2);
		if (count($parts) == 2)
		{
			$headers[strtolower($parts[0])] = $parts[1];
		}
		else
		{
			$headers[] = $parts[0];
		}
	}
	// dechunk body if necessary
	if (isset($headers['transfer-encoding']) && $headers['transfer-encoding'] == 'chunked')
	{
		$chunked = $body;
		$body = '';
		while($chunked && (list($size, $chunked) = explode("\r\n", $chunked, 2)) && $size)
		{
			$body .= substr($chunked, 0, hexdec($size));
			if (true) $chunked = substr($chunked, hexdec($size)+2);	// +2 for "\r\n" behind chunk
		}
	}
	return $body;
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
