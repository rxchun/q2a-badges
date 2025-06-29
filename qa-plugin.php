<?php

// Don't allow this page to be requested directly from browser
if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

qa_register_plugin_module('event', 'qa-badge-check.php','badge_check','Badge Check');
qa_register_plugin_module('module', 'qa-badge-admin.php', 'qa_badge_admin', 'Badge Admin');
qa_register_plugin_module('page', 'qa-badge-page.php', 'qa_badge_page', 'Badges');
qa_register_plugin_module('widget', 'qa-badge-widget.php', 'qa_badge_widget', 'Recent Badge Widget');
qa_register_plugin_layer('qa-badge-layer.php', 'Badge Notification Layer');	
qa_register_plugin_phrases('lang/qa-badge-lang-*.php', 'badges');

function qa_badge_lang($string) {
	return qa_lang($string);
}

function qa_get_badge_list() {
	
	// badges - add to this list to add a new badge, it will be imported when you run this function.  Don't change existing slugs!
	
	$badges = array();

	if(!QA_FINAL_EXTERNAL_USERS) {
		$badges['verified'] = array('type'=>0);
		$badges['profiler'] = array('type'=>0);
		$badges['avatar'] = array('type'=>0);
	}

	$badges['nice_question'] = array('var'=>2, 'type'=>0);
	$badges['good_question'] = array('var'=>5, 'type'=>1);
	$badges['great_question'] = array('var'=>10, 'type'=>2);

	$badges['notable_question'] = array('var'=>50, 'type'=>0);
	$badges['popular_question'] = array('var'=>100, 'type'=>1);
	$badges['famous_question'] = array('var'=>500, 'type'=>2);

	$badges['nice_answer'] = array('var'=>2, 'type'=>0);
	$badges['good_answer'] = array('var'=>5, 'type'=>1);
	$badges['great_answer'] = array('var'=>10, 'type'=>2);

	$badges['nice_answer_old'] = array('var'=>30, 'type'=>0);
	$badges['good_answer_old'] = array('var'=>60, 'type'=>1);
	$badges['great_answer_old'] = array('var'=>120, 'type'=>2);

	$badges['gifted'] = array('var'=>1, 'type'=>0);
	$badges['wise'] = array('var'=>10, 'type'=>1);
	$badges['enlightened'] = array('var'=>30, 'type'=>2);

	$badges['grateful'] = array('var'=>1, 'type'=>0);
	$badges['respectful'] = array('var'=>20, 'type'=>1);
	$badges['reverential'] = array('var'=>50, 'type'=>2);

	$badges['liked'] = array('var'=>20, 'type'=>0);
	$badges['loved'] = array('var'=>50, 'type'=>1);
	$badges['revered'] = array('var'=>200, 'type'=>2);


	$badges['asker'] = array('var'=>10, 'type'=>0);
	$badges['questioner'] = array('var'=>25, 'type'=>1);
	$badges['inquisitor'] = array('var'=>50, 'type'=>2);		

	$badges['answerer'] = array('var'=>25, 'type'=>0);
	$badges['lecturer'] = array('var'=>50, 'type'=>1);
	$badges['preacher'] = array('var'=>100, 'type'=>2);

	$badges['commenter'] = array('var'=>50, 'type'=>0);
	$badges['commentator'] = array('var'=>100, 'type'=>1);
	$badges['annotator'] = array('var'=>500, 'type'=>2);


	$badges['voter'] = array('var'=>10, 'type'=>0);
	$badges['avid_voter'] = array('var'=>50, 'type'=>1);
	$badges['devoted_voter'] = array('var'=>200, 'type'=>2);

	$badges['editor'] = array('var'=>1, 'type'=>0);
	$badges['copy_editor'] = array('var'=>15, 'type'=>1);
	$badges['senior_editor'] = array('var'=>50, 'type'=>2);

	$badges['watchdog'] = array('var'=>1, 'type'=>0);
	$badges['bloodhound'] = array('var'=>10, 'type'=>1);
	$badges['pitbull'] = array('var'=>30, 'type'=>2);


	$badges['reader'] = array('var'=>20, 'type'=>0);
	$badges['avid_reader'] = array('var'=>50, 'type'=>1);
	$badges['devoted_reader'] = array('var'=>200, 'type'=>2);


	$badges['dedicated'] = array('var'=>10, 'type'=>0);
	$badges['devoted'] = array('var'=>25, 'type'=>1);
	$badges['zealous'] = array('var'=>50, 'type'=>2);

	$badges['visitor'] = array('var'=>30, 'type'=>0);
	$badges['trouper'] = array('var'=>100, 'type'=>1);
	$badges['veteran'] = array('var'=>200, 'type'=>2);

	$badges['regular'] = array('var'=>90, 'type'=>0);
	$badges['old_timer'] = array('var'=>180, 'type'=>1);
	$badges['ancestor'] = array('var'=>365, 'type'=>2);


	$badges['100_club'] = array('var'=>100, 'type'=>0);
	$badges['1000_club'] = array('var'=>1000, 'type'=>1);
	$badges['10000_club'] = array('var'=>10000, 'type'=>2);

	$badges['medalist'] = array('var'=>10, 'type'=>0);
	$badges['champion'] = array('var'=>30, 'type'=>1);
	$badges['olympian'] = array('var'=>100, 'type'=>2);

	// get badges from other plugins - experimental!

	$moduletypes=qa_list_module_types();
	foreach ($moduletypes as $moduletype) {
		$modulenames=qa_list_modules($moduletype);
		
		foreach ($modulenames as $modulename) {
			$module=qa_load_module($moduletype, $modulename);
			
			if (method_exists($module, 'custom_badges'))
				$badges=array_merge($badges,$module->custom_badges());
		}
	}

	return $badges;
}


