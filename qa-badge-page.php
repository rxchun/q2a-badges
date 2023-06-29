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
			$qa_content['custom2']='';
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
				
				
				$qa_content['custom'.$c].= $isBronzeLoopClosure . $isBronzeOpen . '<table class="badge-entry-row entry-'.$types.'"><tr id="badge-anchor-'.$slug.'" class="badge-entry-badge"><td><span class="badge-'.$types.'" title="'.$typen.'">'.$name.'</span></td> <td><span class="badge-entry-desc">'.$desc.'</span></td>'.(isset($count[$slug])?' <td><span title="'.$count[$slug]['count'].' '.qa_lang('badges/awarded').'" class="badge-count-link" onclick="jQuery(\'#badge-users-'.$slug.'\').toggleClass(\'q2a-show-badge-source\')">x'.$count[$slug]['count'].'</span></td>':'<td><span class="badge-count">0</span></td>').'</tr>';
				
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

									$users[] = '<a href="'.qa_path_html('user/'.$handle).'">'.$handle.'</a>'.($ucount>1?' x'.$ucount:'');
								}
								$qa_content['custom'.$c] .= '
								<span class="badge-who-received">
									<span class="badges-svg">
										<span class="svg-box-container">
											<svg xmlns="http://www.w3.org/2000/svg" class="svg-box-content" height="48" width="48" viewBox="0 0 50 50"><path d="M11.1 35.25q3.15-2.2 6.25-3.375Q20.45 30.7 24 30.7q3.55 0 6.675 1.175t6.275 3.375q2.2-2.7 3.125-5.45Q41 27.05 41 24q0-7.25-4.875-12.125T24 7q-7.25 0-12.125 4.875T7 24q0 3.05.95 5.8t3.15 5.45ZM24 25.5q-2.9 0-4.875-1.975T17.15 18.65q0-2.9 1.975-4.875T24 11.8q2.9 0 4.875 1.975t1.975 4.875q0 2.9-1.975 4.875T24 25.5ZM24 44q-4.1 0-7.75-1.575-3.65-1.575-6.375-4.3-2.725-2.725-4.3-6.375Q4 28.1 4 24q0-4.15 1.575-7.775t4.3-6.35q2.725-2.725 6.375-4.3Q19.9 4 24 4q4.15 0 7.775 1.575t6.35 4.3q2.725 2.725 4.3 6.35Q44 19.85 44 24q0 4.1-1.575 7.75-1.575 3.65-4.3 6.375-2.725 2.725-6.35 4.3Q28.15 44 24 44Zm0-3q2.75 0 5.375-.8t5.175-2.8q-2.55-1.8-5.2-2.75-2.65-.95-5.35-.95-2.7 0-5.35.95-2.65.95-5.2 2.75 2.55 2 5.175 2.8Q21.25 41 24 41Zm0-18.5q1.7 0 2.775-1.075t1.075-2.775q0-1.7-1.075-2.775T24 14.8q-1.7 0-2.775 1.075T20.15 18.65q0 1.7 1.075 2.775T24 22.5Zm0-3.85Zm0 18.7Z"></path></svg>
										</span>
									</span>
									'. implode(',
								</span>
								<span class="badge-who-received">
									<span class="badges-svg">
										<span class="svg-box-container">
											<svg xmlns="http://www.w3.org/2000/svg" class="svg-box-content" height="48" width="48" viewBox="0 0 50 50"><path d="M11.1 35.25q3.15-2.2 6.25-3.375Q20.45 30.7 24 30.7q3.55 0 6.675 1.175t6.275 3.375q2.2-2.7 3.125-5.45Q41 27.05 41 24q0-7.25-4.875-12.125T24 7q-7.25 0-12.125 4.875T7 24q0 3.05.95 5.8t3.15 5.45ZM24 25.5q-2.9 0-4.875-1.975T17.15 18.65q0-2.9 1.975-4.875T24 11.8q2.9 0 4.875 1.975t1.975 4.875q0 2.9-1.975 4.875T24 25.5ZM24 44q-4.1 0-7.75-1.575-3.65-1.575-6.375-4.3-2.725-2.725-4.3-6.375Q4 28.1 4 24q0-4.15 1.575-7.775t4.3-6.35q2.725-2.725 6.375-4.3Q19.9 4 24 4q4.15 0 7.775 1.575t6.35 4.3q2.725 2.725 4.3 6.35Q44 19.85 44 24q0 4.1-1.575 7.75-1.575 3.65-4.3 6.375-2.725 2.725-6.35 4.3Q28.15 44 24 44Zm0-3q2.75 0 5.375-.8t5.175-2.8q-2.55-1.8-5.2-2.75-2.65-.95-5.35-.95-2.7 0-5.35.95-2.65.95-5.2 2.75 2.55 2 5.175 2.8Q21.25 41 24 41Zm0-18.5q1.7 0 2.775-1.075t1.075-2.775q0-1.7-1.075-2.775T24 14.8q-1.7 0-2.775 1.075T20.15 18.65q0 1.7 1.075 2.775T24 22.5Zm0-3.85Zm0 18.7Z"></path></svg>
										</span>
									</span>
									',$users).'
								</span>
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
