<?php

// Plugin utility functions
require_once 'inc/badge-utils.php';

class qa_badge_widget {

	private $badgeCache = [];  // Cache for badge details by slug to avoid repeated DB calls
	private $userAccounts = []; // Cache for user account info keyed by handle for avatar & other info
	
	// Allow this widget to appear on all templates
	function allow_template($template) {
		return true;
	}

	// Allow this widget to appear in all page regions
	function allow_region($region) {
		return true;
	}

	// Output widget HTML
	function output_widget($region, $place, $themeobject, $template, $request, $qa_content) {
		if (!qa_opt('event_logger_to_database')) {
			return;
		}
		
		// Get badges for users (optimized query)
		$badgeResult = $this->get_badges();

		// Collect all badge rows and user handles
		$badges = [];
		$handles = [];
		
		// Read each badge row from DB result, collecting badges and user handles
		while (($badge = qa_db_read_one_assoc($badgeResult, true)) !== null) {
			$badges[] = $badge;
			$handles[] = $badge['handle'];
		}

		if (empty($badges)) {
			return;
		}

		// Batch fetch user accounts
		$this->userAccounts = $this->fetch_user_accounts(array_unique($handles));

		$themeobject->output('<div class="qa-badges-widget">');
		$first = true;

		foreach ($badges as $badge) {
			$params = $this->parse_badge_params($badge['params']);
			
			// Skip badges without valid slug param
			if (empty($params['badge_slug'])) {
				continue;
			}

			$badgeDetails = $this->get_badge_details($params['badge_slug']);
			
			// Skip if badge type doesn't exist
			if (!$badgeDetails) {
				continue;
			}

			$awardedTime = $this->get_awarded_time($badge['datetime']);
			$badgeHTML = $this->build_badge_html($badge, $badgeDetails, $awardedTime);
			
			// Output widget header and container div only once, before first badge
			if ($first) {
				$this->output_badge_header($themeobject);
				$themeobject->output('<div class="badges-widget-container">');
				$first = false;
			}
			
			// Output the badge entry
			$themeobject->output('<div class="badge-widget-entry">', $badgeHTML, '</div>');
		}

		if (!$first) {
			$themeobject->output('</div>'); // badges-widget-container
		}

		$themeobject->output('</div>'); // qa-badges-widget
	}
	
	/**
	 * Retrieves user account information for the given list of user handles.
	 *
	 * @param array $handles List of user handles (usernames) to fetch.
	 * @return array Associative array keyed by handle, each containing user data:
	 *               userid, handle, email, flags, and avatarblobid.
	 *               Returns an empty array if no handles provided or no matches found.
	 */
	private function fetch_user_accounts($handles) {
		if (empty($handles)) return [];

		$results = qa_db_read_all_assoc(
			qa_db_query_sub(
				'SELECT userid, handle, email, flags, avatarblobid FROM ^users WHERE handle IN ($)',
				$handles
			)
		);

		$userAccounts = [];
		foreach ($results as $user) {
			$userAccounts[$user['handle']] = $user;
		}
		return $userAccounts;
	}
	
	/**
	 * Retrieves recent badge-awarded events from the event log.
	 *
	 * Fetches rows from the ^eventlog table where the event is 'badge_awarded',
	 * joined with the users table to ensure the user is valid.
	 * Limits results by date and count based on plugin options.
	 *
	 * @return mysqli_result|bool The result set from the database query, or false on failure.
	 */
	private function get_badges() {
		return qa_db_query_sub(
			'SELECT el.event, el.handle, el.params, el.userid, UNIX_TIMESTAMP(el.datetime) AS datetime
			FROM ^eventlog el
			JOIN ^users u ON el.userid = u.userid
			WHERE el.event=$
			AND u.userid IS NOT NULL ' . 
			(qa_opt('badge_widget_date_max') ? ' AND DATE_SUB(CURDATE(), INTERVAL ' . (int) qa_opt('badge_widget_date_max') . ' DAY) <= el.datetime' : '') . 
			' ORDER BY el.datetime DESC' .
			(qa_opt('badge_widget_list_max') ? ' LIMIT ' . (int) qa_opt('badge_widget_list_max') : ''),
			'badge_awarded'
		);
	}
	
	/**
	 * Parses a tab-delimited badge parameter string into an associative array.
	 *
	 * Expected input format: "key1=value1\tkey2=value2\t..."
	 * Used to extract badge-related data (e.g., badge_slug) from the event 'params' string.
	 *
	 * @param string|null $paramsString The raw parameter string from the event log.
	 * @return array Associative array of parameter key-value pairs.
	 */
	private function parse_badge_params($paramsString) {
		$params = [];
		$paramsa = explode("\t", $paramsString ?? '');
		foreach ($paramsa as $param) {
			$pair = explode('=', $param ?? '');
			if (count($pair) === 2) {
				$params[$pair[0]] = $pair[1];
			}
		}
		return $params;
	}
	
	/**
	 * Retrieves detailed information about a badge by its slug.
	 * 
	 * Caches results to avoid repeated database or option lookups.
	 * Returns badge slug, name, description, and type.
	 *
	 * @param string $slug The unique badge identifier.
	 * @return array|null Associative array of badge details, or null if not found.
	 */
	private function get_badge_details($slug) {
		if (isset($this->badgeCache[$slug])) {
			return $this->badgeCache[$slug];
		}

		$typea = qa_get_badge_type_by_slug($slug);
		if (!$typea) return null;

		$name = qa_opt('badge_' . $slug . '_name') ?: qa_badge_name($slug);
		$desc = qa_badge_desc_replace($slug, qa_opt('badge_' . $slug . '_var'), false);

		return $this->badgeCache[$slug] = [
			'slug' => $slug,
			'name' => $name,
			'desc' => $desc,
			'type' => strtolower($typea['name'])
		];
	}
	
