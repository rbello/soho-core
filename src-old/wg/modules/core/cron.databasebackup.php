<?php

$db = WG::database();

$dumpfile = WG::base('data/db_backup/'.$db->getDatabaseName().'_'.date('Y-m-d_H-i').'.sql');

$fp = fopen($dumpfile, 'w');

if (!is_resource($fp)) {
	echo date('r') . " [Cronjob database_backup] Backup failed: unable to open dump file.\n";
	return;
}

echo date('r') . " [Cronjob database_backup] Start:\n";

$c =  0;

$tables = $db->query("SHOW TABLE STATUS");
$out = '-- '.WG::vars('appName').' SQL Dump
-- version '.WG::vars('appVersion').'
-- http://'.WG::vars('host').'/
--
-- Generation: '.date('r').'
-- MySQL version: '.mysql_get_server_info().'
-- PHP version: '.phpversion().'

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `'.$db->getDatabaseName().'`
--';

fwrite($fp, $out);
$out = '';

foreach ($tables as $table) {

	$tmp = $db->query("SHOW CREATE TABLE `{$table->Name}`");
	if ($tmp->size() !== 1) {
		echo date('r') . "     ERROR: unable to execute \"SHOW CREATE TABLE `{$table->Name}`\"\n";
		continue;
	}

	// Create table
	$out .= "\n\n--\n-- Table structure: `{$table->Name}`\n--\n\n".$tmp->current()->__get('Create Table').' ;';
	$tmp->destroy();
	unset($tmp);

	// Save
	fwrite($fp, $out);
	$out = '';

	// Rows
	$tmp = $db->query("SHOW COLUMNS FROM `{$table->Name}`");
	$rows = array();
	foreach ($tmp as $row) {
		$rows[] = $row->Field;
	}
	unset($tmp, $row);

	// Data
	$tmp = $db->query("SELECT * FROM `{$table->Name}`");
	if ($tmp->size() > 0) {
		$out .= "\n\n--\n-- Table data: `{$table->Name}`\n--";
		$out .= "\nINSERT INTO `{$table->Name}` (`".implode('`, `', $rows)."`) VALUES ";
		$count = $tmp->size();
		$i = 1;
		foreach ($tmp as $entry) {
			// Create values
			$out .= "\n(";
			$tmp2 = array();
			foreach ($rows as $row) {
				$tmp2[] = "'" . $db->escapeString($entry->get($row)) . "'";
			}
			$out .= implode(', ', $tmp2);
			$out .= $i++ === $count ? ');' : '),';
			unset($tmp2);
			// Save
			fwrite($fp, $out);
			$out = '';
		}
		$tmp->destroy();
		unset($tmp, $i, $count, $entry);
	}

	// Operations counter
	$c++;

}

fclose($fp);

echo date('r') . " [Cronjob database_backup] Finish backup: $c tables (".format_bytes(filesize($dumpfile)).").\n";

?>