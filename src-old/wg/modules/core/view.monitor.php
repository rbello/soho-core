<h1>Monitor</h1>

<div class="rightcommands">
	<a class="button" href="index.php?view=monitor&ts=<?php echo $_SERVER['REQUEST_TIME']; ?>">Refresh</a>
</div>

<div class="singlecol">

<?php

########################################################### S E R V E R

if (WG::checkFlags('ua')) {

	echo '<h2>Server</h2>';

	exec('top -b -n 1', $top);

	if (is_array($top) && sizeof($top) > 0) {
		// CPU usage
		$cpu = explode('%us', $top[2]);
		$cpu = intval(str_replace('Cpu(s):', '', $cpu[0]));
	
		// Memory
		$mem = explode('k ', $top[3]);
		$mem = round(str2int($mem[1]) / str2int($mem[0]) * 100);
	}
	
	unset($top);
	
	// Quota
	
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'quotafs') {
	
		$quota = 0;
	
		$quota_dir = WG::vars('quota_dir');
	
		function calculate_quota ($dir, &$quota) {
			if ($handle = @opendir($dir)) {
				while (false !== ($file = readdir($handle))) {
					if ($file != '.' && $file != '..') {
						$f = "$dir/$file";
						if (is_dir($f)) {
							calculate_quota($f, $quota);
						}
						else {
							$quota += filesize($f);
						}
					}
				}
				@closedir($handle);
			}
		}
	
		calculate_quota($quota_dir, $quota);
	
		$quota_max = 26800000000; // 25 go = OVH
	
	}

	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'quotadb') {
	
		$query = 	'SELECT table_schema "Data Base Name",
					SUM( data_length + index_length ) "Size"
					FROM information_schema.TABLES
					GROUP BY table_schema';
	
		$query = WG::database()->query($query);
	
		if (is_object($query)) {
			foreach ($query as $r) {
				if ($r->get('Data Base Name') == WG::vars('db_name')) {
					$quotadb = intval($r->get('Size'));
				}
			}
		}
	
		$quotadb_max = 52428800; // 50 mo = OVH
	
		if (!isset($quotadb)) {
			echo '<p class="result resulterror">Error: unable to calculate dabatase quota.</p>';
		}
	
	}
	
	echo '<p>';
	echo '<strong>Host</strong>: '.$_SERVER['SERVER_NAME'].'<br />';
	echo '<strong>Httpd</strong>: '.$_SERVER['SERVER_SOFTWARE'].'<br />';
	if (function_exists('apache_get_modules')) {
		echo '<strong>Httpd modules</strong>: '.implode(', ', apache_get_modules()) . '<br />';
	}
	echo '<strong>PHP</strong>: '.phpversion().'<br />';
	echo '<strong>Workgroop</strong>: '.WG::vars('appVersion').'<br />';
	echo '<strong>SMTP</strong>: '.ini_get('SMTP').'<br />';
	echo '<strong>ServerTime</strong>: <var id="serverTime">'.date('r').'</var> ('.date_default_timezone_get().')<br />';
	echo '<strong>PHP Extensions</strong>: '.implode(', ', get_loaded_extensions()).'<br />';
	echo '<strong>FileSystem Quota</strong>: ';
	if (isset($quota)) {
		echo '<ins>' . format_bytes($quota) . ' / ' . format_bytes($quota_max) . ' (' . round($quota / $quota_max * 100) . '%)</ins>';
	}
	else {
		echo '<a href="?view=monitor&action=quotafs">display quota</a>';
	}
	echo '<br />';
	echo '<strong>MySQL Quota</strong>: ';
	if (isset($quotadb)) {
		echo '<ins>' . format_bytes($quotadb) . ' / ' . format_bytes($quotadb_max) . ' (' . round($quotadb / $quotadb_max * 100) . '%)</ins>';
	}
	else {
		echo '<a href="?view=monitor&action=quotadb">display quota</a>'; 
	}
	echo '<br /><strong>Config</strong>: ';
	echo '<code>execution_time</code>=<var>'.ini_get('max_execution_time').'</var>, ';
	echo '<code>upload_size</code>=<var>'.format_bytes(min(let_to_num(ini_get('post_max_size')), let_to_num(ini_get('upload_max_filesize')))).'</var>, ';
	echo '<code>get_magic_quotes_gpc</code>=<var>'.(get_magic_quotes_gpc() ? 'on' : 'off').'</var>';
	if (isset($cpu) && isset($mem)) {
		echo '<br /><strong>CPU</strong>: <var id="cpu">'.$cpu.'%</var><br /><strong>Memory</strong>: <var id="memory">'.$mem.'%</var>';
	}
	echo '</p>';
	
	echo <<<_HTML
