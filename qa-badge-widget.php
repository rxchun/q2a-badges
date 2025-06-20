<?php

class qa_badge_widget {

	// Allow template for the widget
	function allow_template($template) {
		return true;
	}

	// Allow region for the widget
	function allow_region($region) {
		return true;
	}

	// Output widget HTML
	function output_widget($region, $place, $themeobject, $template, $request, $qa_content) {
		$themeobject->output('<div class="qa-badges-widget">');

		// Check if event logging is enabled
		if (!qa_opt('event_logger_to_database')) {
			return;
		}

		// Get badges for users (optimized query)
		$badges = $this->get_badges();

		$first = true;

		// Loop through each badge and render
		while (($badge = qa_db_read_one_assoc($badges, true)) !== null) {
			$params = $this->parse_badge_params($badge['params']);

			if (!isset($params['badge_slug'])) {
				continue; // Skip if badge_slug is not found
			}

			$badgeDetails = $this->get_badge_details($params['badge_slug']);
			if (!$badgeDetails) {
				continue; // Skip if badge type doesn't exist
			}

			$userAvatar = $this->get_user_avatar($badge['handle'], 70);
			$awardedTime = $this->get_awarded_time($badge['datetime']);

			$badgeHTML = $this->build_badge_html($badge, $badgeDetails, $userAvatar, $awardedTime);

			// Output the first badge header
			if ($first) {
				$this->output_badge_header($themeobject);
				$themeobject->output('<div class="badges-widget-container">');
				$first = false;
			}

			// Output the badge entry
			$themeobject->output('<div class="badge-widget-entry">', $badgeHTML, '</div>');
		}

		$themeobject->output('</div></div>'); // END qa-badges-widget + badges-widget-container
	}

	// Get badges from the database
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

	// Parse badge parameters
	private function parse_badge_params($paramsString) {
		$params = [];
		$paramsa = explode("\t", $paramsString ?? '');
		foreach ($paramsa as $param) {
			$parama = explode('=', $param ?? '');
			$params[$parama[0]] = $parama[1];
		}
		return $params;
	}

	// Get badge details (type, name, description)
	private function get_badge_details($slug) {
		$typea = qa_get_badge_type_by_slug($slug);
		if (!$typea) {
			return null;
		}

		$badge_name = qa_badge_name($slug);
		if (!qa_opt('badge_' . $slug . '_name')) {
			qa_opt('badge_' . $slug . '_name', $badge_name);
		}

		$var = qa_opt('badge_' . $slug . '_var');
		$name = qa_opt('badge_' . $slug . '_name');
		$desc = qa_badge_desc_replace($slug, $var, false);

		return [
			'slug' => $typea['slug'],
			'name' => $name,
			'desc' => $desc,
			'type' => $typea['name']
		];
	}

	// Get user avatar
	private function get_user_avatar($handle, $size) {
		$useraccount = qa_db_single_select(qa_db_user_account_selectspec($handle, false));
		$defaultBlobId = qa_opt('avatar_default_blobid');
		
		if (qa_opt('avatar_allow_gravatar') && (@$useraccount['flags'] & QA_USER_FLAGS_SHOW_GRAVATAR)) {
			return sprintf(
				'%s://www.gravatar.com/avatar/%s?s=%s',
				qa_is_https_probably() ? 'https' : 'http',
				md5(strtolower(trim($useraccount['email']))),
				$size
			);
		} elseif (qa_opt('avatar_allow_upload') && (@$useraccount['flags'] & QA_USER_FLAGS_SHOW_AVATAR) && isset($useraccount['avatarblobid'])) {
			return qa_path('image', ['qa_blobid' => $useraccount['avatarblobid'], 'qa_size' => $size], qa_path(''), QA_URL_FORMAT_PARAMS);
		} elseif ((qa_opt('avatar_allow_gravatar') || qa_opt('avatar_allow_upload')) && qa_opt('avatar_default_show') && !empty($defaultBlobId)) {
			return qa_path('image', ['qa_blobid' => qa_opt('avatar_default_blobid'), 'qa_size' => $size], qa_path(''), QA_URL_FORMAT_PARAMS);
		}

		// Fallback default avatar

		// Get the current plugin directory name dynamically using __DIR__
		$pluginDirectory = basename(__DIR__);

		// Construct the plugin URL path dynamically
		$pluginRelativePath = '/qa-plugin/' . $pluginDirectory;

		// Generate the full URL
		return qa_path('') . $pluginRelativePath . '/images/default-avatar-35.png';
	}

