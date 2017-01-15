<?php



// On recupère l'API de gestion de fichier
try {
	$api = WG::files();
}
catch (Exception $ex) {
	echo '<div class="result resulterror"><strong>Error</strong>: file manager is not installed.</div>';
	return;
}

// Téléchargement de fichier
if (isset($_REQUEST['g'])) {
	
	// Code de retour
	$status = array(200, 'OK');
	
	try {
		
		// On vérifie que $node soit un fichier valide
		// En cas d'erreur, une exception WGFilesIOException sera levée
		// Au passage, $node est nettoyé
		$node = $api->checkNode($_REQUEST['g'], false, true);
		
		// On vérifie que l'utilisateur puisse lire la ressource
		// En cas d'erreur, une exception WGFilesNeedPrivilegesException sera levée
		$api->checkCurrentUserPrivileges($node, 'read', true);

		// On récupère le chemin absolue du fichier
		$file = $api->realpath($node);
		
		// Erreur de lecture
		if (!is_readable($file)) {
			throw new Exception("Not readable");
		}
		
		// Pour plus de confort on va indiquer la taille du fichier
		$size = filesize($file);
		
		// En-têtes
		header("Content-Type: application/force-download; name=\"" . basename($file) . "\"");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: $size");
		header("Content-Disposition: attachment; filename=\"" . basename($file) . "\"");
		header("Expires: 0");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
		
		// Lecture du fichier
		readfile($file);
		
	}
	catch (WGFilesIOException $ex) {
		$status = array(404, 'Not Found', 'The requested file <strong>'.htmlspecialchars($_REQUEST['g']).'</strong> was not found on this server.');
	}
	catch (WGFilesSecurityException $ex) {
		// Ici on choisi de ne pas exposer la ressource si c'est une erreur de sécurité
		// TODO pouvoir configurer ça
		//$status = array(403, 'Forbidden', 'You don\'t have permission to access <strong>'.htmlspecialchars($_REQUEST['g']).'</strong> on this server.');
		$status = array(404, 'Not Found', 'The requested file <strong>'.htmlspecialchars($_REQUEST['g']).'</strong> was not found on this server.');
	}
	catch (Exception $ex) {
		$status = array(500, 'Internal Server Error', ' The server encountered an internal error and was unable to complete your request.');
	}

	// Traitement des erreurs
	if ($status[0] !== 200) {
		
		// En-têtes
		header("HTTP/1.0 {$status[0]} {$status[1]}", $status[0], true);
		header('Content-type: text/html');
		
		echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN"><html><head>';
		echo "<title>{$status[0]} {$status[1]}</title>";
		echo "</head><body>	<h1>{$status[1]}</h1><p>{$status[2]}</p></body></html>";

	}

	exit();

}

// Le répertoire cible
$node = isset($_SESSION['cd']) ? $_SESSION['cd'] : '/';

// Répertoire indiqué
if (isset($_REQUEST['p'])) {
	$node = $_REQUEST['p'];
}

// On vérifie que $node soit un répertoire valide
// Au passage, $node est nettoyé
$node = $api->checkNode($node, true, false);

// Si $node est null, c'est que le répertoire n'existe pas
if ($node == null) {
	echo '<div id="poptop" class="alert">Folder not found. <a id="close-poptop">[close]</a></div>';
	$node = '/';
}

// On vérifie que l'utilisateur puisse lire la ressource
if (!$api->checkCurrentUserPrivileges($node, 'read', false)) {
	echo '<div id="poptop" class="alert">Forbidden. <a id="close-poptop">[close]</a></div>';
	$node = '/';
}

// On sauvegarde le noeud dans la session
$_SESSION['cd'] = $node;

// Traiter un changement de nom
if (isset($_REQUEST['rename']) && isset($_REQUEST['newname'])) {
	try {
		$api->rename($_REQUEST['rename'], trim($_REQUEST['newname']));
		echo '<div id="poptop" class="alert">Node name changed. <a id="close-poptop">[close]</a></div>';
	}
	catch (Exception $ex) {
		echo '<div id="poptop" class="alert">Unable to change node name. <a id="close-poptop">[close]</a></div>';
	}
}

// Traiter la création d'un répertoire
else if (isset($_REQUEST['mkdir'])) {
	try {
		$api->mkdir($_REQUEST['mkdir'], true);
		echo '<div id="poptop" class="alert">Folder created. <a id="close-poptop">[close]</a></div>';
	}
	catch (Exception $ex) {
		echo '<div id="poptop" class="alert">Unable to create folder: '.htmlspecialchars($_REQUEST['mkdir']).' <a id="close-poptop">[close]</a></div>';
	}
}

// Traiter une suppression
else if (isset($_REQUEST['del'])) {
	try {
		$api->unlink($_REQUEST['del'], true);
		echo '<div id="poptop" class="alert">Deleted: '.htmlspecialchars($_REQUEST['del']).' <a id="close-poptop">[close]</a></div>';
	}
	catch (Exception $ex) {
		echo '<div id="poptop" class="alert">Unable to delete: '.htmlspecialchars($_REQUEST['del']).' <a id="close-poptop">[close]</a></div>';
	}
}

// On prépare deux tableaux pour contenir les noeuds
$folders = array();
$files = array();