<script>

// Update server time
WG.live.bind.monitorViewUpdater1 = {
	event: 'serverTime',
	view: 'monitor',
	onChange: function (data) {
		var st = new Date();
		st.setTime(data * 1000);
		$('#view-monitor #serverTime').text(st.toGMTString());
	}
};

// Update cpu and memory charges
WG.live.bind.monitorViewUpdater3 = {
	event: 'cpumem',
	view: 'monitor',
	onChange: function (data) {
		$('#view-monitor #cpu').text(data.cpu + '%');
		$('#view-monitor #memory').text(data.memory + '%');
	}
};

</script>
_HTML;

}


########################################################### T E R M I N A L

if (WG::checkFlags('ua')) :

?>

	<h2 id="cronlist">Terminal</h2>
	<table id="term">
	  <tbody>
	    <tr id="term-out">
			<td colspan="3"><textarea readonly></textarea></td>
	    <tr>
	    <tr id="term-in">
			<td width="1%"><span><?php echo htmlspecialchars(WG::user()->get('login')) . '@' . WG::host(); ?></span></td>
			<td width="*"><input type="text" /></td>
			<td width="1%"><input type="submit" value="OK" /></td>
	    <tr>
	  </tbody>
	</table>
	<script>
	$(function () {

		// Les composants qui vont servire
		var out = $('#view-monitor #term-out textarea');
		var inp = $('#view-monitor #term-in input[type="text"]');
		var bt = $('#view-monitor #term-in input[type="submit"]');
		var scheme = $('#view-monitor #term-in span');

		// Gestion du cache
		var cache = []; // mémorise les entrées
		var cache_cur = 0; // curseur dans la mémoire

		// Passwords
		var lastReturnCode = 0; // code de retour, pour savoir le traitement à faire
		var lastInput = null; // mémorise la commande à renvoyer avec le mdp
		var lastConcat = null; // mémoire si segment à concatener au mdp

		// Infos sur l'utilisateur et la config
		var user = "<?php echo WG::user()->get('login'); ?>";
		var host = "<?php echo WG::host(); ?>";

		// Auto-completion
		var autocomp = null; // jqXHR de la requête en cours 
		var tab = false; // Indique si la touche tab a été appuyé une fois

		// Appliquer le comportement normal sur le fiel input
		// Ce comportement est embarqué dans une fonction pour pouvoir l'appliquer
		// si le champ input change (pour la saisie des mots de passes)
		var setInputBehavior = function (inp) {
			inp.keyup(function (e) {
				// Enter key: submit for
				if (e.keyCode === 13) {
					query();
				}
				// Up key: browse cache
				else if (e.keyCode === 38) {
					if (cache.length < 1) return;
					var pos = cache.length - cache_cur - 1;
					//console.log("L=" + cache.length + " C=" + cache_cur + " P=" + pos);
					inp.val(cache[pos]);
					cache_cur++;
					if (cache_cur >= cache.length) {
						cache_cur = 0;
					}
				}
				// Down key: browse cache
				else if (e.keyCode === 40) {
					if (cache.length < 1) return;
					var pos = cache.length - cache_cur - 1;
					//console.log("L=" + cache.length + " C=" + cache_cur + " P=" + pos);
					inp.val(cache[pos]);
					cache_cur--;
					if (cache_cur < 0) {
						cache_cur = cache.length - 1;
					}
				}
			});
			inp.keydown(function (e) {
				// Tab key: auto-completion
				if (e.keyCode === 9) {
					if (tab) {
						if (autocomp != null) {
							autocomp.abort();
						}
						// Webservice query
						autocomp = WG.ajax({
							url: WG.appURL() + 'ws.php',
							dataType: 'json',
							timeout: 15000, // 15 secondes
							data: {
								'w': 'exec-cmd',
								'a': inp.val()
							},
							success: function (data, textStatus, jqXHR) {

								// L'operation d'autocomp est terminée
								autocomp = null;
								
								// Aucun résultat = on ne fait rien
								if (data.length < 1) return;

								// Un résultat
								if (data.length === 1) {
									// Si ce n'est pas un nom de répertoire, on ajoute un espace et on désactive le tab
									if (data[0].substr(-1) != '/') {
										tab = false;
										data[0] += ' ';
									}
									inp.val(inp.val() + data[0]);								
								}

								// Plusieurs résultats = on affiche les résultats
								else {
									var d = '',
										m = inp.val().split(" ").pop();
									for (i in data) {
										// Completion partielle
										if (data[i].substr(0, 1) == '|' && data[i].substr(-1) == '|') {
											inp.val(inp.val() + data[i].substr(1, data[i].length - 2));
										}
										// Sinon on ajoute aux propositions
										else {
											d += "\n" + m + data[i];
										}
									}
									out.val(out.val() + ">" + d + "\n");
									out[0].scrollTop = out[0].scrollHeight;
								}
								
							},
							// L'erreur d'autocomplete n'est pas très grave
							error: function (jqXHR, textStatus, errorThrown) {
								// L'operation d'autocomp est terminée
								autocomp = null;
							}
						});
					}
					else {
						// On active la premiere tabulation
						tab = true;
					}
					e.preventDefault();
					return false;
				}
				else {
					tab = false;
				}
			});
		}

		var query = function () {
			
			var query = jQuery.trim(inp.val());

			// Nothing typen
			if (query == '') {
				out.val(out.val() + ">\n");
				out[0].scrollTop = out[0].scrollHeight;
				if (passwordInput === null) {
					return;
				}
			}

			// Clean
			if (query == 'cls') {
				out.val('');
				inp.val('').focus();
				return;
			}

			// Désactivation de l'UI
			bt.attr('disabled', 'disabled');

			// Restauration de la mémorisation
			if (lastReturnCode === 254 || lastReturnCode === 252) {
				// Complete query
				if (lastReturnCode === 252) {
					// Traitement spécifique pour les mots de passes cryptés 
					query = lastInput + " --passwdcr=" + WG.security.sha1(WG.security.phpSessionID() + ":" + WG.security.sha1(lastConcat + ":" + query));
				}
				else {
					query = lastInput + " --passwd=" + query;
				}
				// Remove memory
				lastInput = null;
				lastConcat = null;
				// Changement de type
				inp.remove();
				$('#view-monitor #term-in td:nth-of-type(2)').html('<input type="text" />');
				inp = $('#view-monitor #term-in input[type="text"]');
				setInputBehavior(inp);
			}
			else {
				// Scheme
				out.val(out.val() + "> " + query + "\n");
				// Cache
				cache_cur = 0;
				cache.push(inp.val());
			}

			// On vide le contenu pour que l'utilisateur puisse direct continuer à taper
			inp.val('');

			// Webservice query
			WG.ajax({
				url: WG.appURL() + 'ws.php',
				dataType: 'json',
				data: {
					'w': 'exec-cmd',
					'c': query
				},
				success: function (data, textStatus, jqXHR) {

					// Enregistrement du dernier code de retour
					lastReturnCode = data.returnCode;
					
					// Affichage dans la console du résultat
					out.val(out.val() + data.data);
					
					// Traitement de code de retour : entrée d'un mot de passe
					if (data.returnCode === 254 || data.returnCode === 252) {
						// Changement de type pour le field input
						inp.remove();
						$('#view-monitor #term-in td:nth-of-type(2)').html('<input type="password" />');
						inp = $('#view-monitor #term-in input[type="password"]');
						setInputBehavior(inp);
						// Enregistrement de la commande 
						lastInput = query;
						// On enregistre aussi le segment à concatener
						if (data.returnCode === 252) {
							lastConcat = data.concat;
						}
					}
					
					// Autoscroll
					out[0].scrollTop = out[0].scrollHeight;
					
					// Restitution de l'UI
					bt.removeAttr('disabled');
					inp.removeAttr('disabled');
					
					// Au cas où le focus aurait changé
					inp.focus();

					// Scheme
					if (data.user != user) {
						scheme.text(data.user + '@' + host);
						user = data.user;
					}
					
				},
				error: function (jqXHR, textStatus, errorThrown) {
					
					// Message d'erreur
					out.val(out.val() + "Error: " + errorThrown + "\n");
					
					// Autoscroll
					out[0].scrollTop = out[0].scrollHeight;
					
					// Restitution de l'UI
					bt.removeAttr('disabled');
					inp.removeAttr('disabled');

					// Suppression de la mémorisation
					lastInput = null;
					lastConcat = null;
					
				}
			});
			
		};

		out.click(function () {
			inp.focus();
		});

		setInputBehavior(inp);

		bt.click(function () {
			query();
			return false;
		});

		// Fix pour firefox
		out.height(out.parent().height());

	});
	</script>

