<?php

// Use $_LIVE to fill result data
// Use $_TIMEREF to compare with dates

exec('top -b -n 1', $top);

if (is_array($top) && sizeof($top) > 0) {

	// CPU usage
	$cpu = explode('%us', $top[2]);
	$cpu = intval(str_replace('Cpu(s):', '', $cpu[0]));

	// Memory
	$mem = explode('k ', $top[3]);
	$mem = round(str2int($mem[1]) / str2int($mem[0]) * 100);

	$_LIVE['cpu'] = $cpu;
	$_LIVE['memory'] = $mem;
	
}

else {

	$_LIVE['cpu'] = 0;
	$_LIVE['memory'] = 0;

}

?>