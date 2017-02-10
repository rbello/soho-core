<?php

namespace Soho\UI;

class ViewPlugin implements \SohoPlugin {

	public function onStart(\Soho $soho) {
		$soho->on('http request', array($this, '__onHttpRequest'));
	}

	public function __onHttpRequest() {
		echo "YOUPI!!!";
	}

}