<?php

endif;

########################################################### U S E R S

if (WG::checkFlags('ua')) :

	echo '<h2>Users</h2>';

	echo '<table class="data"><thead><tr><th>Username</th><th>Flags</th><th>Name</th><th>Email</th><th>Last connection</th>';
	echo '</tr></thead><tbody>';

	$members = ModelManager::get('TeamMember')->all();
	
	foreach ($members as $member) {
		echo '<tr><td>'.htmlspecialchars($member->login).'</td><td>'.$member->flags.'</td>';
		$online = ($member->last_connection) >= (time() - WG::vars('ui_ws_refresh') * 2);
		echo '<td>'.htmlspecialchars($member->name).'</td>';
		echo '<td>'.htmlspecialchars($member->email).'</td>';
		echo '<td><img src="wg/modules/core/public/'.($online ? 'online' : 'offline').'.png" title="'.($online ? 'Online' : 'Offline')
			.'" /> '.WG::rdate($member->last_connection).'</td></tr>';
	}
	
	unset($members);
	
	echo '</tbody></table>';

endif;

########################################################### W G C R T   L O G S

if (WG::checkFlags('uS')) :

	$logfile = WG::base('data/wgcrt.log');
	
	if (@$_REQUEST['action'] === 'showwgcrtlog') {
		$tmp = file_get_contents($logfile);
		if ($tmp !== false) {
			echo '<pre>'.htmlspecialchars($tmp).'</pre><span id="logeof"></span>';
		}
		unset($tmp);
	}
	
	else if (@$_REQUEST['action'] === 'razwgcrtlog') {
		echo '<div class="result resultok">RAZ done</div>';
		@unlink($logfile);
	}
	
	else if (@$_REQUEST['action'] === 'archivewgcrtlog') {
		echo '<div class="result resultok">Log archived</div>';
		if (is_file($logfile)) {
			@rename($logfile, dirname($logfile) . '/' . date('d-m-Y') . '.' . basename($logfile));
		}
	}
	
	echo '<p>Access log: <a href="index.php?view=monitor&action=showwgcrtlog#logeof">Read</a> (';

	if (is_file($logfile)) {
		echo format_bytes(filesize($logfile));
	}
	else {
		echo '0';
	}

	echo ') | <a href="index.php?view=monitor&action=razwgcrtlog">RAZ</a>';
	echo '| <a href="index.php?view=monitor&action=archivewgcrtlog">Archive</a></p>';

