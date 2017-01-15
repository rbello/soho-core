<?php

/**
 * Cet objet permer de r�cup�rer les derniers logs, et de les
 * regrouper dans des stacks coh�rentes.
 *
 * Il y a deux types d'organisation :
 *  - centr�e membre de l'�quipe
 *  - centr�e subject
 *
 */
class LogStackParser {

	public $data;

	public function __construct($stackCount=5, $memberCenter=false) {

		$this->data = array();

		$logs = ModelManager::get('Log')->get(array(), array('*'), 'creation DESC', 100);

		$current = null;

		foreach ($logs as $log) {

			// Find the target
			if (WG::model($log->target_type)) {
				$target = ModelManager::get($log->target_type)->getbyid($log->target_id);
			}
			else {
				$target = $log->target_name;
			}

			if (!$target && $log->type != 'delete') {
				//echo "[target not found: {$log->target_type} #{$log->target_id}]";
				continue;
			}

			$stackname = $memberCenter ? ($log->user ? $log->user->id : -1) : $log->target_name;

			if ($current === null || $current['name'] !== $stackname) {
				if ($current !== null) {
					$this->data[] = $current;
					$current = null;
				}
				if (sizeof($this->data) >= $stackCount) {
					break;
				}
				$current = array(
					'name' => $stackname,
					'title' => $memberCenter ? ($log->user ? $log->user->name : ''): $log->target_name,
					'logs' => array()
				);
				if ($memberCenter) {
					$current['user'] = $log->user;
				}
				else {
					$current['target'] = $target;
				}
			}

			$current['logs'][] = $log;

		}

		if ($current !== null) {
			$this->data[] = $current;
		}

	}


}


?>