	// Get the time since the badge was awarded
	private function get_awarded_time($badgeTime) {
		// Calculate the awarded time (time ago)
		$awardedTimeToString = qa_time_to_string(qa_opt('db_time') - $badgeTime);
		
		// Get the full date when the badge was awarded
		$fullDate = date('Y-m-d', $badgeTime); // Format as "dd/mm/yyyy HH:mm" - date('d/m/Y H:i', $badgeTime) 
		
		// Return both time ago and full date as an array
		return [
			'time_ago' => qa_lang_html_sub('main/x_ago', $awardedTimeToString),
			'full_date' => $fullDate
		];
	}

	// Build the badge HTML for output
	private function build_badge_html($badge, $badgeDetails, $userAvatar, $awardedTime) {
		$badgeHandle = str_replace(' ', '%20', $badge['handle']);
		$imgSrcType = qa_opt('site_theme') == 'Polaris' ? 'data-src="'.$userAvatar.'" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="' : 'src="'.$userAvatar.'"';

		return '
		<div class="badge-awarded">
			<div class="badge-awarded-header">
				<div class="badge-awarded-avatar">
					<a href="'.qa_path('user').'/'.qa_html($badgeHandle).'" class="qa-avatar-link">
						<img class="qa-avatar-image qa-lazy-img" width="35" height="35" '.$imgSrcType.' alt="Awarded Badge User Avatar">
					</a>
				</div>
				<div class="badge-awarded-info">
					<span class="wibawho-wrapper">
						<a class="wibawho" href="'.qa_path('user').'/'.qa_html($badgeHandle).'">'.qa_html($badge['handle']).'</a>
					</span>
					<span class="wibawhat">
						<span class="wibawhat-text">'.qa_lang('badges/widget_badge_earned').'</span>
					</span>
				</div>
				<div class="clear" style="clear:both;"></div>
			</div>
			<div class="badge-awarded-footer">
				<span class="wibawhat-time">
					<span class="wibawhat-timestamp" title="'.qa_html($awardedTime['full_date']).'">'.qa_html($awardedTime['time_ago']).'</span>
				</span>
				<span class="wibabadge">
					<a href="'.qa_path('').'badges#badge-anchor-'.qa_html($badgeDetails['slug']).'" title="'.qa_html($badgeDetails['name']).' - '.qa_html($badgeDetails['desc']).' ('.qa_html($badgeDetails['type']).')">
						<span class="badge-'.qa_html($badgeDetails['slug']).'">'.qa_html($badgeDetails['name']).'</span>
					</a>
				</span>
				<div class="clear" style="clear:both;"></div>
			</div>
		</div>
		';
	}

	// Output the widget header (only the first time)
	private function output_badge_header($themeobject) {
		$themeobject->output('
		<h2>
			<span class="badges-svg">
				<span class="svg-box-container">
					<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="18" height="18" viewBox="0, 0, 400,321.875">
						<g class="svgg">
							<path class="path0" d="M193.862 1.328 C 192.384 2.056,190.115 4.351,188.665 6.583 C 185.632 11.256,109.758 142.835,108.480 145.641 C 107.683 147.391,110.761 153.064,144.004 211.111 C 164.023 246.066,181.281 276.586,182.355 278.934 C 187.466 290.103,188.092 303.089,184.028 313.672 C 182.874 316.680,181.735 319.756,181.498 320.508 C 181.096 321.785,182.360 321.875,200.710 321.875 L 220.353 321.875 219.075 319.404 C 214.497 310.551,212.840 297.370,215.162 288.281 C 217.163 280.448,218.526 277.930,256.535 211.808 C 276.144 177.696,292.187 149.426,292.188 148.984 C 292.188 147.980,211.238 7.403,209.033 4.576 C 205.625 0.209,199.008 -1.208,193.862 1.328 M8.968 42.108 C 5.575 42.956,2.438 45.208,1.132 47.733 C -0.678 51.234,-0.561 308.347,1.252 312.168 C 6.009 322.193,3.941 321.944,80.616 321.697 L 146.484 321.484 150.391 319.406 C 160.216 314.177,165.232 303.103,162.567 292.522 C 161.702 289.086,120.522 216.519,51.985 97.656 C 21.267 44.382,21.516 44.790,18.816 43.393 C 16.005 41.940,11.824 41.395,8.968 42.108 M384.736 42.239 C 379.951 43.627,383.712 37.328,282.993 212.607 C 232.927 299.735,237.913 290.242,237.911 298.438 C 237.909 308.250,241.840 314.856,250.391 319.406 L 254.297 321.484 320.313 321.720 C 396.991 321.993,394.272 322.299,398.532 312.923 C 400.846 307.827,400.786 50.908,398.469 47.108 C 395.980 43.027,389.700 40.800,384.736 42.239 " stroke="none" fill="#000000" fill-rule="evenodd"></path>
						</g>
					</svg>
				</span>
			</span>
			' . qa_lang('badges/badge_widget_title') . '
		</h2>
		');
	}
}


/*
	Omit PHP closing tag to help avoid accidental output
*/
