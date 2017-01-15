<?php

function printr($a, $b=false) {
	return print_r($a, $b);
}

function print_proper_name($name, $article=false) {
	$name = ucfirst(str_replace('_', ' ', $name));
	if ($article) {
		$name = (in_array(strtolower(substr($name, 0, 1)), array('a', 'e', 'i', 'o', 'u', 'y'))
			? 'an ' : 'a ') . $name;
	}
	return $name;
}

function fileupload_errorname($errorcode) {
	switch ($errorcode) {
		case UPLOAD_ERR_OK : return 'UPLOAD_ERR_OK';
		case UPLOAD_ERR_INI_SIZE : return 'UPLOAD_ERR_INI_SIZE';
		case UPLOAD_ERR_FORM_SIZE : return 'UPLOAD_ERR_FORM_SIZE';
		case UPLOAD_ERR_PARTIAL : return 'UPLOAD_ERR_PARTIAL';
		case UPLOAD_ERR_NO_FILE : return 'UPLOAD_ERR_NO_FILE';
		case UPLOAD_ERR_NO_TMP_DIR : return 'UPLOAD_ERR_NO_TMP_DIR';
		case UPLOAD_ERR_CANT_WRITE : return 'UPLOAD_ERR_CANT_WRITE';
		case UPLOAD_ERR_EXTENSION : return 'UPLOAD_ERR_EXTENSION';
	}
	return 'UPLOAD_ERR_UNKNOWN';
}

function fileupload_errormessage($errorcode) {
	switch ($errorcode) {
		case UPLOAD_ERR_INI_SIZE : return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
		case UPLOAD_ERR_FORM_SIZE : return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
		case UPLOAD_ERR_PARTIAL : return 'The uploaded file was only partially uploaded';
		case UPLOAD_ERR_NO_FILE : return 'No file was uploaded';
		case UPLOAD_ERR_NO_TMP_DIR : return 'Missing a temporary folder';
		case UPLOAD_ERR_CANT_WRITE : return 'Failed to write file to disk';
		case UPLOAD_ERR_EXTENSION : return 'File upload stopped by extension';
		default : return 'Unknown upload error';
	}
}


function ziplib_error_name($code) {
	switch ($code) {
		case ZIPARCHIVE::ER_OK			: return 'No error';
		case ZIPARCHIVE::ER_MULTIDISK	: return 'Multi-disk zip archive not supported';
		case ZIPARCHIVE::ER_RENAME		: return 'Renaming temporary file failed';
		case ZIPARCHIVE::ER_CLOSE		: return 'Closing zip archive failed';
		case ZIPARCHIVE::ER_SEEK		: return 'Seek error';
		case ZIPARCHIVE::ER_READ		: return 'Read error';
		case ZIPARCHIVE::ER_WRITE		: return 'Write error';
		case ZIPARCHIVE::ER_CRC			: return 'CRC error';
		case ZIPARCHIVE::ER_ZIPCLOSED	: return 'Containing zip archive was closed';
		case ZIPARCHIVE::ER_NOENT		: return 'No such file';
		case ZIPARCHIVE::ER_EXISTS		: return 'File already exists';
		case ZIPARCHIVE::ER_OPEN		: return 'Can\'t open file';
		case ZIPARCHIVE::ER_TMPOPEN		: return 'Failure to create temporary file';
		case ZIPARCHIVE::ER_ZLIB		: return 'Zlib error';
		case ZIPARCHIVE::ER_MEMORY		: return 'Malloc failure';
		case ZIPARCHIVE::ER_CHANGED		: return 'Entry has been changed';
		case ZIPARCHIVE::ER_COMPNOTSUPP	: return 'Compression method not supported';
		case ZIPARCHIVE::ER_EOF			: return 'Premature EOF';
		case ZIPARCHIVE::ER_INVAL		: return 'Invalid argument';
		case ZIPARCHIVE::ER_NOZIP		: return 'Not a zip archive';
		case ZIPARCHIVE::ER_INTERNAL	: return 'Internal error';
		case ZIPARCHIVE::ER_INCONS		: return 'Zip archive inconsistent';
		case ZIPARCHIVE::ER_REMOVE		: return 'Can\'t remove file';
		case ZIPARCHIVE::ER_DELETED		: return 'Entry has been deleted';
	}
	return 'Unknown error';
}

/**
 * http://blog.evolya.fr/?q=relative+time
 * 10/03/2001
 */
