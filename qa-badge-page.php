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
			$qa_content=qa_content_prepare();

			$qa_content['title']=qa_lang('badges/badge_list_title');

			$badges = qa_get_badge_list();
			
			$totalawarded = 0;
			
			$qa_content['custom']='<em>'.qa_lang('badges/badge_list_pre').'</em><br />';
			$qa_content['custom2']='<table class="badge-entry-row badge-entry-row-title"><tr><td>'.qa_lang('badges/badge_name').'</td><td>'.qa_lang('badges/badge_description').'</td><td>'.qa_lang('badges/awarded').'</td></tr></table>';
			$c = 2;
			
			$result = qa_db_read_all_assoc(
				qa_db_query_sub(
					'SELECT user_id,badge_slug  FROM ^userbadges'
				)
			);
			
			$count = array();
			
			foreach($result as $r) {
				if(qa_opt('badge_'.$r['badge_slug'].'_enabled') == '0') continue;
				if(isset($count[$r['badge_slug']][$r['user_id']])) $count[$r['badge_slug']][$r['user_id']]++;
				else $count[$r['badge_slug']][$r['user_id']] = 1;
				$totalawarded++;
				if(isset($count[$r['badge_slug']]['count'])) $count[$r['badge_slug']]['count']++;
				else $count[$r['badge_slug']]['count'] = 1;
			}
			
			$badgeCheckRepeated = '';
			$badgeLoopCount = 0;
			
			foreach($badges as $slug => $info) {
				if(qa_opt('badge_'.$slug.'_enabled') == '0') continue;
				$badge_name = qa_badge_name($slug);
				if(!qa_opt('badge_'.$slug.'_name')) qa_opt('badge_'.$slug.'_name',$badge_name);
				$name = qa_opt('badge_'.$slug.'_name');
				$var = qa_opt('badge_'.$slug.'_var');
				$desc = qa_badge_desc_replace($slug,$var,false);
				$type = qa_get_badge_type($info['type']);
				$types = $type['slug'];
				$typen = $type['name'];
				
				$badgeCheckRepeated = $slug.'-'.$types;
				
				// New Badge Group Divider
				
				$isBronzeLoopClosure = ($types == 'bronze' && $badgeCheckRepeated != '' && (++$badgeLoopCount != 1)) ? '</div>' : '';
				$isBronzeOpen = $types == 'bronze' ? '<div class="badgeGroup">' : '';
				$isBronzeClosure = ($types == 'bronze' && $badgeCheckRepeated == '') ? '<div class="badgeGroup">' : '';
				
				
				$qa_content['custom'.$c].= $isBronzeLoopClosure . $isBronzeOpen . '<table class="badge-entry-row entry-'.$types.'"><tr id="badge-anchor-'.$slug.'" class="badge-entry-badge"><td><span class="badge-'.$types.'" title="'.$typen.'">'.$name.'</span></td> <td><span class="badge-entry-desc">'.$desc.'</span></td>'.(isset($count[$slug])?' <td><span title="'.$count[$slug]['count'].' '.qa_lang('badges/awarded').'" class="badge-count-link noSelect" onclick="jQuery(\'#badge-users-'.$slug.'\').toggleClass(\'q2a-show-badge-source\')">'.$count[$slug]['count'].'x</span></td>':'<td><span class="badge-count">0</span></td>').'</tr>';
				
				// source users

				if(qa_opt('badge_show_source_users') && isset($count[$slug])) {
					
					$users = array();
					
					require_once QA_INCLUDE_DIR.'app/users.php';

					$qa_content['custom'.$c] .= '
					<div id="badge-users-'.$slug.'" class="badge-users badge-container-sources" style="display: none;">
						<div class="badge-wrapper-sources">
							<h3>
								<span class="badge-'.$types.'">'.qa_html($name).'</span><span class="badge-source-title-description">'.$desc.'</span>
							</h3>
							<div class="badge-wrapperToo-sources">';
								foreach($count[$slug] as $uid => $ucount) {
									if($uid == 'count') continue;

									if (QA_FINAL_EXTERNAL_USERS) {
										$handles=qa_get_public_from_userids(array($uid));
										$handle=@$handles[$uid];
									}
									else {
										$useraccount=qa_db_select_with_pending(
											qa_db_user_account_selectspec($uid, true)
										);
										$handle=@$useraccount['handle'];
									}

									if(!$handle) continue;

									$users[] = '<span class="badge-who-received"><a href="'.qa_path_html('user/'.$handle).'">'.$handle.'</a>'.($ucount>1?' '.$ucount.'x':'').'</span>';
								}
								$qa_content['custom'.$c] .= 
								implode('',$users) .'
							</div>
							<div class="close-badge-source-wrapper">
								<div class="badge-close-sbtn" onclick="jQuery(\'#badge-users-'.$slug.'\').removeClass(\'q2a-show-badge-source\')">'.qa_lang('badges/close_badge_source').'</div>
							</div>
						</div>
						<div class="badge-close-source" onclick="jQuery(\'#badge-users-'.$slug.'\').removeClass(\'q2a-show-badge-source\')"></div>
					</div>';
				}
				$qa_content['custom'.$c] .= '</table>' . $isBronzeClosure;
				
			}
			
			$qa_content['custom'.$c] .= '</div>'; // Close qa-part-custom2
			
			$qa_content['custom3'] ='<div class="total-badges"><span>'.count($badges).' '.qa_lang('badges/badges_total').'</span>'.($totalawarded > 0 ? ', <span class="total-badge-count">'.round($totalawarded).' '.qa_lang('badges/awarded_total').'</span>':'').'</div>';
			
			// Deprecated JS Badges Group
			
			/*
			$qa_content['custom'.++$c]='
			<script>
			// Groups Badges by "category"
			jQuery(\'.entry-bronze\').each(function (index) {
				if(jQuery(this).parent().next().find(\'.entry-silver\').length !== 0 && jQuery(this).parent().next().next().find(\'.entry-gold\').length !== 0){
					jQuery(this).parent().nextUntil().addBack().slice(0, 3).wrapAll(\'<div class="badgeGroup"></div>\');
				}
				else if(jQuery(this).parent().next().find(\'.entry-silver\').length !== 0){
					jQuery(this).parent().nextUntil().addBack().slice(0, 2).wrapAll(\'<div class="badgeGroup"></div>\');
				}
				else {
					jQuery(this).parent().wrapAll(\'<div class="badgeGroup"></div>\');
				}
			});
			</script>';
			*/
			
			if(isset($qa_content['navigation']['main']['custom-2'])) $qa_content['navigation']['main']['custom-2']['selected'] = true;

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
	
	};
	

/*
	Omit PHP closing tag to help avoid accidental output
*/
