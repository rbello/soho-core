<?php

class Soho_CLI_Stats extends Soho_CLI_Soho {	

	/**
	 * Display analytics data of evolya.fr website.
	 *
	 * @requireFlags s
	 * @allowedParams
	 * @cmdPackage Application data
	 */
	function handle_stats($file, $cmd, $params, $argv) {
		if (!$this->check()) {
			return false;
		}
		if (isset($params['help']) || !isset($params[0])) {
			echo "Usage: $cmd today" . PHP_EOL;
			echo "Usage: $cmd year" . PHP_EOL;
			echo "Usage: $cmd month" . PHP_EOL;
			echo "Usage: $cmd week" . PHP_EOL;
			return false;
		}
		switch (strtolower(trim($params[0]))) {
			
			case 'today' :

				$q = mysql_query("SELECT * FROM `mimo_store` WHERE `name` = 'analytics' ORDER BY `update` DESC LIMIT 2");
				$last = mysql_fetch_assoc($q);
				$past = mysql_fetch_assoc($q);
				echo "Date: " . date('d/m/Y', $last['update']) . " (" . WG::rdate($last['update']) . ")" . PHP_EOL;
				echo "-----------------------------------------------------" . PHP_EOL;
				$data = unserialize($last['data']);
				
				// visits1year => 12 mois x [ time/jour => visitCount ]
				// visits14days => 14 jours x [ time/jour => [ timestamp => int, date => yyyy-mm-dd, visits => int, visitors => int ] ]
                // visitsYesterday => [ total => int, direct => int, referral => int, organic => int ]
				// referrals30days => N x [ string sitename => int ]
				// keywords7days => N x [ string keyword => int ]
				// yesterday => [
				//	medium => [ total => int, direct => int, referral => int, organic => int ],
				//	keywords => N x [ string keyword => int ],
				//	referral => N x [ string sitename => int ],
				//	contents => N x [ title => string, visits => int, url => string ]
				// ]
				// keysYesterday => [
				//	entranceBounceRate => [ Now => int, Past => int ],
				//	avgTimeOnSite => [ Now => int, Past => int ],
				//	avgTimeOnPage => [ Now => int, Past => int ],
				//	pageviewsPerVisit => [ Now => int, Past => int ],
				//	isMobile => [ Now => [ Y => int%, N => int% ], Past => [ Y => int%, N => int% ] ]
				// ]
				
				$tmp = $data['keysYesterday'];
				echo "Entrance bounce rate: " . round($tmp['entranceBounceRate']['Now'], 2) . "% (";
				echo ($tmp['entranceBounceRate']['Now'] > $tmp['entranceBounceRate']['Past']) ? '+' : '';
				$v = round(($tmp['entranceBounceRate']['Now'] / $tmp['entranceBounceRate']['Past'] * 100), 2);
				echo ($v < 0 ? $v + 100 : $v - 100) . '%)';
				
				echo PHP_EOL . "Average time on site: " . round($tmp['avgTimeOnSite']['Now'], 2) . " seconds (";
				echo ($tmp['avgTimeOnSite']['Now'] > $tmp['avgTimeOnSite']['Past']) ? '+' : '';
				$v = round(($tmp['avgTimeOnSite']['Now'] / $tmp['avgTimeOnSite']['Past'] * 100), 2);
				echo ($v < 0 ? $v + 100 : $v - 100) . '%)';
				
				echo PHP_EOL . "Average time on page: " . round($tmp['avgTimeOnPage']['Now'], 2) . " seconds (";
				echo ($tmp['avgTimeOnPage']['Now'] > $tmp['avgTimeOnPage']['Past']) ? '+' : '';
				$v = round(($tmp['avgTimeOnPage']['Now'] / $tmp['avgTimeOnPage']['Past'] * 100), 2);
				echo ($v < 0 ? $v + 100 : $v - 100) . '%)';
				
				echo PHP_EOL . "Pageviews per visit: " . round($tmp['pageviewsPerVisit']['Now'], 2) . " seconds (";
				echo ($tmp['pageviewsPerVisit']['Now'] > $tmp['pageviewsPerVisit']['Past']) ? '+' : '';
				$v = round(($tmp['pageviewsPerVisit']['Now'] / $tmp['pageviewsPerVisit']['Past'] * 100), 2);
				echo ($v < 0 ? $v + 100 : $v - 100) . '%)';
				
				break;
			
			default :
				echo "Error: invalid option '{$params[0]}'" . PHP_EOL;
				return false;

		}
		return true;
	}
	
}

?>