endif;

########################################################### C R O N S   J O B S

if (WG::checkFlags('uS')) :

	echo '<h2 id="cronlist">CRON Jobs</h2>';
	echo '<p style="float:right"><a class="button" href="cron.php" target="_blank"><img src="'.$page->core.'resultset_next.png" />&nbsp;Run&nbsp;CRON&nbsp;service</a></p>';
	echo '<p>Last execution of CRON service : <var id="_service">';

	$data = WG::crondata();
	echo $data['_service'] > 0 ? WG::rdate($data['_service']) : 'never';

	if ($data['_service'] <= 0) {
		//echo '<div id="poptop" class="alert">The CRON service has never run yet. <a id="close-poptop">[close]</a></div>';
	}
	else if (time() - $data['_service'] > 3600) {
		//echo '<div id="poptop" class="alert">The CRON service ran for the last time '.strtolower(WG::rdate($data['_service'])).'. <a id="close-poptop">[close]</a></div>';
	}
	
	echo '</var></p>';

	if (isset($_REQUEST['runcronjob'])) {
	
		echo '<pre>';
		echo 'Execute CRON job: ' . $_REQUEST['runcronjob'] . "\n";
		WG::executeCronJob($_REQUEST['runcronjob'], true, true);
		echo '</pre>';
	
	}

	echo '<table class="data"><thead><tr><th>Module</th><th>Name</th><th>Description</th><th>Script</th>';
	echo '<th>Frequency</th><th>Tasks todo</th><th>Last run</th></tr></thead><tbody>';
	
	foreach (WG::cronjobs() as $job) {
	
		echo '<tr><td>'.$job['module'].'</td><td>'.htmlspecialchars($job['name']).'</td><td>'.$job['description'].'</td><td>'.$job['script'].'</td>';
		echo '<td>'.$job['frequency'].'</td>';
		$taskcount = '-';
		if (isset($job['queue_model'])) {
			try {
				WG::model($job['queue_model']);
				$taskcount = ModelManager::get($job['queue_model'])->count();
				$taskcount = intval($taskcount);
			}
			catch (Exception $ex) { throw $ex; }
			if ($taskcount === 0) {
				$taskcount = '0';
			}
		}
		echo '<td>'.$taskcount.'</td><td><a href="index.php?view=monitor&runcronjob='.urlencode($job['name']).'#cronlist" title="Run task: '.htmlspecialchars($job['name']).'"><img src="wg/modules/core/public/resultset_next.png" /></a> ';
		if (isset($job['disabled']) && $job['disabled'] === true) {
			echo '<img src="'.$page->core.'red.png" alt="Disabled" title="Disabled" />';
		}
		else {
			echo '<var id="job_'.$job['name'].'">'.(isset($data['job_'.$job['name']]) ? WG::rdate($data['job_'.$job['name']]) : 'never').'</var>';
		}
		echo '</td></tr>';
	
	}
	
	echo '</tbody></table>';

	$logfile = WG::base('data/cron.log');
	
	if (@$_REQUEST['action'] === 'showcronlog') {
		$tmp = @file_get_contents($logfile);
		if ($tmp !== false) {
			echo '<pre>'.htmlspecialchars($tmp).'</pre><span id="logeof"></span>';
		}
		unset($tmp);
	}
	
	else if (@$_REQUEST['action'] === 'razcronlog') {
		echo '<div class="result resultok">RAZ done</div>';
		@unlink($logfile);
	}
	
	else if (@$_REQUEST['action'] === 'archivecronlog') {
		echo '<div class="result resultok">Log archived</div>';
		if (is_file($logfile)) {
			@rename($logfile, dirname($logfile) . '/' . date('d-m-Y') . '.' . basename($logfile));
		}
	}

	echo '<p>CRON log : <a href="index.php?view=monitor&action=showcronlog#logeof">Read</a> (';

	if (is_file($logfile)) {
		echo format_bytes(filesize($logfile));
	}
	else {
		echo '0';
	}

	echo ') | <a href="index.php?view=monitor&action=razcronlog">RAZ</a> ';
	echo '| <a href="index.php?view=monitor&action=archivecronlog">Archive</a></p>';

	echo <<<_HTML
