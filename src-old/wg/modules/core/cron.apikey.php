<?php

echo date('r') . " [Cronjob apikey_changer] Start:\n";

$c =  0;

foreach (TeamMember::all() as $member) {

	// Generate new API key
	$newkey = getRandomKey(48);

	// Change API key
	$member
		->set('apikey', $newkey)
		->save();

	// Create mail content
	$contents = file_get_contents(WG::base($job['mail-template']));

	// Create mail
	$mail = ModelManager::get('EmailCronTask')->new
		->set('to', $member->email)
		->set('creation', time())
		->set('title', $job['mail-title']);
	$mail->contents = str_replace(
		array(
			'%member_id%',
			'%member_name%',
			'%member_email%',
			'%member_avatar%',
			'%newkey%',
			'%app_name%',
			'%app_owner%'
		),
		array(
			$member->id,
			utf8_decode($member->name),
			$member->email,
			$member->thumb,
			$newkey,
			utf8_decode(WG::vars('appName')),
			utf8_decode(WG::vars('appOwner'))
		),
		$contents
	);

	// Send mail to user
	echo date('r') . " [Cronjob apikey_changer] Add mail to send: {$member->email} ...";
	$mail->save();
	echo " done\n";

	$c++;
}

echo date('r') . " [Cronjob apikey_changer] Finish: $c operation(s).\n";

?>