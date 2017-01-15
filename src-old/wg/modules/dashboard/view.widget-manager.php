<h1>Widgets</h1>
<div class="rightcommands">
	<a class="button" href="index.php?view=dashboard"><img src="<?php echo $page->resources; ?>widgets.png" />&nbsp;My&nbsp;Dashboard</a>
</div>
<div class="leftcol">
<?php

//// Install

if (isset($_REQUEST['add'])) {

	$widget = WG::widgets($_REQUEST['add']);
	
	if ($widget) {
		
		// TODO Rights
		
		// TODO Configuration du widget
		
		ModelManager::get('Widget')->new
			->set('user', WG::user()->get('id'))
			->set('class', $widget['name'])
			->save();
		
		echo '<p class="result resultok">Widget <b>'.htmlspecialchars($_REQUEST['add']).'</b> added to your dashboard.</p>';
		
	}
	else {
		echo '<p class="result resulterror">Widget not found: <b>'.htmlspecialchars($_REQUEST['add']).'</b>.</p>';
	}

}

//// Remove

else if (isset($_REQUEST['remove'])) {

	$widget = ModelManager::get('Widget')->getById(intval($_REQUEST['remove']), 1);

	if (!$widget) {
		echo '<p class="result resulterror">Widget not found.</p>';
	}
	else if ($widget->get('user')->get('id') !== WG::user()->get('id')) {
		echo '<p class="result resulterror">Forbidden.</p>';
	}
	else {
		echo '<p class="result resultok">Widget removed.</p>';
		$widget->delete();
	}

}


?>
	<table class="receipt widget-list">
		<thead>
			<tr>
				<th width="55px"></th>
				<th width="*">Widget</th>
				<th width="1%"></th>
			</tr>
		</thead>
		<tbody>
<?php

$userWidgets = WG::user()->getWidgets();

foreach (WG::widgets() as $widget) {
	
	// On verifie que le plugin soit installé
	$installed = null;
	foreach ($userWidgets as $w) {
		if ($w->get('class') == $widget['name']) {
			$installed = $w;
			break;
		}
	}
	
	$title = $widget[isset($widget['title']) ? 'title' : 'name'];
	$title = htmlspecialchars($title);
	
	echo '<tr>';
	if (isset($widget['icon50x50'])) {
		echo '<td><img src="'.WG::url($widget['icon72x72']).'" alt="'.$title.'" /></td>';
	}
	else if (isset($widget['icon72x72'])) {
		echo '<td><img src="'.WG::url($widget['icon72x72']).'" alt="'.$title.'" /></td>';
	}
	else {
		echo '<td></td>';
	}
	echo '<td>';
	echo '<h3>'.$title.'</h3>';
	//echo '<p>Module: '.htmlspecialchars($widget['module']).'</p>';
	if (isset($widget['desc'])) {
		echo '<p>'.htmlspecialchars($widget['desc']).'</p>';
	}
	echo '</td>';
	echo '<td>';
	if ($installed) {
		echo '<a class="button" href="index.php?view=widgetmanager&remove='.$installed->get('id').'">Remove</a>';
	}
	else {
		echo '<a class="button-blue" href="index.php?view=widgetmanager&add='.$widget['name'].'">Install</a>';
	}
	echo '</td>';
	echo '</tr>';
	
}


?>
		</tbody>
	</table>

</div>