function qa_get_badges_by_type() {
	$bin = qa_get_badge_list();
	foreach($bin as $slug => $info) {
		$bout[$info['type']][] = array(
			'slug'=>$slug,
			'var'=>@$info['var']
		);
	}
	return $bout;
}

function qa_get_badge_type_by_slug($slug) {
	$badges = qa_get_badge_list();
	return qa_get_badge_type(@$badges[$slug]['type']);
}
function qa_get_badge_type($id) {
	
	// badge categories, e.g. bronze, silver, gold
	$badge_types = array();
	
	$badge_types[] = array('slug'=>'bronze','name'=>qa_lang('badges/bronze'));
	$badge_types[] = array('slug'=>'silver','name'=>qa_lang('badges/silver'));
	$badge_types[] = array('slug'=>'gold','name'=>qa_lang('badges/gold'));
	
	$id = (int)$id;
	
	return $badge_types[$id];
}

function qa_badge_award_check($badges, $var, $uid, $oid = NULL, $notify = 1) {  // oid is the postid (if), notify = 1 for email and popup, 2 for just popup.
	if(!$uid) return;
	$awarded = array();
	foreach($badges as $badge_slug) {
		
		if(($var === false || (int)$var >= (int)qa_opt('badge_'.$badge_slug.'_var')) && qa_opt('badge_'.$badge_slug.'_enabled') !== '0') {
			if($oid) {
				$result = @qa_db_read_one_value(
					qa_db_query_sub(
						'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$ AND object_id=#',
						$uid, $badge_slug, $oid
					),
					true
				);
			}
			else {
				$result = @qa_db_read_one_value(
					qa_db_query_sub(
						'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND badge_slug=$',
						$uid, $badge_slug
					),
					true
				);				
			}
			
			if ($result == null) { // not already awarded this badge
				qa_db_query_sub(
					'INSERT INTO ^userbadges (awarded_at, notify, object_id, user_id, badge_slug, id) '.
					'VALUES (NOW(), #, #, #, #, 0)',
					$notify, $oid, $uid, $badge_slug
				);
				
				if($notify > 0) {
					
					//qa_db_usernotice_create($uid, $content, 'html');
					if(qa_opt('badge_email_notify') && $notify == 1) qa_badge_notification($uid, $oid, $badge_slug);
					// raise an event
					$event_params = array('badge_slug' => $badge_slug);
					if ($oid) {
						$event_params['postid'] = $oid;
					}
					
					qa_report_event('badge_awarded', $uid, qa_getHandleFromId($uid), qa_cookie_get(), $event_params);
				}
				
				array_push($awarded,$badge_slug);
			}
		}
	}
	return $awarded;
}

