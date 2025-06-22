<?php

// Prevent direct browser access: allow only fetch requests with custom header
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'Fetch') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

// Core Q2A includes
require_once '../../../qa-include/qa-base.php';
require_once QA_INCLUDE_DIR.'app/users.php';
require_once QA_INCLUDE_DIR.'app/options.php';
require_once QA_INCLUDE_DIR.'db/users.php';
require_once QA_INCLUDE_DIR.'db/selects.php';

// Plugin utility functions
require_once '../inc/badge-utils.php';

// -----------------------------------------------------------------------------
// Input validation
// -----------------------------------------------------------------------------

$slug   = qa_get('slug');
$offset = (int) qa_get('offset');
$limit  = (int) qa_get('limit');
if ($limit <= 0) $limit = 20;

if (!$slug || !qa_opt('badge_' . $slug . '_enabled')) {
    echo '<div class="error">Invalid badge.</div>';
    return;
}

// -----------------------------------------------------------------------------
// Get all badge award counts
// -----------------------------------------------------------------------------

$allCounts = qa_get_badge_award_counts();

if (!isset($allCounts[$slug])) {
    echo '<div class="error">No users have earned this badge yet.</div>';
    return;
}

// Get user-level award counts for this badge
$count = $allCounts[$slug];
unset($count['count']); // Remove total count summary key

// Reverse sort so newest awards appear first
krsort($count);

// -----------------------------------------------------------------------------
// Paginate and prepare list of user IDs to fetch
// -----------------------------------------------------------------------------

// Over-fetch to account for invalid users
$badgeUsers = array_slice($count, $offset, $limit * 3, true);

$valid = 0;
$seen_users = [];

// Remove duplicate user IDs (retain most recent counts)
$badge_awards = $allCounts[$slug];
unset($badge_awards['count']);
krsort($badge_awards);

foreach ($badge_awards as $uid => $ucount) {
    if (isset($seen_users[$uid])) {
        continue;
    }
    $seen_users[$uid] = $ucount;
}

// Final list after duplication removal and pagination
$paginated_users = array_slice($seen_users, $offset, $limit, true);

// -----------------------------------------------------------------------------
// Fetch user handles in batch
// -----------------------------------------------------------------------------

$userIds = array_keys($paginated_users);

$handle_map = [];
if (count($userIds) > 0) {
    $handles = qa_db_read_all_assoc(
        qa_db_query_sub(
            'SELECT userid, handle FROM ^users WHERE userid IN (#)',
            $userIds
        )
    );

    foreach ($handles as $user) {
        $handle_map[$user['userid']] = $user['handle'];
    }
}

// -----------------------------------------------------------------------------
// Render HTML block for each user
// -----------------------------------------------------------------------------

foreach ($paginated_users as $uid => $ucount) {
    $handle = isset($handle_map[$uid]) ? $handle_map[$uid] : null;
    if (!$handle) continue;

    $avatar_url  = get_user_avatar($handle, 88); // Use util function
    $avatarHTML  = generate_avatar_html($handle, $avatar_url, 44); // Use util function
    $profile_url = qa_path_html('user/' . $handle);
    $handle_html = qa_html($handle);
    $info        = ($ucount > 1) ? ' x' . $ucount : '';
    $visit_label = qa_lang('badges/visit_profile');

    echo <<<HTML
        <li class="badge-list-item flex flex-row">
            <div class="bliu-avatar flex">
                {$avatarHTML}
            </div>
            <div class="bliu-container flex">
                <a class="bliu-link" href="{$profile_url}">{$handle_html}</a>
                <span class="bliu-info">{$info}</span>
            </div>
            <div class="bliu-more flex">
                <a class="bliu-button" href="{$profile_url}">{$visit_label}</a>
            </div>
        </li>
HTML;
}
