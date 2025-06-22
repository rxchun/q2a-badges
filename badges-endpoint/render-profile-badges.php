<?php

// Prevent direct browser access: allow only fetch requests with custom header
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'Fetch') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

// Core Q2A includes
require_once '../../../qa-include/qa-base.php';
require_once QA_INCLUDE_DIR.'db/selects.php';
require_once QA_INCLUDE_DIR.'app/posts.php';
require_once QA_INCLUDE_DIR.'app/users.php';

// Plugin utility functions
require_once '../inc/badge-utils.php';

// -----------------------------------------------------------------------------
// Input validation & badge settings check
// -----------------------------------------------------------------------------

$slug   = qa_get('slug');
$userid = (int) qa_get('userid');
$offset = (int) qa_get('offset');
$limit  = (int) qa_get('limit');
if ($limit <= 0) $limit = 10;

if (!$slug || !$userid || !qa_opt('badge_' . $slug . '_enabled')) {
    echo '<div class="error">Invalid badge or user.</div>';
    return;
}

// -----------------------------------------------------------------------------
// Get badge source posts for a given user and badge slug
// -----------------------------------------------------------------------------

$result = qa_db_read_all_assoc(
    qa_db_query_sub(
        'SELECT object_id FROM ^userbadges WHERE user_id=# AND badge_slug=$ LIMIT #,#',
        $userid, $slug, $offset, $limit * 3 // over-fetch to account for possible hidden/invalid posts
    )
);

// Display newest to oldest
$result = array_reverse($result);

// -----------------------------------------------------------------------------
// Collect parent IDs for comments/answers to batch fetch parent titles
// -----------------------------------------------------------------------------

$parentIds = [];
foreach ($result as $row) {
    $postid = $row['object_id'];
    if (!$postid) continue;

    $post = qa_post_get_full($postid);
    if (!$post) continue;

    if ($post['parentid']) {
        $parentIds[] = $post['parentid'];
    }
}

// -----------------------------------------------------------------------------
// Batch fetch parent post titles (to avoid N+1 queries)
// -----------------------------------------------------------------------------

$parentTitles = [];
if (!empty($parentIds)) {
    $parentPosts = qa_db_read_all_assoc(
        qa_db_query_sub(
            'SELECT postid, title FROM ^posts WHERE postid IN (#)',
            $parentIds
        )
    );

    foreach ($parentPosts as $parentPost) {
        $parentTitles[$parentPost['postid']] = $parentPost['title'];
    }
}

// -----------------------------------------------------------------------------
// Render badge sources (up to limit), with appropriate links/titles
// -----------------------------------------------------------------------------

$valid = 0;
$isHiddenPost = false;

foreach ($result as $row) {
    $postid = $row['object_id'];
    if (!$postid) continue;

    $post = qa_post_get_full($postid);
    if (!$post) continue;

    $title = isset($post['title']) ? $post['title'] : '[no title]';
    $decodedTitle = htmlspecialchars_decode($title, ENT_QUOTES);
    $titleLength = 60;

    $titleCropped = truncate_badge_title($decodedTitle, $titleLength);
    $safeTitle = htmlspecialchars($titleCropped, ENT_NOQUOTES);

    if (qa_strlen($safeTitle) === 0) {
        $safeTitle = '<span>' . qa_lang('badges/badge_empty_source') . '</span>';
    }

    // Default to post URL
    $url = qa_q_path($postid, $post['title']);

    // Use parent post if available (answers/comments)
    if ($post['parentid']) {
        $parentTitle = isset($parentTitles[$post['parentid']]) ? $parentTitles[$post['parentid']] : '[no parent title]';
        $decodedTitle = htmlspecialchars_decode($parentTitle, ENT_QUOTES);

        $url = qa_q_path($post['parentid'], $parentTitle);
        $safeTitle = htmlspecialchars(truncate_badge_title($decodedTitle, $titleLength), ENT_NOQUOTES);

        if (empty($parentTitles[$post['parentid']])) {
            $safeTitle = '<span>' . qa_lang('badges/badge_empty_source') . '</span>';
            $isHiddenPost = true;
        }
    }

    // Append anchor for answers/comments
    if ($post['type'] === 'C') {
        $url .= '?show=' . $post['parentid'] . '#c' . $postid;
    } else {
        $url .= '#a' . $postid;
    }

    // Make full absolute URL
    $baseUrl = qa_opt('site_url');
    $fullUrl = $baseUrl . $url;

    // Title tooltip if cropped
    $encodedTitle = htmlspecialchars($decodedTitle, ENT_NOQUOTES);
    $titleAttr = strlen($encodedTitle) > 40 ? 'title="' . $encodedTitle . '"' : '';

    $showHref = '<a href="' . qa_html($fullUrl) . '" target="_blank" ' . $titleAttr . '>' . $safeTitle . '</a>';
    if ($isHiddenPost) {
        $showHref = '<span ' . $titleAttr . '>' . $safeTitle . '</span>';
    }

    echo '<div class="badge-source">' . $showHref . '</div>';

    $valid++;
    $isHiddenPost = false;

    if ($valid >= $limit) break;
}