function qa_badge_notification($uid, $oid, $badge_slug) {

	if(!qa_opt('badge_email_notify_id_'.$uid))
		return;
	
	require_once QA_INCLUDE_DIR.'app/users.php';
	require_once QA_INCLUDE_DIR.'app/emails.php';
	
	if (QA_FINAL_EXTERNAL_USERS) {
		$publictohandle=qa_get_public_from_userids(array($uid));
		$handle=@$publictohandle[$uid];
		
	} 
	else {
		$user = qa_db_single_select(qa_db_user_account_selectspec($uid, true));
		$handle = @$user['handle'];
	}

	$subject = qa_opt('badge_email_subject');
	$body = qa_opt('badge_email_body');

	$body = preg_replace('/\^if_post_text="([^"]*)"/',($oid?'$1':''),$body); // if post text
	
	$site_url = qa_path('');
	$profile_url = qa_path_html('user/'.$handle, null, $site_url);
	


	if($oid) {
		$post = qa_db_read_one_assoc(
			qa_db_query_sub(
				'SELECT * FROM ^posts WHERE postid=#',
				$oid
			),
			true
		);
		if($post['parentid']) 
			$parent = qa_db_read_one_assoc(
				qa_db_query_sub(
					'SELECT * FROM ^posts WHERE postid=#',
					$post['parentid']
				),
				true
			);
		if(isset($parent) && $parent['basetype'] == 'A') {
			$parent = qa_db_read_one_assoc(
				qa_db_query_sub(
					'SELECT * FROM ^posts WHERE postid=#',
					$parent['parentid']
				),
				true
			);
		}
		if(isset($parent)) {
			$anchor = urlencode(qa_anchor($post['basetype'], $oid));

			$post_title = $parent['title'];
			$post_url = qa_path_html(qa_q_request($parent['postid'], $parent['title']), null, qa_path(''),null, $anchor);
		}
		else {
			$post_title = $post['title'];
			$post_url = qa_path_html(qa_q_request($post['postid'], $post['title']), null, qa_path(''));
		}

	}
	
	$subs = array(
		'^badge_name'=> qa_opt('badge_'.$badge_slug.'_name'),
		'^post_title'=> @$post_title,
		'^post_url'=> @$post_url,
		'^profile_url'=> $profile_url,
		'^site_url'=> $site_url,
	);
	
	qa_send_notification($uid, '@', $handle, $subject, $body, $subs);
}

function qa_badge_name($slug, $reset=false) {
	if($reset)
		$name = qa_lang('badges/'.$slug);
	else
		$name = qa_opt('badge_'.$slug.'_name')?qa_opt('badge_'.$slug.'_name'):qa_lang('badges/'.$slug);
	
	// plugins
	
	if($name == '[badges/'.$slug.']') {
		global $qa_lang_file_pattern;
		foreach($qa_lang_file_pattern as $name => $files) {
			$lang = qa_lang($name.'/badge_'.$slug);
			if($lang != '['.$name.'/badge_'.$slug.']') {
				return $lang;
			}
		}
		return $slug;
	}
	return $name;
}