try {

	// On tente d'obtenir la liste de fichiers
	$list = $api->ls($node);
	
	// On parcours les éléments dans le dossier
	foreach ($list as $item) {
		
		// On recupère les infos de l'element, sans la taille récursive des répertoires
		$info = $api->pathinfo($node . $item, PATHINFO_ALL ^ PATHINFO_RECURSIVE);
		
		// En cas d'erreur.
		if (!$info) {
			continue;
		}
		
		// On classe les dossiers d'un côté, les fichiers de l'autre
		if ($info['type'] == 'd') {
			$folders[$item] = $info;
		}
		else {
			$files[$item] = $info;
		}
		
	}
	
}
catch (Exception $ex) {
	echo '<div class="result resulterror"><strong>Error</strong>: unable to read this directory.</div>';
	return;
}

// On classe les données alphabétiquement
ksort($folders, SORT_STRING);
ksort($files, SORT_STRING);

// Breadcrumb
echo '<div class="view-topbar"><ul class="split-breadcrumb">';
echo '<li><a href="index.php?view=files&p=%2F">/</a></li>';

// Breadcrumbs
$prec = '/';
foreach (explode('/', $node) as $el) {
	
	if (empty($el)) continue;
	
	$al = $prec.$el.'/';
	
	if ($api->checkCurrentUserPrivileges($al, 'read', false)) {
		echo '<li><a href="index.php?view=files&p='.urlencode($al).'">'.$el.'</a></li>';
	}
	
	else {
		echo '<li><a>'.$el.'</a></li>';
	}
	
	$prec .= $el . '/';
}
unset($prec, $el, $al);
echo '</div></div>';

// Top right commands
echo '<div class="rightcommands">';
echo '<a class="button" href="index.php?view=files&action=upload"><b>Upload</b></a>';
echo '<a class="button mkdir" path="'.htmlspecialchars($node).'"><img src="'.$page->resources.'folder_add.png" />&nbsp;New&nbsp;Folder</a>';
echo '</div>';

// Table : header
echo '<table id="files-table" class="wg-tree-table" border="1">';
echo '<thead><tr><th></th><th>Name</th><th>&nbsp;</th><th>Size</th><th>Modified</th></tr></thead><tbody>';

// Table : body : folders
foreach ($folders as $item => $info) {
	
	// On fabrique le chemin relatif du noeud
	$filepath = "$node$item/";
	
	// On recupère les privilèges de l'utilisateur sur ce noeud
	$priv = $api->getCurrentUserPrivilegeSet($filepath);

	// Il n'est pas possible de lire ce fichier, on va l'éviter
	if (!in_array('read', $priv)) {
		continue;
	}
	
	echo '<tr class="folder" path="'.htmlspecialchars($filepath).'">';
	echo '<td></td><td><a class="readdir">';
	echo utf8_encode(htmlspecialchars($item));
	echo '</a></td><td>';

	if (in_array('write', $priv)) {
		echo '<a class="rename" title="Rename"></a> ';
		echo '<a class="del" title="Delete"></a>';
	}

	echo '</td><td></td>';

	echo '<td>'.WG::rdate($info['mtime']).'</td>';
	
	echo '</tr>';
	
}

// Table : body : files
foreach ($files as $item => $info) {
	
	// On fabrique le chemin relatif du noeud
	$filepath = "$node$item/";
	
	// On recupère les privilèges de l'utilisateur sur ce noeud
	$priv = $api->getCurrentUserPrivilegeSet($filepath);
	
	// Il n'est pas possible de lire ce fichier, on va l'éviter
	if (!in_array('read', $priv)) {
		continue;
	}
	
	echo '<tr class="file" path="'.htmlspecialchars($filepath).'">';
	
	echo '<td ext="'.(isset($info['extension']) ? $info['extension'] : '').'"></td>';
	
	echo '<td><a class="view">';
	echo utf8_encode(htmlspecialchars($item));
	echo '</a></td><td>';

	echo '<a class="get" title="Download"></a> ';
	
	if (in_array('write', $priv)) {
		echo '<a class="rename" title="Rename"></a> ';
	}
	if (in_array('unbind', $priv)) {
		echo '<a class="del" title="Delete"></a>';
	}

	echo '</td><td>'.format_bytes($info['size']).'</td>';

	echo '<td>'.WG::rdate($info['mtime']).'</td>';
	
	echo '</tr>';

}

// Table : footer
echo '</tbody></table>';

echo <<<_JS
<script>
$(function () {

	$('#files-table').each(function () {
	
		// Changedir
		$('a.readdir', this).click(function () {
			WG.setView('files', {'p': $(this).closest('tr').attr('path')});
			return false;
		});
	
		// Download
		$('a.get', this).click(function () {
			$(this)
				.attr('href', 'view.php?v=files&g=' + $(this).closest('tr').attr('path'))
				.attr('target', '_blank');
			return true;
		});
	
		// Delete
		$('a.del', this).click(function () {
			if (!confirm('Are you sure?\\nThis file will be deleted permanently.')) {
				return false;
			}
			WG.setView('files', {
				del: $(this).closest('tr').attr('path')
			});
			return false;
		});
	
		// Rename
		$('a.rename', this).click(function () {
			var name = prompt('New name: ');
			if (typeof name != 'string') {
				return false;
			}
			name = jQuery.trim(name);
			if (name != '') {
				WG.setView('files', {
					rename: $(this).closest('tr').attr('path'),
					newname: name
				});
			}
			return false;
		});
	
	});
	
	// Mkdir
	$('#view-files a.mkdir').click(function () {
		var name = prompt('Folder name: ');
		if (typeof name != 'string') {
			return false;
		}
		name = jQuery.trim(name);
		if (name != '') {
			WG.setView('files', {
				mkdir: this.getAttribute('path') + name
			});
		}
		return false;
	});
	
});
</script>
_JS;

?>