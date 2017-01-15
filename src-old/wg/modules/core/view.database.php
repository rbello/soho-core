<div class="view" id="view-database">
	<h1>Database</h1>
	<iframe src="<?php

	echo
		WG::vars('appurl')
		. 'ws.php?w='
		. (WG::useAES() ? WG::aesEncrypt('mysql-adminer') : 'mysql-adminer')
		. '&_tpx=' . time();

	?>" id="adminer-frame" class="fit-height" style="width:100%"></iframe>
</div>