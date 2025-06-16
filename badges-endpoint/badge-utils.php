<?php

// Security check
if (!defined('QA_VERSION')) {
    header('Location: ../../');
    exit;
}

/**
 * Retrieves the total number of times each badge has been awarded, grouped by badge slug and user ID.
 *
 * The result is a multidimensional associative array in the following format:
 *
 * [
 *     'badge_slug' => [
 *         'user_id_1' => count,
 *         'user_id_2' => count,
 *         ...
 *         'count'     => total_awards_for_this_badge
 *     ],
 *     ...
 * ]
 *
 * This function filters out disabled badges by checking the `badge_{slug}_enabled` option.
 * It queries the `^userbadges` table and aggregates awards per badge slug and user.
 *
 * @return array A nested array where each badge slug maps to user award counts and a total count.
 */
function qa_get_badge_award_counts() {
    $result = qa_db_read_all_assoc(
        qa_db_query_sub('SELECT user_id, badge_slug FROM ^userbadges')
    );

    $count = [];

    foreach ($result as $r) {
        if (qa_opt('badge_' . $r['badge_slug'] . '_enabled') == '0') continue;

        if (isset($count[$r['badge_slug']][$r['user_id']])) {
            $count[$r['badge_slug']][$r['user_id']]++;
        } else {
            $count[$r['badge_slug']][$r['user_id']] = 1;
        }

        if (isset($count[$r['badge_slug']]['count'])) {
            $count[$r['badge_slug']]['count']++;
        } else {
            $count[$r['badge_slug']]['count'] = 1;
        }
    }

    return $count;
}

/**
 * Truncates a title to a specified length.
 *
 * @param string $title The title string.
 * @param int $length Max character length.
 * @return string Truncated title.
 */
function truncate_badge_title($title, $length = 60) {
    return (qa_strlen($title) > $length) ? qa_substr($title, 0, $length) : $title;
}

/**
 * Retrieves the URL for a user's avatar image.
 *
 * @param string $handle The user's handle.
 * @param int $size The desired image size.
 * @return string Avatar URL.
 */
function get_user_avatar($handle, $size) {
    $useraccount = qa_db_single_select(qa_db_user_account_selectspec($handle, false));
    $allowGravatar = qa_opt('avatar_allow_gravatar');
    $allowUpload = qa_opt('avatar_allow_upload');
    $defaultBlobId = qa_opt('avatar_default_blobid');
    $defaultShow = qa_opt('avatar_default_show');
    $isHttps = qa_is_https_probably();

    if ($allowGravatar && (@$useraccount['flags'] & QA_USER_FLAGS_SHOW_GRAVATAR)) {
        return sprintf('%s://www.gravatar.com/avatar/%s?s=%s',
            $isHttps ? 'https' : 'http',
            md5(strtolower(trim($useraccount['email']))),
            $size
        );
    }

    if ($allowUpload && (@$useraccount['flags'] & QA_USER_FLAGS_SHOW_AVATAR) && isset($useraccount['avatarblobid'])) {
        return qa_path('image', ['qa_blobid' => $useraccount['avatarblobid'], 'qa_size' => $size], qa_path(''), QA_URL_FORMAT_PARAMS);
    }

    if (($allowGravatar || $allowUpload) && $defaultShow && !empty($defaultBlobId)) {
        return qa_path('image', ['qa_blobid' => $defaultBlobId, 'qa_size' => $size], qa_path(''), QA_URL_FORMAT_PARAMS);
    }

    $pluginDirectory = basename(dirname(__DIR__));
    return qa_path('') . qa_opt('site_url') . "/qa-plugin/{$pluginDirectory}/images/default-avatar-35.png";
}

/**
 * Generates HTML for a user's avatar, with lazy loading if Polaris theme is active.
 *
 * @param string $handle The user's handle.
 * @param string $avatar_url The avatar URL.
 * @return string HTML block.
 */
function generate_avatar_html($handle, $avatar_url) {
    $lazyImgSrc = qa_opt('site_theme') === 'Polaris'
        ? 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='
        : $avatar_url;

    return '
        <div class="bliu-avatar flex">
            <a class="qa-avatar-link" href="' . qa_path_html('user/' . $handle) . '">
                <img class="qa-avatar-image qa-lazy-img" width="44" height="44"
                    src="' . $lazyImgSrc . '" data-src="' . $avatar_url . '" alt="' . qa_html($handle) . '">
            </a>
        </div>';
}
