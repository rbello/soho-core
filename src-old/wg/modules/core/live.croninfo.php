<?php

// Use $_LIVE to fill result data
// Use $_TIMEREF to compare with dates

// Attention : les CRONDATA sont renvoyées entières.
// Pour le moment ce n'est pas un problème car ces données ne contiennent rien de sensible,
// mais il faut faire attention si cela change.
$_LIVE = WG::crondata();

foreach ($_LIVE as $k => &$v) {
	$v = WG::rdate($v);
}

?>