function rdatetime_en($timestamp, $ref = 0) {

	if (!$timestamp) return 'Never';

	if ($ref < 1) $ref = time();

	$ts = $ref - $timestamp;
	$past = $ts > 0;
	$ts = abs($ts);

	if ($past) {
		$left = '';
		$right = ' ago';
	}
	else {
		$left = 'In ';
		$right = '';
	}

	if ($ts === 0) return 'Now';

	if ($ts === 1) return $left.'1 second'.$right;

	// Less than 1 minute
	if ($ts < 60) return $left.$ts.' seconds'.$right;

	$tm = floor($ts / 60);
	$ts = $ts - $tm * 60;

	// Less than 3 hours
	if ($tm < 3 && $ts > 0) {
		return $left.$tm.' minute'.($tm > 1 ? 's' : '').' and '.$ts.' second'.($ts > 1 ? 's' : '').$right;
	}

	// Less than 1 hour
	if ($tm < 60) {
		if ($ts > 0) {
			//$left = 'About ';
		}
		return $left.$tm.' minutes'.$right;
	}

	$th = floor($tm / 60);
	$tm = $tm - $th * 60;

	// Less than 3 hours
	if ($th < 3) {
		if ($tm > 0) {
			return $left.$th.' hour'.($th > 1 ? 's' : '').' and '.$tm.' minute'.($tm > 1 ? 's' : '').$right;
		}
		else {
			return $left.$th.' hour'.($th > 1 ? 's' : '').$right;
		}
	}

	$td = floor($th / 24);
	$th = $th - $td * 24;

	$refday = strtotime(date('Y-m-d', $ref));
	$refyday = strtotime(date('Y-m-d', $ref - 86400));

	// Same day, or yesterday
	if ($td <= 1 && $timestamp >= $refyday) {
		if ($timestamp < $refday) {
			$left = 'Yesterday';
			$right = '';
		}
		else {
			$left = 'Today';
			$right = '';
		}
		return $left.' at '.date('H:i a', $timestamp).($right != '' ? ' '.$right : '');
	}

	// Less than 3 days
	if ($td < 3) {
		$left = 'Last ';
		$right = '';
		return $left.strtolower(date('l', $timestamp)).' at '.date('H:i', $timestamp).$right;
	}

	// Less than 5 days
	if ($td < 5) {
		return $left.$td.' days'.$right;
	}

	$sameyear = date('Y', $timestamp) == date('Y', $ref);
	$refday = strtotime(date('Y-m-01', $ref));

	$right = '';

	// Same month
	if ($sameyear && $timestamp >= $refyday) {
		$left = 'The ';
		return $left.date('j M \a\t H:i', $timestamp).$right;
	}

	return date('F j, Y', $timestamp);

}

function rdate($timestamp) {
	return rdatetime_en($timestamp);
}

function let_to_num($v) {
	$l = substr($v, -1);
	$ret = substr($v, 0, -1);
	switch(strtoupper($l)){
		case 'P': $ret *= 1024;
		case 'T': $ret *= 1024;
		case 'G': $ret *= 1024;
		case 'M': $ret *= 1024;
		case 'K': $ret *= 1024;
		break;
	}
	return $ret;
}

function format_bytes($bytes) {
	if ($bytes < 1024) return $bytes.' B';
	elseif ($bytes < 1048576) return round($bytes / 1024, 2).' KB';
	elseif ($bytes < 1073741824) return round($bytes / 1048576, 2).' MB';
	elseif ($bytes < 1099511627776) return round($bytes / 1073741824, 2).' GB';
	else return round($bytes / 1099511627776, 2).' TB';
}

function frency2sec($frequency) {
	switch ($frequency) {
		case 'each second' : case 'second' : case 'seconds' :
			return 1;
			break;
		case 'each minute' : case 'minute' : case 'minutes' :
			return 60;
			break;
		case 'hourly' : case 'each hour' : case 'hour' : case 'hours' :
			return 3600;
			break;
		case 'daily' : case 'each day' : case 'day' : case 'days' :
			return 86400;
			break;
		case 'weekly' : case 'each week' : case 'week' : case 'weeks' :
			return 604800;
			break;
		case 'monthly' : case 'each month' : case 'month' : case 'months' :
			return 2592000;
			break;
		case 'bi-monthly' :
			return 5184000;
			break;
		case 'quarterly' :
			return 7776000;
			break;
		case 'half-yearly' : case 'biannual' :
			return 15552000;
			break;
		case 'annual' : case 'yearly' :
			return 15552000;
			break;
	}
	return 0;
}

function getRandomKey($length=12) {
	$randStr = null;
	$args[] = 'N' . $length;
	for ($i = 0; $i < $length; $i++) {
		$args[] = mt_rand();
	}
	return substr(base64_encode((call_user_func_array('pack', $args))), 1, $length);
}

function cut($str, $limit=0) {
	if ($limit > 0 && $limit < strlen($str)) {
		return substr($str, 0, $limit - 2) . '..';
	}
	return $str;
}

function str2int($str) {
	$r = '';
	for ($i = 0, $j = strlen($str); $i < $j; $i++) {
		$c = $str{$i};
		if ($c === '0' || $c === '1' || $c === '2' || $c === '3' || $c === '4'
		 || $c === '5' || $c === '6' || $c === '7' || $c === '8' || $c === '9') {
			$r .= $c;
		}
	}
	return intval($r);
}


/*if (!function_exists('json_decode')) {
	function json_decode($json) {
		$json = str_replace(
			array("\\\\", "\\\""),
			array("&#92;", "&#34;"),
			$json
		);
		$parts = preg_split(
			"@(\"[^\"]*\")|([\[\]\{\},:])|\s@is",
			$json,
			-1,
			PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
		);
		foreach ($parts as $index => $part) {
			if (strlen($part) == 1) {
				switch ($part) {
				case "[": case "{":
					$parts[$index] = "array(";
					break;
				case "]": case "}":
					$parts[$index] = ")";
					break;
				case ":":
					$parts[$index] = "=>";
					break; 
				case ",":
					break;
				default:
					return null;
			}
			}
			else {
				if ((substr($part, 0, 1) != "\"") || (substr($part, -1, 1) != "\"")) {
					return null;
				}
			}
		}
		$json = str_replace(
			array("&#92;", "&#34;", "$"),
			array("\\\\", "\\\"", "\\$"),
			implode("", $parts)
		);
		return eval("return $json;");
	}
}*/

/**
* Utility function to get a specific attribute value in a
* <a href="http://fr.php.net/manual/en/class.simplexmlelement.php" target="_blank">SimpleXMLElement</a>.
*
* @param SimpleXMLElement $object
* @param string $attribute
* @return string If the attribute was found.
* @return null If the attribute doesn't exists.
*/
function xml_attribute(SimpleXMLElement $object, $attribute) {
	if (isset($object[$attribute])) return (string) $object[$attribute];
	return null;
}

class MiniPage {
	public $title = '';
	public $desc = '';
	public $author = '';
	public $contents = '';
	public $resources = '';
	public $core = '';
}

?>