<script>

//Update cron infos table
WG.live.bind.monitorViewUpdater2 = {
	event: 'croninfo',
	view: 'monitor',
	onChange: function (data) {
		for (service in data) {
			$('#view-monitor #' + service).text(data[service]);
		}
	}
};

</script>
_HTML;

endif;

########################################################### W E B S E R V I C E S

if (WG::checkFlags('ua')) :

	echo '<h2>Webservices</h2>';
	echo '<table class="data"><thead><tr><th>Module</th><th>Name</th><th>Description</th><th>Type</th>';
	echo '<th>Security</th><th>Method</th></tr></thead><tbody>';
	
	foreach (WG::webservices() as $ws) {
	
		echo '<tr><td>'.$ws['module'].'</td><td>'.htmlspecialchars($ws['name']).'</td>';
		echo '<td>'.htmlspecialchars($ws['description']).'</td>';
		echo '<td>'.@$ws['returnType'].'</td>';
		$sec = array();
		if (isset($ws['requireFlags'])) {
			$sec[] = 'Flags=' . $ws['requireFlags'];
		}
		if (isset($ws['aesSupport']) && $ws['aesSupport']) {
			$sec[] = 'aesSupport';
		}
		if (isset($ws['sslOnly']) && $ws['sslOnly']) {
			$sec[] = 'sslOnly';
		}
		if (isset($ws['disableSecurity']) && $ws['disableSecurity']) {
			$sec[] = 'disableSecurity';
		}
		echo '<td>' . implode(' + ', $sec) . '</td>';
		if (isset($ws['disabled']) && $ws['disabled'] === true) {
			echo '<td><img src="'.$page->core.'red.png" alt="Disabled" title="Disabled" /></td></tr>';
		}
		else {
			echo '<td>'.strtoupper($ws['method']).'</td></tr>';
		}
	
	}

	echo '</tbody></table>';