function qa_badge_desc_replace($slug, $var=null, $admin=false) {
	$desc = qa_opt('badge_'.$slug.'_desc')?qa_opt('badge_'.$slug.'_desc'):qa_lang('badges/'.$slug.'_desc');
	
	// plugins
	
	if($desc == '[badges/'.$slug.'_desc]') {
		global $qa_lang_file_pattern;
		foreach($qa_lang_file_pattern as $name => $files) {
			$lang = qa_lang($name.'/badge_'.$slug.'_desc');
			if($lang != '['.$name.'/badge_'.$slug.'_desc]') {
				$desc = $lang;
				break;
			}
		}
	}

	// var replace
	
	if($var) {
		$desc = $admin?str_replace('#','<input type="text" name="badge_'.$slug.'_var" size="4" value="'.$var.'">',$desc):str_replace('#',format_number_with_commas($var),$desc);
		$desc = preg_replace('/\^([^^]+)\^(\S+)/',($var == 1?"$1":"$2"),$desc);
	}
	
	// other badge reference replace
	
	preg_match_all('|\$(\S+)|',$desc,$others,PREG_SET_ORDER);
	
	if(!$others) return $desc;
	
	foreach($others as $other) {
		if(!qa_opt('badge_'.$other[1].'_name')) qa_opt('badge_'.$other[1].'_name',qa_lang('badges/'.$other[1]));
		$name = qa_opt('badge_'.$other[1].'_name');

		$desc = str_replace($other[0],$name,$desc);
	}
	return $desc;
}

/**
 * Formats a numeric string with commas (e.g., "1000" -> "1,000").
 *
 * @param string $string The string to format.
 * @return string The formatted string with commas, or the original string if not numeric.
 */
function format_number_with_commas($string) {
	// Check if the string is numeric
	if (is_numeric($string)) {
		// Convert string to number and apply number_format
		return number_format((int)$string);
	}
	// Return original string if not a number
	return $string;
}

if(!function_exists('qa_getHandleFromId')) {
	
	function qa_getHandleFromId($userid) {
		require_once QA_INCLUDE_DIR.'app/users.php';
		
		if (QA_FINAL_EXTERNAL_USERS) {
			$publictohandle=qa_get_public_from_userids(array($userid));
			$handle=@$publictohandle[$userid];
			
		} 
		else {
			$user = qa_db_single_select(qa_db_user_account_selectspec($userid, true));
			$handle = @$user['handle'];
		}
		return $handle;
	}
}


// worker functions

// layout
	
function qa_badge_plugin_user_widget($handle) {
	
	$userids = qa_handles_to_userids(array($handle));
	$userid = $userids[$handle];

	// displays small badge widget, suitable for meta
	
	$result = qa_db_read_all_values(
		qa_db_query_sub(
			'SELECT badge_slug FROM ^userbadges WHERE user_id=#',
			$userid
		)
	);

	if(count($result) == 0) return;
	
	$badges = qa_get_badge_list();
	foreach($result as $slug) {
		$bcount[$badges[$slug]['type']] = isset($bcount[$badges[$slug]['type']])?$bcount[$badges[$slug]['type']]+1:1; 
	}
	$output='<span class="badge-medals-widget">';
	for($x = 2; $x >= 0; $x--) {
		if(!isset($bcount[$x])) continue;
		$count = $bcount[$x];
		if($count == 0) continue;

		$type = qa_get_badge_type($x);
		$types = $type['slug'];
		$typed = $type['name'];

		$output.='<span class="badge-pointer badge-pointer-'.$typed.'" title="'.$count.' '.$typed.'"><span class="badge-'.$types.'-count">'.$count.'</span></span> ';
	}
	$output = substr($output,0,-1);  // lazy remove space
	$output.='</span>';
	return($output);
}

