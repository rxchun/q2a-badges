<?php

class qa_badge_page {
	
	var $directory;
	var $urltoroot;
	
	function load_module($directory, $urltoroot)
	{
		$this->directory=$directory;
		$this->urltoroot=$urltoroot;
	}
	
	function suggest_requests() // for display in admin interface
	{	
		return array(
			array(
				'title' => qa_lang('badges/badges'),
				'request' => 'badges',
				'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
			),
		);
	}
	
	function match_request($request)
	{
		if ($request=='badges')
			return true;

		return false;
	}

	function process_request($request)
	{
		// Include required utility functions
		require_once $this->directory . 'badges-endpoint/badge-utils.php';

		// Prepare the main content array
		$qa_content = qa_content_prepare();

		// Set the page title
		$qa_content['title'] = qa_lang('badges/badge_list_title');

		// Get the list of all badges
		$badges = qa_get_badge_list();

		// Initialize total awarded count
		$totalAwarded = 0;

		// Get counts of badges awarded to users
		$badgeAwardCounts = qa_get_badge_award_counts();
		
		// Sum total badges awarded
		foreach ($badgeAwardCounts as $slug => $users) {
			if (isset($users['count'])) {
				$totalAwarded += $users['count'];
			}
		}
		
		// Descirption of Badges list
		$qa_content['custom'] = 
			'<p class="total-badges">'.
				'<span><strong>' . count($badges) . '</strong> ' . qa_lang('badges/badges_total') . '</span>'.
				($totalAwarded > 0 ? ', <span class="total-badge-count"><strong>' . round($totalAwarded) . '</strong> ' . qa_lang('badges/awarded_total') . '</span>' : '').
			'</p>'.
			'<em>'.qa_lang('badges/badge_list_pre').'</em>';
		
		// Table header
		$qa_content['custom2'] = '
		<table class="badge-entry-row badge-entry-row-title">
			<tr>
				<td>' . qa_lang('badges/badge_name') . '</td>
				<td>' . qa_lang('badges/badge_description') . '</td>
				<td>' . qa_lang('badges/awarded') . '</td>
			</tr>
		</table>';

		// Starting index for custom content blocks
		$contentIndex = 2;

		// Variables to handle badge grouping display logic
		$previousBadgeGroup = '';
		$badgeGroupCount = 0;

		// Loop through each badge to build the list
		foreach ($badges as $slug => $info) {
			// Skip badges that are disabled
			if (qa_opt('badge_' . $slug . '_enabled') == '0') continue;

			// Get badge name and description
			$badgeName = qa_badge_name($slug);
			if (!qa_opt('badge_' . $slug . '_name')) {
				qa_opt('badge_' . $slug . '_name', $badgeName);
			}
			$name = qa_opt('badge_' . $slug . '_name');
			$var = qa_opt('badge_' . $slug . '_var');
			$desc = qa_badge_desc_replace($slug, $var, false);

			// Get badge type info (e.g. bronze, silver, gold)
			$typeInfo = qa_get_badge_type($info['type']);
			$typeSlug = $typeInfo['slug'];
			$typeName = $typeInfo['name'];

			// Track current badge group to manage div wrappers
			$previousBadgeGroup = $slug . '-' . $typeSlug;

			// Manage badge group divs
			$closePrevGroup = ($typeSlug == 'bronze' && $previousBadgeGroup != '' && (++$badgeGroupCount != 1)) ? '</div>' : '';
			$openBadgeGroup = $typeSlug == 'bronze' ? '<div class="badgeGroup">' : '';
			$isBronzeClosure = ($typeSlug == 'bronze' && $previousBadgeGroup == '') ? '<div class="badgeGroup">' : '';

			// Build the badge row HTML
			$qa_content['custom' . $contentIndex] .=
				$closePrevGroup .
				$openBadgeGroup .
				'<table class="badge-entry-row entry-' . $typeSlug . '">
					<tr id="badge-anchor-' . $slug . '" class="badge-entry-badge">
						<td><span class="badge-' . $typeSlug . '" title="' . $typeName . '">' . $name . '</span></td>
						<td><span class="badge-entry-desc">' . $desc . '</span></td>';

					// Badge awarded count with clickable user list if available
					if (isset($badgeAwardCounts[$slug])) {
						
						$countForBadge = $badgeAwardCounts[$slug]['count'];
						$fetchUrl = qa_path('qa-plugin/' . basename(__DIR__));
						
						// If showing source is not enabled
						$dataAttributes = 'class="badge-count"';
						
						if (qa_opt('badge_show_source_users') && isset($badgeAwardCounts[$slug])) {
							$dataAttributes = '
								class="badge-count-link noSelect" 
								data-slug="'.qa_html($slug).'" 
								data-type-slug="'.qa_html($typeSlug).'" 
								data-name="'.qa_html($name).'" 
								data-popup-title="'.qa_lang('badges/badge_widget_title').'"
								data-desc="'.htmlspecialchars($desc, ENT_QUOTES).'" 
								data-fetch-url="'.$fetchUrl.'"
							';
						}
						
						$qa_content['custom' . $contentIndex] .=
							'<td>
								<span title="'.$countForBadge.' '.qa_lang('badges/awarded').'" '.$dataAttributes.'>
									'.$countForBadge.'x
								</span>
							</td>';
					} else {
						$qa_content['custom' . $contentIndex] .= 
							'<td><span class="badge-count" title="0 '.qa_lang('badges/awarded').'" >0</span></td>';
					}

					$qa_content['custom' . $contentIndex] .= '</tr>';

			$qa_content['custom' . $contentIndex] .= '</table>' . $isBronzeClosure;

			// Update previous badge group for next iteration
			$previousBadgeGroup = $previousBadgeGroup;
		}

		// Optionally mark navigation tab selected if applicable
		if (isset($qa_content['navigation']['main']['custom-2'])) {
			$qa_content['navigation']['main']['custom-2']['selected'] = true;
		}

		return $qa_content;
	}
	
	function getuserfromhandle($handle) {
		require_once QA_INCLUDE_DIR.'app/users.php';
		
		if (QA_FINAL_EXTERNAL_USERS) {
			$publictouserid=qa_get_userids_from_public(array($handle));
			$userid=@$publictouserid[$handle];
			
		} 
		else {
			$userid = qa_db_read_one_value(
				qa_db_query_sub(
					'SELECT userid FROM ^users WHERE handle = $',
					$handle
				),
				true
			);
		}
		if (!isset($userid)) return;
		return $userid;
	}

}


/*
Omit PHP closing tag to help avoid accidental output
*/
