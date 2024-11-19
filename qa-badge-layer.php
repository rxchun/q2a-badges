<?php

	class qa_html_theme_layer extends qa_html_theme_base {

	// Patch Version
	private $patchNumber = '?v=59';
	
	// init before start
	public $badge_notice;
	
		function doctype() {
				
			qa_html_theme_base::doctype();
			if (qa_opt('badge_active')) {
				
				// tabs

				if($this->template == 'user' && !qa_opt('badge_admin_user_field_no_tab')) {
					if(!isset($this->content['navigation']['sub'])) {
						$this->content['navigation']['sub'] = array(
							'profile' => array(
								'url' => qa_path_html('user/'.$this->_user_handle(), null, qa_path('')),
								'label' => $this->_user_handle(),
								'selected' => !qa_get('tab')?true:false
							),
							'badges' => array(
								'url' => qa_path_html('user/'.$this->_user_handle(), array('tab'=>'badges'), qa_path('')),
								'label' => qa_lang('badges/badges'),
								'selected' => qa_get('tab')=='badges'?true:false
							),
						);
					}
					else {
						$this->content['navigation']['sub']['badges'] = array(
							'url' => qa_path_html('user/'.$this->_user_handle(), array('tab'=>'badges'), qa_path('')),
							'label' => qa_lang('badges/badges'),
							'selected' => qa_get('tab')=='badges'?true:false
						);
					}
				}
				
				require_once QA_INCLUDE_DIR.'app/users.php';

				$userid = qa_get_logged_in_userid();

				if(!$userid) return; // not logged in?  die.
				
				// first visit check
				
				$user = @qa_db_read_one_assoc(
					qa_db_query_sub(
						'SELECT ^achievements.user_id AS uid,^achievements.oldest_consec_visit AS ocv,^achievements.longest_consec_visit AS lcv,^achievements.total_days_visited AS tdv,^achievements.last_visit AS lv,^achievements.first_visit AS fv, ^userpoints.points as points FROM ^achievements, ^userpoints WHERE ^achievements.user_id=# AND ^userpoints.userid=#',
						$userid,$userid
					),
					true
				);
				
				// if(!$user['uid'])
				if(empty($user)) {
					qa_db_query_sub(
						'INSERT INTO ^achievements (user_id, first_visit, oldest_consec_visit, longest_consec_visit, last_visit, total_days_visited, questions_read, posts_edited) VALUES (#, NOW(), NOW(), #, NOW(), #, #, #) ON DUPLICATE KEY UPDATE first_visit=NOW(), oldest_consec_visit=NOW(), longest_consec_visit=#, last_visit=NOW(), total_days_visited=#, questions_read=#, posts_edited=#',
						$userid, 1, 1, 0, 0, 1, 1, 0, 0
					);
					return;
				}

				// check lapse in days since last visit
				// using julian days
				
				$todayj = GregorianToJD(date('n'),date('j'),date('Y'));
				
				$last_visit = strtotime($user['lv']);
				$lastj = GregorianToJD(date('n',$last_visit),date('j',$last_visit),date('Y',$last_visit));
				$last_diff = $todayj-$lastj;
				
				$oldest_consec = strtotime($user['ocv']);
				$oldest_consecj = GregorianToJD(date('n',$oldest_consec),date('j',$oldest_consec),date('Y',$oldest_consec));
				$oldest_consec_diff = $todayj-$oldest_consecj+1; // include the first day
				
				$first_visit = strtotime($user['fv']);
				$first_visitj = GregorianToJD(date('n',$first_visit),date('j',$first_visit),date('Y',$first_visit));
				$first_visit_diff = $todayj-$first_visitj;
				
				if($last_diff < 0) return; // error
				
				if($last_diff < 2) { // one day or less, update last visit
					
					if($oldest_consec_diff > $user['lcv']) {
						$user['lcv'] = $oldest_consec_diff;
						qa_db_query_sub(
							'UPDATE ^achievements SET last_visit=NOW(), longest_consec_visit=#, total_days_visited=total_days_visited+#  WHERE user_id=#',
							$oldest_consec_diff, $last_diff, $userid 
						);		
					}
					else {
						qa_db_query_sub(
							'UPDATE ^achievements SET last_visit=NOW(), total_days_visited=total_days_visited+# WHERE user_id=#',
							$last_diff,$userid 
						);		
					}
					$badges = array('dedicated','devoted','zealous');
					qa_badge_award_check($badges, $user['lcv'], $userid,null,2);
				}
				else { // 2+ days, reset consecutive days due to lapse
					qa_db_query_sub(
						'UPDATE ^achievements SET last_visit=NOW(), oldest_consec_visit=NOW(), total_days_visited=total_days_visited+1 WHERE user_id=#',
						$userid
					);		
				}

				$badges = array('visitor','trouper','veteran');
				qa_badge_award_check($badges, $user['tdv'], $userid,null,2);
				
				$badges = array('regular','old_timer','ancestor');
				qa_badge_award_check($badges, $first_visit_diff, $userid,null,2);
				
				// check points
				if(isset($user['points'])) {
					$badges = array('100_club','1000_club','10000_club');
					qa_badge_award_check($badges, $user['points'], $userid,null,2);	
				}
			}
		}
		
	// theme replacement functions
	
		function head_custom() {
			qa_html_theme_base::head_custom();
			$patchNumber = $this->patchNumber;
			
			if(!qa_opt('badge_active'))
				return;
			
			// only load Styles if enabled
			if (qa_opt('badge_active')) {
				$this->output('
					<link rel="preload" as="style" href="'.QA_HTML_THEME_LAYER_URLTOROOT.'css/badges-styles.min.css'.$patchNumber.'" onload="this.onload=null;this.rel=\'stylesheet\'">
					<noscript><link rel="stylesheet" href="'.QA_HTML_THEME_LAYER_URLTOROOT.'css/badges-styles.min.css'.$patchNumber.'"></noscript>
				');
			}
			// add RTL CSS file
			if ($this->isRTL) {
				$this->output('
					<link rel="preload" as="style" href="'.QA_HTML_THEME_LAYER_URLTOROOT.'css/badges-rtl-style.css'.$patchNumber.'" onload="this.onload=null;this.rel=\'stylesheet\'">
					<noscript><link rel="stylesheet" href="'.QA_HTML_THEME_LAYER_URLTOROOT.'css/badges-rtl-style.css'.$patchNumber.'"></noscript>
				');
			}
			
			if (qa_opt('badge_active') && $this->template != 'admin') {
				$this->badge_notify();
			}

			// Added fix to remove empty <style> in <head>
			if (qa_opt('badges_css') != null) {
				$this->output('<style>',qa_opt('badges_css'),'</style>');
			}
		}
		
		public function footer_scripts()
		{
			qa_html_theme_base::footer_scripts();
			
			if (qa_opt('badge_active') && $this->template != 'admin') {
				$this->output("
				<script>
					// Q2A Badges
					jQuery(document).ready(() => {
						// Handle document Z indexes for dark pane
						jQuery(document).on('click', '.badge-count-link', function () {
							jQuery('.leftPanel, .qa-header').addClass('zindex1');
						});
						
						jQuery(document).on('click', '.badge-close-source, .badge-close-sbtn', function () {
							jQuery('.leftPanel, .qa-header').removeClass('zindex1');
						});
					});
					
					// Add Pop Up positioning for the triggered Badge Source Link
					$(document).on('click', '.badge-count-link', (e) => {
						if ($(window).width() <= 575) {
							const closestBadgeContainer = $(e.currentTarget).parents('.badge-container').find('.badge-container-sources .badge-wrapper-sources');
							const badgeTopDistance = ($(e.currentTarget).offset().top - closestBadgeContainer.height()) - (closestBadgeContainer.height() / 6);
							
							$(closestBadgeContainer).css({
								top: badgeTopDistance
							});
						} else {
							$('.badge-wrapper-sources').css('top', '');
						}
					});
					
				</script>
				");
			}
			
			// If user page - add scroll to section
			if (qa_opt('badge_active') && $this->template == 'user') {
				$this->output("
				<script>
					// Q2A Badges
					jQuery(document).ready(() => {
						// Badges plugin Scroll DOM on earned badge
						if (window.location.href.indexOf('badges') > -1) {
							$([document.documentElement, document.body]).animate({
								scrollTop: $('body.qa-template-user div.qa-part-form-badges-list').offset().top
							}, 500);
						}
					});
				</script>
				");
			}
			
			if ($this->request == 'admin/plugins' && qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN) {
				$this->output("
				<script>
					// Q2A Badges
					".(qa_opt('badge_notify_time') != '0'?"
					jQuery(document).ready(() => { jQuery('.notify-container').delay(".((int)qa_opt('badge_notify_time')*1000).").fadeOut('fast'); });":"")."
					function badgeEdit(slug,end) {
						if(end) {
							jQuery('#badge_'+slug+'_edit').hide();
							jQuery('#badge_'+slug+'_badge').show();
							jQuery('#badge_'+slug+'_badge').html(jQuery('#badge_'+slug+'_edit').val());
							return;
						}
						jQuery('#badge_'+slug+'_badge').hide();
						jQuery('#badge_'+slug+'_edit').show();
						jQuery('#badge_'+slug+'_edit').focus();
					}
				</script>");
			} else if (isset($this->badge_notice)) {
				$this->output("
				<script>
					// Q2A Badges
					".(qa_opt('badge_notify_time') != '0'?"
					jQuery(document).ready(() => { jQuery('.notify-container').delay(".((int)qa_opt('badge_notify_time')*1000).").fadeOut('fast'); });":"")."
				</script>");
			}
			
		}
		
		function body_prefix()
		{
			qa_html_theme_base::body_prefix();
			if(isset($this->badge_notice))
				$this->output($this->badge_notice);
		}

		function body_suffix()
		{
			qa_html_theme_base::body_suffix();
			
			if (qa_opt('badge_active')) {
				if(isset($this->content['test-notify'])) {
					$this->trigger_notify('Badge Tester');
				}
				
			}
		}

		function main_parts($content)
		{
			if (qa_opt('badge_active') && $this->template == 'user' && qa_opt('badge_admin_user_field') && (qa_get('tab')=='badges' || qa_opt('badge_admin_user_field_no_tab')) && isset($content['raw']['userid'])) { 
					$userid = $content['raw']['userid'];
					if(!qa_opt('badge_admin_user_field_no_tab'))
						foreach($content as $i => $v)
							if(strpos($i,'form') === 0)
								unset($content[$i]);
					$content['form-badges-list'] = qa_badge_plugin_user_form($userid);
			}

			if (qa_opt('badge_active') && $this->template == 'user' && qa_opt('badge_admin_user_field') && (qa_get('tab')=='badges' || qa_opt('badge_admin_user_field_no_tab')) && isset($content['raw']['userid'])) { 
				$this->output('<div class="badges-tabs-content">');
					qa_html_theme_base::main_parts($content);
				$this->output('</div>');
			} else {
				qa_html_theme_base::main_parts($content);
			}

		}

		function post_meta_who($post, $class)
		{
			if (empty($post['who']['level']) && @$post['who'] && @$post['who']['data'] && qa_opt('badge_active') && (bool)qa_opt('badge_admin_user_widget') && ($class != 'qa-q-item' || qa_opt('badge_admin_user_widget_q_item')) ) {
				$post['who']['suffix'] = (@$post['who']['suffix']).' <span class="badge-medals-widget">'.qa_lang('badges/badge_anonymous_user').'</span>'; // No data
			} else if (@$post['who'] && @$post['who']['data'] && qa_opt('badge_active') && (bool)qa_opt('badge_admin_user_widget') && ($class != 'qa-q-item' || qa_opt('badge_admin_user_widget_q_item')) ) {
				$handle = preg_replace('/ *<[^>]+> */', '',$post['who']['data']);
				$post['who']['suffix'] = (@$post['who']['suffix']).' '.qa_badge_plugin_user_widget($handle);
			}
			
			qa_html_theme_base::post_meta_who($post, $class);
		}

		function logged_in()
		{
			if (qa_opt('badge_active') && (bool)qa_opt('badge_admin_loggedin_widget') && @$this->content['loggedin']['data'] != null) {
				$handle = preg_replace('/ *<[^>]+> */', '',$this->content['loggedin']['data']);
				$this->content['loggedin']['data'] = $this->content['loggedin']['data'].' '.qa_badge_plugin_user_widget($handle);
			}
			
			qa_html_theme_base::logged_in();
		}
		
		function q_view_main($q_view) {
			qa_html_theme_base::q_view_main($q_view);

		// badge check on view update

			if (qa_opt('badge_active') && isset($this->content['inc_views_postid'])) {

				$uid = $q_view['raw']['userid'];

				if(!$uid) return; // anonymous

				$oid = $this->content['inc_views_postid'];

				// total views check

				$views = $q_view['raw']['views'];
				$views++; // because we haven't incremented the views yet
				
				$badges = array('notable_question','popular_question','famous_question');

				qa_badge_award_check($badges, $views, $uid, $oid,2);

			
				// personal view count increase and badge check
				
				$uid = qa_get_logged_in_userid();
				
				qa_db_query_sub(
					'UPDATE ^achievements SET questions_read=questions_read+1 WHERE user_id=# ',
					$uid
				);
				
				$views = qa_db_read_one_value(
					qa_db_query_sub(
						'SELECT questions_read FROM ^achievements WHERE user_id=# ',
						$uid
					),
					true
				);		
						
				$badges = array('reader','avid_reader','devoted_reader');

				qa_badge_award_check($badges, $views, $uid,null,2);
			
			}
		}
		
		public function ranking_score($item, $class)
		{
			$this->ranking_cell($item['score'], $class . '-score');
			$this->output(qa_badge_plugin_user_widget($item['raw']['handle']));
		}
		
		// badge popup notification

		function badge_notify() {
			$userid = qa_get_logged_in_userid();
			
			qa_db_query_sub(
				'CREATE TABLE IF NOT EXISTS ^userbadges ('.
					'awarded_at DATETIME NOT NULL,'.
					'user_id INT(11) NOT NULL,'.
					'notify TINYINT DEFAULT 0 NOT NULL,'.
					'object_id INT(10),'.
					'badge_slug VARCHAR (64) CHARACTER SET ascii DEFAULT \'\','.
					'id INT(11) NOT NULL AUTO_INCREMENT,'.
					'PRIMARY KEY (id)'.
				') ENGINE=MyISAM DEFAULT CHARSET=utf8'
			);			
			
			$result = qa_db_read_all_values(
				qa_db_query_sub(
					'SELECT badge_slug FROM ^userbadges WHERE user_id=# AND notify>=1',
					$userid
				)
			);
			if(count($result) > 0) {
				$notice = '<div class="notify-container">';
				
					if(count($result) == 1) {
						$slug = $result[0];
						$badge_name=qa_lang('badges/'.$slug);
						if(!qa_opt('badge_'.$slug.'_name')) qa_opt('badge_'.$slug.'_name',$badge_name);
						$name = qa_opt('badge_'.$slug.'_name');
						
						$notice .= '<div class="badge-notify notify"><div class="badge-notify-text">'.qa_lang('badges/badge_notify')."'".$name.'\'<span class="badge-profile-check">'.qa_lang('badges/badge_notify_profile_pre').'<a href="'.qa_path_html((QA_FINAL_EXTERNAL_USERS?qa_path_to_root():'').'user/'.qa_get_logged_in_handle(),array('tab'=>'badges'),qa_path('')).'">'.qa_lang('badges/badge_notify_profile').'</a></span></div><div class="notify-close" onclick="jQuery(this).parent().fadeOut()">&#x2715;</div></div>';
					}
					else {
						$number_text = count($result)>2?str_replace('#', count($result)-1, qa_lang('badges/badge_notify_multi_plural')):qa_lang('badges/badge_notify_multi_singular');
						$slug = $result[0];
						$badge_name=qa_lang('badges/'.$slug);
						if(!qa_opt('badge_'.$slug.'_name')) qa_opt('badge_'.$slug.'_name',$badge_name);
						$name = qa_opt('badge_'.$slug.'_name');
						$notice .= '<div class="badge-notify notify"><div class="badge-notify-text">'.qa_lang('badges/badge_notify')."'".$name.'\'&nbsp;'.$number_text.'<span class="badge-profile-check">'.qa_lang('badges/badge_notify_profile_pre').'<a href="'.qa_path_html('user/'.qa_get_logged_in_handle(),array('tab'=>'badges'),qa_path('')).'">'.qa_lang('badges/badge_notify_profile').'</a></span></div><div class="notify-close" onclick="jQuery(this).parent().fadeOut()">&#x2715;</div></div>';
					}

				$notice .= '</div>';
				
				// remove notification flag
				
				qa_db_query_sub(
					'UPDATE ^userbadges SET notify=0 WHERE user_id=# AND notify>=1',
					$userid
				);
				$this->badge_notice = $notice;
			}
		}

	// etc
		
		function trigger_notify($message) {
			$notice = '<div class="notify-container"><div class="badge-notify notify"><div class="badge-notify-text">'.qa_lang('badges/badge_notify')."'".$message.'\'!<span class="badge-profile-check">'.qa_lang('badges/badge_notify_profile_pre').'<a href="'.qa_path('').'user/'.qa_get_logged_in_handle().'">'.qa_lang('badges/badge_notify_profile').'</a></span></div><div class="notify-close" onclick="jQuery(this).parent().parent().fadeOut()">&#x2715;</div></div></div>';
			$this->output($notice);
		}
		
		function priviledge_notify() { // gained priviledge
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
		// grab the handle of the profile you're looking at
		function _user_handle()
		{
			preg_match( '#user/([^/]+)#', $this->request, $matches );
			return !empty($matches[1]) ? $matches[1] : null;
		}		
	}
	