endif;

########################################################### F L A G S

if (WG::checkFlags('ua')) :

	echo '<h2>Flags</h2>';
	echo '<table class="data"><thead><tr><th>Module</th><th>Flag</th><th>Name</th><th>Description</th></tr></thead><tbody>';
	
	foreach (WG::flags() as $flag) {
	
		echo '<tr><td>'.$flag['module'].'</td>';
		echo '<td>'.htmlspecialchars($flag['flag']).'</td>';
		echo '<td>'.htmlspecialchars($flag['name']).'</td>';
		echo '<td>'.@htmlspecialchars($flag['description']).'</td>';
		echo '</tr>';
	
	}

	echo '</tbody></table>';

endif;

########################################################### V I E W S

if (WG::checkFlags('ua')) :

	echo '<h2>Views</h2>';
	echo '<table class="data"><thead><tr><th>Module</th><th>Name</th><th>Security</th><th>Script</th></tr></thead><tbody>';
	
	foreach (WG::views() as $view) {
	
		echo '<tr><td>'.$view['module'].'</td>';
		echo '<td>'.htmlspecialchars($view['name']).'</td>';
		echo '<td>'.(isset($view['requireFlags']) ? 'private ('.$view['requireFlags'].')' : 'public').'</td>';
		echo '<td>'.htmlspecialchars($view['script']).'</td>';
		echo '</tr>';
	
	}

	echo '</tbody></table>';

endif;
	
########################################################### S T O R E S

if (WG::checkFlags('uS')) :

	echo '<h2>Stores</h2>';
	echo '<table class="data"><thead><tr><th>Module</th><th>Name</th><th>Type</th><th>Description</th></tr></thead><tbody>';
	
	foreach (WG::stores() as $store) {
	
		echo '<tr><td>'.$store['module'].'</td>';
		echo '<td>'.htmlspecialchars($store['name']).'</td>';
		echo '<td>'.htmlspecialchars($store['type']).'</td>';
		echo '<td>'.htmlspecialchars($store['description']).'</td>';
		echo '</tr>';
	
	}

	echo '</tbody></table>';

endif;

########################################################### L I V E S   S E R V I C E S

if (WG::checkFlags('uS')) :

	echo '<h2>Live! Services</h2>';
	echo '<table class="data"><thead><tr><th>Module</th><th>Name</th><th>Description</th></tr></thead><tbody>';
	
	foreach (WG::lives() as $live) {
	
		echo '<tr><td>'.$live['module'].'</td>';
		echo '<td>'.htmlspecialchars($live['name']).'</td>';
		echo '<td>'.htmlspecialchars($live['description']).'</td>';
		echo '</tr>';
	
	}
	
	echo '</tbody></table>';

endif;

########################################################### S E S S I O N

if (WG::checkFlags('uS') && WG::vars('dev_mode') === true) :

	echo '<h2> Session</h2>';

	if (@$_REQUEST['action'] === 'clearsession') {
		echo '<div class="result resultok">Session cleared</div>';
		$_SESSION = array();
	}

	echo '<pre>';
	echo 'ID: '.session_id() . "\nNAME: " . session_name() . "\n" . htmlspecialchars(print_r($_SESSION, true));
	echo '</pre><p><a href="index.php?view=monitor&action=clearsession">Clear session</a></p>';

endif;

# Eof
echo '<p># EOF</p></div>';

?>