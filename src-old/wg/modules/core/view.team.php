<div class="view" id="view-team">
	<h1>Team</h1>
	
<?php

$team = ModelManager::get('TeamMember')->all();

$mimo_account_username = WG::vars('mimo_account_username');

foreach ($team as $member) {

	if ($member->login == 'root' || $member->login == $mimo_account_username) {
		continue;
	}

	echo '<div quicksearch="'.htmlspecialchars($member->name).'/'.$member->email.'" url="mailto:'.$member->email.'" class="card">';
	echo '<img class="avatar" src="data/team/'.$member->thumb.'" />';
	echo '<h2>'.htmlspecialchars($member->name).'</h2>';
	echo '<p>Color: <span class="color-sample" style="background-color:'.$member->color.'"></span></p>';
	echo '<p>Mail: <a href="mailto:'.$member->email.'">'.htmlspecialchars($member->email).'</a></p>';
	// Feeds
	if (WG::module('activity') && WG::checkFlags('a')) {
		echo '<p style="margin-top:8px"><img src="wg/modules/activity/public/feed.png" /> <strong>Feeds</strong></p>';
		$activitymgr = ModelManager::get('Activity');
		$follow = ModelManager::get('Follow')->get(array('user' => $member->id, 'target_type' => 'activity'));
		foreach ($follow as $f) {
			$a = $activitymgr->getbyid($f->target_id);
			if (!$a) continue;
			echo '<p>&nbsp;<a href="index.php?view=activity&open='.$a->id.'#row'.$a->id.'">'.htmlspecialchars($a->name).'</a></p>';
		}
		if (sizeof($follow) == 0) {
			echo '<p>&nbsp;No feed</p>';
		}
	}
	// Widgets
	if (WG::module('dashboard') && WG::checkFlags('a')) {
		echo '<p style="margin-top:8px"><img src="wg/modules/dashboard/public/widgets.png" /> <strong>Widgets</strong></p>';
		$widgets = ModelManager::get('Widget')->get(array('user' => $member->id));
		foreach ($widgets as $w) {
			echo '<p>&nbsp;'.$w->class.'</p>';
		}
		if (sizeof($widgets) == 0) {
			echo '<p>&nbsp;No widget</p>';
		}
	}
	echo '</div>';
}

?>
</div>