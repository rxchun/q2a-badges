<?php

class qa_html_theme_layer extends qa_html_theme_base {

// Patch Version
private $patchNumber = '?v=121';

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
							'label' => qa_lang_html('badges/badges'),
							'selected' => qa_get('tab')=='badges'?true:false
						),
					);
				}
				else {
					$this->content['navigation']['sub']['badges'] = array(
						'url' => qa_path_html('user/'.$this->_user_handle(), array('tab'=>'badges'), qa_path('')),
						'label' => qa_lang_html('badges/badges'),
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
			try {
				$this->badge_notify();
			} catch (\Throwable $e) {
				error_log('badge_notify error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
			}
		}

		// Added fix to remove empty <style> in <head>
		if (qa_opt('badges_css') != null) {
			$this->output('<style>',qa_opt('badges_css'),'</style>');
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
		if (qa_opt('site_theme') === 'Polaris') {
			qa_html_theme_base::post_meta_who($post, $class);
			return;
		}

		if (empty($post['who']['level']) && @$post['who'] && @$post['who']['data'] && qa_opt('badge_active') && (bool)qa_opt('badge_admin_user_widget') && ($class != 'qa-q-item' || qa_opt('badge_admin_user_widget_q_item')) ) {
			$post['who']['suffix'] = (@$post['who']['suffix']).' <span class="qa-badge-medals-widget">'.qa_lang_html('badges/badge_anonymous_user').'</span>'; // No data
		} else if (@$post['who'] && @$post['who']['data'] && qa_opt('badge_active') && (bool)qa_opt('badge_admin_user_widget') && ($class != 'qa-q-item' || qa_opt('badge_admin_user_widget_q_item')) ) {
			$user = isset($post['raw']['userid']) ? (int)$post['raw']['userid'] : preg_replace('/ *<[^>]+> */', '',$post['who']['data']);
			$post['who']['suffix'] = (@$post['who']['suffix']).' '.qa_badge_plugin_user_widget($user);
		}
		
		qa_html_theme_base::post_meta_who($post, $class);
	}

	function logged_in()
	{
		if (qa_opt('site_theme') === 'Polaris') {
			qa_html_theme_base::logged_in();
			return;
		}

		$widget = '';

		if (qa_opt('badge_active') && (bool)qa_opt('badge_admin_loggedin_widget') && !empty($this->content['loggedin']['data'])) {
			$userid = qa_get_logged_in_userid();
			if ($userid) {
				$widget = qa_badge_plugin_user_widget((int)$userid);

				if ($widget && (qa_opt('site_theme') !== 'Polaris')) {
					$this->content['loggedin']['data'] .= ' ' . $widget;
					$widget = '';
				}
			}
		}

		qa_html_theme_base::logged_in();

		if ($widget) {
			$this->output(' ' . $widget);
		}
	}
	
	function q_view_main($q_view) {
		qa_html_theme_base::q_view_main($q_view);

	// badge check on view update

		if (
			qa_opt('badge_active') &&
			isset($this->content['inc_views_postid']) &&
			isset($q_view['raw']['userid'], $q_view['raw']['views'])
		) {

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
		if (qa_opt('site_theme') === 'Polaris') {
			return;
		}

		if (isset($item['raw']['userid'])) {
			$this->output(qa_badge_plugin_user_widget((int)$item['raw']['userid']));
		} elseif (isset($item['raw']['handle'])) {
			$this->output(qa_badge_plugin_user_widget($item['raw']['handle']));
		}
	}
	
	public function body_hidden()
	{
		qa_html_theme_base::body_hidden();
		$patchNumber = $this->patchNumber;
		
		if (qa_opt('badge_active') && ($this->template == 'user' || $this->template == 'custom')) {
			$this->output_raw('<script src="'.QA_HTML_THEME_LAYER_URLTOROOT.'js/badges.min.js'.$patchNumber.'" defer></script>');
		}
	}
	
	/**
     * Ensure userbadges table exists
     */
    private function ensure_userbadges_table() {
        qa_db_query_sub('
            CREATE TABLE IF NOT EXISTS ^userbadges (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                object_id INT(10),
                badge_slug VARCHAR(64) CHARACTER SET ascii DEFAULT "",
                awarded_at DATETIME NOT NULL,
                notify TINYINT(1) DEFAULT 0 NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    /**
     * Get badges that have notify flag
     */
    private function get_badges_to_notify($userId) {
        return qa_db_read_all_values(
            qa_db_query_sub(
                'SELECT badge_slug FROM ^userbadges WHERE user_id = # AND notify >= 1',
                $userId
            )
        );
    }

    /**
     * Clear notification flags
     */
    private function clear_badge_notifications($userId) {
        qa_db_query_sub(
            'UPDATE ^userbadges SET notify = 0 WHERE user_id = # AND notify >= 1',
            $userId
        );
    }

    /**
     * Generate the user's badge tab URL
     */
    private function get_user_badges_url() {
        return qa_path_html(
            (QA_FINAL_EXTERNAL_USERS ? qa_path_to_root() : '') .
            'user/' . qa_get_logged_in_handle(),
            ['tab' => 'badges']
        );
    }

    /**
     * Render badge notification HTML
     */
    private function render_badge_notification_html($badgeText, $profileUrl) {
        $preText  = qa_lang_html('badges/badge_notify_view_it');
        $linkText = qa_lang_html('badges/badge_notify_your_profile');
		$linkViewMore = qa_lang_html('badges/badge_notify_see_more');

        return '
        <div class="notify-container">
            <div class="qa-badge-notify notify">
                <div class="qa-badge-notify-text">
                    ' . $badgeText . '
					<span class="qa-badge-check-profile">
                        ' . $preText . ' <a class="notify-link" href="' . $profileUrl . '">' . $linkText . '</a>.
                    </span>
                </div>
				<div class="qa-badge-notify-interact">
					<a class="qa-badge-notify-button notify-link" href="' . $profileUrl . '">' . $linkViewMore . '</a>
					<div class="qa-badge-notify-button notify-close" onclick="this.closest(\'.qa-badge-notify\').style.display=\'none\';" aria-label="Close notification">&#x2715;</div>
				</div>
            </div>
        </div>';
    }

    /**
     * Show badge popup notification
     */
    public function badge_notify() {
        $userId = qa_get_logged_in_userid();
        if (!$userId) {
            return;
        }

        $this->ensure_userbadges_table();

        $badgeSlugs = $this->get_badges_to_notify($userId);
        if (empty($badgeSlugs)) {
            return;
        }

        $badgeCount = count($badgeSlugs);
        $slug       = $badgeSlugs[0];

        // Get or set badge name
        $badgeName = qa_lang_html('badges/' . $slug);
        if (!qa_opt('badge_' . $slug . '_name')) {
            qa_opt('badge_' . $slug . '_name', $badgeName);
        }
        $name = qa_html(qa_opt('badge_' . $slug . '_name'));

        // Badge text
        if ($badgeCount === 1) {
            $badgeText = qa_lang_html('badges/badge_notify') .
                         ' <span class="qa-badge">' . $name . '</span>';
        } else {
            $extraCount = $badgeCount - 1;
            $numberText = ($badgeCount > 2)
                ? str_replace('#', $extraCount, qa_lang_html('badges/badge_notify_multi_plural'))
                : qa_lang_html('badges/badge_notify_multi_singular');

            $badgeText = qa_lang_html('badges/badge_notify') .
                         ' <span class="qa-badge">' . $name . '</span> ' .
                         qa_html($numberText);
        }

        // Always link to badges tab
        $profileUrl = $this->get_user_badges_url();

        // Render notice
        $this->badge_notice = $this->render_badge_notification_html($badgeText, $profileUrl);

        // Reset notify flags
        $this->clear_badge_notifications($userId);
    }

    /**
     * Trigger a manual notification (generic)
     */
    public function trigger_notify($message) {
        $badgeText  = qa_lang_html('badges/badge_notify') .
                      ' <span class="qa-badge">' . qa_html($message) . '</span>';

        // Always link to badges tab
        $profileUrl = $this->get_user_badges_url();

        $noticeHtml = $this->render_badge_notification_html($badgeText, $profileUrl);
        $this->output($noticeHtml);
    }
	
	// gained priviledge
	function priviledge_notify() {
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