function qa_badge_plugin_user_form($userid) {

	$handles = qa_userids_to_handles(array($userid));
	$handle = $handles[$userid];
	
	// displays badge list in user profile

	$result = qa_db_read_all_assoc(
		qa_db_query_sub(
			'SELECT badge_slug as slug, object_id AS oid FROM ^userbadges WHERE user_id=#',
			$userid
		)
	);
	
	$fields = array();
	
	if(count($result) > 0) {
		
		// count badges
		$bin = qa_get_badge_list();
		
		$badges = array();
		
		foreach($result as $info) {
			$slug = $info['slug'];
			$type = $bin[$slug]['type'];
			if(isset($badges[$type][$slug])) $badges[$type][$slug]['count']++;
			else $badges[$type][$slug]['count'] = 1;
			if($info['oid']) $badges[$type][$slug]['oid'][] = $info['oid'];
		}
		
		foreach ($badges as $type => $badge) {

			$typea = qa_get_badge_type($type);
			$types = $typea['slug'];
			$typed = $typea['name'];

			$output = '
				<table>
					<tr>
						<td class="badge-title-wrapper qa-form-wide-label">
							<h3 class="badge-title badge-title-' . qa_html($typed) . '">
								<span class="badge-title-pointer" title="' . qa_lang('badges/' . $types . '_desc') . '">' . qa_html($typed) . '</span> 
								<span id="hmb-' . qa_html($typed) . '" class="hmb-perclass" title="' . count($badge) . ' ' . qa_lang('badges/badge_of_type') . '">(' . count($badge) . ')</span>
							</h3>
						</td>
					</tr>';

			foreach ($badge as $slug => $info) {

				$badge_name = qa_badge_name($slug);
				if (!qa_opt('badge_' . $slug . '_name')) qa_opt('badge_' . $slug . '_name', $badge_name);
				$name = qa_opt('badge_' . $slug . '_name');

				$count = qa_html((int)$info['count']);

				$oids = (qa_opt('badge_show_source_posts') && is_array(@$info['oid'])) ? $info['oid'] : null;

				$var = qa_opt('badge_' . $slug . '_var');
				$desc = qa_badge_desc_replace($slug, $var, false);
				
				$fetchUrl = qa_path('qa-plugin/' . basename(__DIR__));
				
				$dataAttributes = 'class="badge-count"';
					
				if ($oids) {
					$dataAttributes = '
						class="badge-count-link noSelect"
						data-slug="'.qa_html($slug).'"
						data-type-slug="'.qa_html($types).'"
						data-name="'.qa_html($name).'"
						data-desc="'.htmlspecialchars($desc, ENT_QUOTES).'"
						data-fetch-url="'.qa_html($fetchUrl).'"
						data-userid="'.qa_html((int)$userid).'"
					';
				}
				
				$output .= '
					<tr>
						<td class="badge-container">
							<table class="badge-entry-row entry-' . qa_html($types) . '">
								<tbody>
									<tr id="badge-anchor-' . qa_html($slug) . '">
										<td>
											<span class="badge-' . qa_html($types) . '" title="' . htmlspecialchars($desc, ENT_QUOTES) . '">' . qa_html($name) . '</span>
										</td>
										<td>
											<span title="'.$count.' '.qa_lang('badges/awarded').'" '.$dataAttributes.'>
												'.$count.'x
											</span>
										</td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>';
			}

			$output .= '
				</table>';

			$outa[] = $output;
		}

		$fields[] = array(
				'value' => '<table class="badge-user-tables"><tr><td class="badge-user-table">'.implode('</td><td class="badge-user-table">',$outa).'</td></tr></table>',
				'type' => 'static',
		);
	}

	$ok = null;
	$tags = null;
	$buttons = array();
	
	if((bool)qa_opt('badge_email_notify') && qa_get_logged_in_handle() == $handle) {

		// add badge notify checkbox
		if(qa_clicked('badge_email_notify_save')) {
			qa_opt('badge_email_notify_id_'.$userid, (bool)qa_post_text('badge_notify_email_me'));
			$ok = qa_lang('badges/badge_notified_email_me');
		}

		$select = (bool)qa_opt('badge_email_notify_id_'.$userid);
		
		$tags = 'id="badge-form" action="'.qa_self_html().'#signature_text" method="POST"';
		
		$fields[] = array(
			'type' => 'blank',
		);
		
		$fields[] = array(
			'label' => qa_lang('badges/badge_notify_email_me'),
			'type' => 'checkbox',
			'tags' => 'NAME="badge_notify_email_me"',
			'value' => $select,
		);
							
		$buttons[] = array(
			'label' => qa_lang_html('main/save_button'),
			'tags' => 'NAME="badge_email_notify_save"',
		);
	}

	return array(				
		'ok' => ($ok && !isset($error)) ? $ok : null,
		'style' => 'tall',
		'tags' => $tags,
		'title' => qa_lang('badges/badges'),
		'fields'=>$fields,
		'buttons'=>$buttons,
	);
	
}


/*
	Omit PHP closing tag to help avoid accidental output
*/
