<?php

$c =  0;

$feeds = WG::vars('rss_feeds');

function log_new_article($sitename, $siteurl, $title, $text, $url) {
	WG::log(
		'RssArticle',
		-1,
		$sitename,
		'create',
		'<img src="'.WG::url('modules/activity/public/feed.png').'" /> '.$title.' <blockquote>'.$text.'</blockquote> Published on <a href="'.$url.'" target="_blank" rel="nofollow">'.$sitename.'</a>',
		true
	);
}

$datafile = WG::base('data/rss-feeds.dat');

$data = array();

// Restore data
if (is_file($datafile) && is_readable($datafile)) {
	$tmp = @file_get_contents($datafile);
	if ($tmp) {
		$tmp = @unserialize($tmp);
		if (is_array($tmp)) {
			$data = $tmp;
		}
		else {
			echo date('r') . " [Cronjob rss_reader]   Error: unable to unzerialize data file ($datafile)\n";
		}
	}
	else {
		echo date('r') . " [Cronjob rss_reader]   Error: unable to read data file ($datafile)\n";
	}
	unset($tmp);
}

define('MAGPIE_CACHE_DIR', WG::base('../cache'));
require_once WG::base('modules/dashboard/magpierss-0.72/rss_fetch.inc');

foreach ($feeds as $feed) {

	$rss = @fetch_rss($feed['url']);

	if (!$rss) {
		echo date('r') . " [Cronjob rss_reader]   Error: unable to fetch url {$feed['url']}\n";
		continue;
	}

	foreach ($rss->items as $item ) {

		$url = $item['link'];

		// New article found
		if (!in_array($url, $data)) {
			// Publish a log
			log_new_article(
				$feed['name'],
				$feed['url'],
				$item['title'],
				substr(strip_tags($item['description']), 0, WG::vars('rss_description_length')) . '..',
				$url
			);
			// Save url
			$data[] = $url;
			// Operation
			$c++;
			echo date('r') . " [Cronjob rss_reader]   News link: $url\n";
		}

	}
}

// Save data
@file_put_contents($datafile, serialize($data));

if ($c > 0) {
	echo date('r') . " [Cronjob rss_reader] Finish: $c operation(s).\n";
}

?>
