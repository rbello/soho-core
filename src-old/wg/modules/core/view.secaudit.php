<h1>Security Audit</h1>

<div class="singlecol">
	<h2>Tests</h2>
	<ul>
		<li><a href="index.php?view=secaudit&test=loganalyze">Log analyze</a></li>
		<li><a href="index.php?view=secaudit&test=htaccess">Htaccess map</a></li>
	</ul>
<?php

/*
  13 => 
    array
      'timestamp' => int 1331216287
      'datetime' => string 'Thu, 08 Mar 2012 09:18:07 -0500' (length=31)
      'request' => string 'HTTP/1.0 POST /evolya/evolya.workgroop/src/ws.php' (length=49)
      'from' => string '127.0.0.1 (localhost)' (length=21)
      'agent' => string 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.63 Safari/535.7' (length=98)
      'modes' => 
        array
          'ssl' => boolean false
          'aes' => boolean false
  14 => 
    array
      'timestamp' => int 1331216287
      'datetime' => string 'Thu, 08 Mar 2012 09:18:07 -0500' (length=31)
      'login' => string 'SUCCESS' (length=7)
      'logininfo' => string 'remi (QoP = b)' (length=14)
      'from' => string '127.0.0.1' (length=9)
      'agent' => string 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.63 Safari/535.7' (length=98)
  15 => 
    array
      'timestamp' => int 1331216287
      'datetime' => string 'Thu, 08 Mar 2012 09:18:07 -0500' (length=31)
      'request' => string 'HTTP/1.0 POST /evolya/evolya.workgroop/src/ws.php' (length=49)
      'from' => string '127.0.0.1 (localhost)' (length=21)
      'agent' => string 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.63 Safari/535.7' (length=98)
      'modes' => 
        array
          'ssl' => boolean false
          'aes' => boolean false
      'user' => string 'remi (remi@evolya.fr), TokenID=64htsmdcde3jagdtnrnobfhl40' (length=57)
*/

