<?php

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

		// Lecture des sous-repertoires et application r�cursive de la fonction
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

function display_htaccess_audit($dir) {
	$r = array();
	$r[] = '<style type="text/css">.allow { color: green; } .deny { color: red; }</style>';
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