	/**
	 * Formats the badge award timestamp into readable date strings.
	 *
	 * @param int $badgeTime The Unix timestamp when the badge was awarded.
	 * @return array Contains:
	 *               - 'time_ago': Human-readable time difference (e.g. "3 days ago").
	 *               - 'full_date': Formatted date string (YYYY-MM-DD).
	 */
	private function get_awarded_time($badgeTime) {
		return [
			'time_ago' => qa_lang_html_sub('main/x_ago', qa_time_to_string(qa_opt('db_time') - $badgeTime)),
			'full_date' => date('Y-m-d', $badgeTime)
		];
	}
	
	/**
	 * Builds the HTML structure for displaying an awarded badge.
	 *
	 * @param array $badge Badge data from the database (e.g. handle, datetime).
	 * @param array $badgeDetails Details about the badge (e.g. name, slug, type, description).
	 * @param array $awardedTime Array with 'time_ago' and 'full_date' of when the badge was awarded.
	 * @return string HTML block showing the badge, user avatar, and award info.
	 */
	private function build_badge_html($badge, $badgeDetails, $awardedTime) {
		$handle = qa_html($badge['handle']);
		$badgeSlug = qa_html($badgeDetails['slug']);
		$badgeName = qa_html($badgeDetails['name']);
		$badgeDesc = qa_html($badgeDetails['desc']);
		$badgeType = qa_html($badgeDetails['type']);
		$langEarnedBadge = qa_lang('badges/widget_badge_earned');

		$avatarUrl = get_user_avatar($handle, 70);  // Use util function
		$avatarHtml = generate_avatar_html($handle, $avatarUrl, 35);  // Use util function

		$imgSrc = qa_opt('site_theme') == 'Polaris'
			? 'data-src="'.$avatarUrl.'" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="'
			: 'src="'.$avatarUrl.'"';

		$siteUrl = qa_opt('site_url');
		$badgeUrl = $siteUrl . 'badges#badge-anchor-' . $badgeSlug;

		return <<<HTML
		<div class="badge-awarded">
			<div class="badge-awarded-header">
				<div class="badge-awarded-avatar">
					{$avatarHtml}
				</div>
				<div class="badge-awarded-info">
					<span class="wibawho-wrapper">
						<a class="wibawho" href="user/{$handle}">{$handle}</a>
					</span>
					<span class="wibawhat">
						<span class="wibawhat-text">{$langEarnedBadge}</span>
					</span>
				</div>
				<div class="clear" style="clear:both;"></div>
			</div>
			<div class="badge-awarded-footer">
				<span class="wibawhat-time">
					<span class="wibawhat-timestamp" title="{$awardedTime['full_date']}">{$awardedTime['time_ago']}</span>
				</span>
				<span class="wibabadge">
					<a href="{$badgeUrl}" title="{$badgeName} - {$badgeDesc} ({$badgeType})">
						<span class="badge-{$badgeType}">{$badgeName}</span>
					</a>
				</span>
				<div class="clear" style="clear:both;"></div>
			</div>
		</div>
HTML;

	}
	
	// Wdiget Header
	private function output_badge_header($themeobject) {
		$svgIcon = '
			<span class="badges-svg">
				<span class="svg-box-container">
					<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="18" height="18" viewBox="0, 0, 400,321.875">
						<g class="svgg">
							<path d="M193.862 1.328 C 192.384 2.056,190.115 4.351,188.665 6.583 C 185.632 11.256,109.758 142.835,108.480 145.641 C 107.683 147.391,110.761 153.064,144.004 211.111 C 164.023 246.066,181.281 276.586,182.355 278.934 C 187.466 290.103,188.092 303.089,184.028 313.672 C 182.874 316.680,181.735 319.756,181.498 320.508 C 181.096 321.785,182.360 321.875,200.710 321.875 L 220.353 321.875 219.075 319.404 C 214.497 310.551,212.840 297.370,215.162 288.281 C 217.163 280.448,218.526 277.930,256.535 211.808 C 276.144 177.696,292.187 149.426,292.188 148.984 C 292.188 147.980,211.238 7.403,209.033 4.576 C 205.625 0.209,199.008 -1.208,193.862 1.328 M8.968 42.108 C 5.575 42.956,2.438 45.208,1.132 47.733 C -0.678 51.234,-0.561 308.347,1.252 312.168 C 6.009 322.193,3.941 321.944,80.616 321.697 L 146.484 321.484 150.391 319.406 C 160.216 314.177,165.232 303.103,162.567 292.522 C 161.702 289.086,120.522 216.519,51.985 97.656 C 21.267 44.382,21.516 44.790,18.816 43.393 C 16.005 41.940,11.824 41.395,8.968 42.108 M384.736 42.239 C 379.951 43.627,383.712 37.328,282.993 212.607 C 232.927 299.735,237.913 290.242,237.911 298.438 C 237.909 308.250,241.840 314.856,250.391 319.406 L 254.297 321.484 320.313 321.720 C 396.991 321.993,394.272 322.299,398.532 312.923 C 400.846 307.827,400.786 50.908,398.469 47.108 C 395.980 43.027,389.700 40.800,384.736 42.239"></path>
						</g>
					</svg>
				</span>
			</span>
		';
		
		$themeobject->output('<h2>' . $svgIcon . qa_lang('badges/badge_widget_title') . '</h2>');
	}
	
}

/*
	Omit PHP closing tag to help avoid accidental output
*/
