<?php

// Use $_LIVE to fill result data
// Use $_TIMEREF to compare with dates

// Attention : les CRONDATA sont renvoy�es enti�res.
// Pour le moment ce n'est pas un probl�me car ces donn�es ne contiennent rien de sensible,
// mais il faut faire attention si cela change.
$_LIVE = WG::crondata();

foreach ($_LIVE as $k => &$v) {
	$v = WG::rdate($v);
}

?>