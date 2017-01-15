<?php

$c =  0;

$emails = ModelManager::get('EmailCronTask')->all();
$todo = array();

$emailcron_send_per_exec = WG::vars('emailcron_send_per_exec');

while (sizeof($emails) > 0 && $emailcron_send_per_exec > 0) {
	$todo[] = array_pop($emails);
	$emailcron_send_per_exec--;
}

if (sizeof($todo) > 0) {

	// Import library
	WG::lib('Mailer.php');

	foreach ($todo as $task) {
		// Send the mail
		try {
			$mail = new Mailer();
			$mail->from = WG::vars('contact_email');
			$mail->website = 'http://'.WG::vars('host').'/';
			$mail->title = $task->title;
			$mail->content = $task->contents;

			//
			if ($task->from != '') $mail->from = $task->from;

			// Dev mode : back to sender
			$email = WG::vars('dev_mode') ? WG::vars('contact_email') : $task->to;

			if (!$mail->send($email)) {
				throw new Exception("unknown error");
			}
			echo date('r') . " [Cronjob mailer_service] Send mail to {$email}\n";
			$task->delete();
			$c++;
		} catch (Exception $ex) {
			echo date('r') . " [Cronjob mailer_service] ERROR Unable to send mail to {$email} [".$ex->getMessage()."]\n";
		}
	}
}

if ($c > 0) {
	echo date('r') . " [Cronjob mailer_service] Finish: $c operation(s), ".sizeof($emails)." todo later.\n";
}

?>