if (isset($_REQUEST['test'])) {

	/**** TEST : LOG ANALYZE ****/
	if ($_REQUEST['test'] == 'loganalyze') {

		echo '<h2>Result</h2>';

		function parse_log_raw($file) {
			if (!is_file($file) || !is_readable($file)) return null;
			$fc = file_get_contents($file);
			if ($fc === false) return null;
			$fc = explode("\n", $fc);
			$logs = array();
			$cur = array();
			foreach ($fc as $line) {
				$line = trim($line);
				if (empty($line)) {
					if (sizeof($cur) > 0) {
						$logs[] = $cur;
						$cur = array();
					}
					continue;
				}
				$tmp = substr($line, 0, 18);
				if (substr($tmp, 0, 6) == 'From: ') {
					$tmp = explode(' ', substr($line, 6));
					if (sizeof($tmp) > 1) {
						$cur['remoteip'] = $tmp[0];
						$cur['remoteaddr'] = substr($tmp[1], 1, -1);
					}
					else {
						$cur['remoteip'] = $tmp[0];
					}
				}
				else if (substr($tmp, 0, 7) == 'Agent: ') {
					$cur['agent'] = substr($line, 7);
				}
				else if (substr($tmp, 0, 10) == 'Security: ') {
					$cur['modes'] = array(
						'ssl' => substr($line, strpos($line, 'SSL=') + 4, 1) === '1',
						'aes' => substr($line, strpos($line, 'AES=') + 4, 1) === '1'
					);
				}
				else if (substr($tmp, 0, 7) == 'Modes: ') {
					$cur['modes'] = array(
						'ssl' => substr($line, strpos($line, 'SSL='), 1) === '1',
						'aes' => substr($line, strpos($line, 'AES='), 1) === '1'
					);
				}
				else if (substr($tmp, 0, 6) == 'User: ') {
					$cur['user'] = substr($line, 6);
				}
				else if (substr($tmp, 0, 9) == 'HTTP/1.0 ') {
					$cur['request'] = $line;
				}
				else if (substr($tmp, 0, 15) == 'LOGIN FAILURE: ') {
					$cur['login'] = 'FAILURE';
					$cur['logininfo'] = substr($line, 15);
				}
				else if (substr($tmp, 0, 18) == 'LOGIN SUCCESSFUL: ') {
					$cur['login'] = 'SUCCESS';
					$cur['logininfo'] = substr($line, 18);
				}
				else if (substr($tmp, 0, 14) == 'DISCONNECTED: ') {
					$cur['login'] = 'DISCONNECTED';
					$cur['logininfo'] = substr($line, 14);
				}
				else if (sizeof($cur) === 0) {
					$cur['timestamp'] = strtotime($line);
					$cur['datetime'] = $line;
				}
				else {
					$cur[] = $line;
				}
			}
			if (sizeof($cur) > 0) {
				$logs[] = $cur;
			}
			return $logs;
		}

		function stack_log($data) {
			$stacks = array();
			// On parcours les logs
			foreach ($data as $log) {
				// La clé est composée de l'ip et de l'agent
				$key = $log['remoteip'] . ' ' . @$log['agent'];
				// Création du stack
				if (!isset($stacks[$key])) {
					$stacks[$key] = array(
						// Suspicious Level
						// -1 = OK
						//  0 = NEUTRAL
						// >0 = SUSPICIOUS
						'level' => 0,
						'start' => $log['timestamp'],
						'end' => $log['timestamp'],
						'logs' => array()
					);
				}
				// On enregistre le log dans le stack
				$stacks[$key]['logs'][] = $log;
				// On enregistre la fin de la session
				$stacks[$key]['end'] = $log['timestamp'];
				// On regarde si l'utilisateur a tenté une authentification
				if (isset($log['login'])) {
					switch ($log['login']) {
						case 'SUCCESS' :
							// Si la session a été authentifiée, le stack est considéré comme OK
							$stacks[$key]['level'] = -1;
							break;
						case 'FAILURE' :
							// En cas d'erreur, on monte le niveau d'erreur
							$stacks[$key]['level'] += 1;
							break;
						case 'DISCONNECTED' :
							break;
					}
				}
			}
			
			return $stacks;
		}

		$data = parse_log_raw(WG::base('data/wgcrt.log'));

		$data = stack_log($data);

		echo '<style>
		tr.green  { background-color: #d3f0be; }
		tr.orange { background-color: #f0e5be; }
		tr.red    { background-color: #f0bebe; }
		</style>';
		echo '<table class="data">';
		echo '<thead><tr><th>Start date</th><th>End date</th><th>Client info</th></tr></thead><tbody>';
		foreach ($data as $key => $stack) {
			$level = 'orange';
			if ($stack['level'] < 0) {
				$level = 'green';
			}
			else if ($stack['level'] > 0) {
				$level = 'red';
			}
			echo '<tr class="'.$level.'"><td>'.date('j M, Y H:i', $stack['start']).'</td><td>'.date('j M, Y H:i', $stack['end']).'</td><td>'.htmlspecialchars($key).'</td></tr>';
		}
		echo '</tbody></table>';

		

	}

	/**** TEST : HTACCESS ****/
	else if ($_REQUEST['test'] == 'htaccess') {

		// La fonction qui permet de suivre le cheminement des directives allow/deny des htaccess
		function htaccess_audit_raw($dir, $allow = true) {
			if ($handle = opendir($dir)) {

				// Si un fichier .htaccess existe, on va le lire pour regarder
				// si on doit modifier $allow.
				if (is_file("$dir/.htaccess")) {
					if (($fg = file_get_contents("$dir/.htaccess")) !== false) {
						$fg = strtolower($fg);
						if (strpos($fg, 'allow from all') !== false) $allow = true;
						if (strpos($fg, 'deny from all') !== false) $allow = false;
						unset($fg);
					}
				}

				$r = array(
					'dir' => $dir,
					'allow' => $allow,
					'sub' => array()
				);

				// Lecture des sous-repertoires et application récursive de la fonction
				while (false !== ($file = readdir($handle))) {
					if ($file == '.' || $file == '..') continue;
					if (is_dir("$dir/$file")) {
						$r['sub'][] = htaccess_audit_raw("$dir/$file", $allow);
					}
				}
				closedir($handle);
				return $r;
			}
			else return null;
		}

		$data = htaccess_audit_raw(realpath(WG::base('..')));

		function display_htaccess_audit($dir) {
			$r = array();
			$r[] = '<li class="';
			$r[] = $dir['allow'] ? 'allow' : 'deny';
			$r[] = '">';
			$r[] = htmlspecialchars($dir['dir']);
			if (sizeof($dir['sub']) > 0) {
				$r[] = '<ul>';
				foreach ($dir['sub'] as $sub) {
					$r[] = display_htaccess_audit($sub);
				}
				$r[] = '</ul>';
			}
			$r[] = '</li>';
			return implode('', $r);
		}

		?>
		<style type="text/css">
		.allow { color: green; }
		.deny { color: red; }
		</style>
		<?php

		echo '<h2>Result</h2>';
		echo display_htaccess_audit($data);

	}

}

?>
</div>