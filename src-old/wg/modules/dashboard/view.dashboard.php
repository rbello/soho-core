<h1>Dashboard</h1>
<div class="rightcommands">
	<a class="button" href="https://workshop.evolya.fr/owncloud" target="_blank">Owncloud</a>
	<a class="button" href="index.php?view=widgetmanager"><img src="<?php echo $page->resources; ?>widgets.png" />&nbsp;Manage&nbsp;Widgets</a>
</div>

<div class="leftcol">

<?php

// On parcours les widgets
foreach (WG::user()->getWidgets() as $widget) {

	// On demande d'obtenir un objet Widget
	$obj = WG::widgets()->createByModel($widget);

	if (!is_object($obj)) {
		// TODO Afficher un message d'erreur
		continue;
	}

	echo '<div class="widget widget-'.$widget->get('class').'">';
	echo '<h2>'.htmlspecialchars($widget->get('class')).'</h2>'; // TODO Recuperer le vrai titre
	echo $obj->html();
	echo '</div>';
	
}

?>
</div>

<div class="rightcol">
<h2>OwnCloud</h2>
<p><a href="https://workshop.evolya.fr/owncloud" target="_blank">Owncloud</a></p>
<p><a href="http://workshop.evolya.fr/public/owncloud-1.2.1-setup.exe" target="_blank">Client (Windows)</a></p>

<?php

echo '<div class="widget widget-LastLogsWidget">';
echo '<h2>Last logs</h2>';
$w = WG::widgets()->createByType('LastLogsWidget', WG::user());
echo $w->html();
echo '</div>'

?>
</div>