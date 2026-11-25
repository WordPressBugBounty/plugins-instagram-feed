<?php

if (!defined('ABSPATH')) {
	die('-1');
}

/**
 * Class SB_Instagram_Post
 *
 * Primarily used for resizing and storing images, this class
 * performs certain tasks with data for a single post.
 *
 * Currently used only by the SB_Instagram_Post_Set class
 *
 * @since 2.0/4.0
 */
class SB_Instagram_Post
{
	/**
	 * Option holding the retry state of failed image downloads. A post's entry
	 * is removed when its image is saved or marked as an error, or when it is
	 * stale and a new post needs room. Reset Image Storage deletes the option.
	 */
	public const DOWNLOAD_RETRY_OPTION = 'sbi_image_download_retries';

	/**
	 * Failed downloads before a post is marked as an error.
	 */
	public const DOWNLOAD_MAX_ATTEMPTS = 3;

	/**
	 * Seconds between download attempts.
	 */
	public const DOWNLOAD_RETRY_WAIT = 3600;

	/**
	 * Posts tracked at once. When the map is full, stale entries make room
	 * first. If none is stale, a post not in it is not added, and its first
	 * failed download marks it as an error.
	 */
	public const DOWNLOAD_RETRY_MAX_ENTRIES = 200;

	/**
	 * Seconds after an entry's wait ends before it counts as stale: one day.
	 * Its post has most likely left the feed. Stale entries are dropped only
	 * when the map is full and a new post needs room.
	 */
	public const DOWNLOAD_RETRY_STALE_AFTER = 86400;

	/**
	 * @var string
	 */
	private $instagram_post_id;

	/**
	 * @var array
	 */
	private $instagram_api_data;

	/**
	 * @var string
	 */
	private $db_id;

	/**
	 * @var string
	 */
	private $media_id;

	/**
	 * @var string
	 */
	private $top_time_stamp;

	/**
	 * @var bool|int
	 */
	private $images_done;

	/**
	 * @var array
	 */
	private $resized_image_array;

	/**
	 * @var object|SB_Instagram_Data_Encryption
	 *
	 * @since 5.14.5
	 */
	private $encryption;

	/**
	 * SB_Instagram_Post constructor.
	 *
	 * @param string $instagram_post_id from the Instagram API
	 */
	public function __construct($instagram_post_id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . SBI_INSTAGRAM_POSTS_TYPE;

		$feed_id_match = $wpdb->get_results($wpdb->prepare("SELECT id, media_id, top_time_stamp, images_done FROM $table_name WHERE instagram_id = %s LIMIT 1", $instagram_post_id), ARRAY_A);

		$this->db_id = !empty($feed_id_match) ? $feed_id_match[0]['id'] : '';
		$this->media_id = !empty($feed_id_match) ? $feed_id_match[0]['media_id'] : '';
		$this->top_time_stamp = !empty($feed_id_match) && isset($feed_id_match[0]['top_time_stamp']) ? $feed_id_match[0]['top_time_stamp'] : '';
		$this->images_done = !empty($feed_id_match) && isset($feed_id_match[0]['images_done']) ? $feed_id_match[0]['images_done'] === '1' : 0;

		$this->instagram_post_id = $instagram_post_id;

		$this->encryption = new SB_Instagram_Data_Encryption();
	}

	/**
	 * Whether or not this post has already been saved in the custom table
	 *
	 * @return bool
	 *
	 * @since 2.0/4.0
	 */
	public function exists_in_posts_table()
	{
		return !empty($this->db_id);
	}

	/**
	 * Whether or not resized image files have already been recorded as being created
	 * in the database table
	 *
	 * @return bool|int
	 *
	 * @since 2.0/4.0
	 */
	public function images_done_resizing()
	{
		return $this->images_done;
	}

	/**
	 * @param array $instagram_api_data
	 *
	 * @since 2.0/4.0
	 */
	public function set_instagram_api_data($instagram_api_data)
	{
		$this->instagram_api_data = $instagram_api_data;
	}

	/**
	 * Used for sorting top posts since they don't have a posted on date
	 *
	 * @return string
	 *
	 * @since 2.0/4.0
	 */
	public function get_top_time_stamp()
	{
		return $this->top_time_stamp;
	}

	/**
	 * Used to save information about the post before image resizing is done to
	 * prevent a potentially storing multiple entries for the same post
	 *
	 * @param mixed|string|bool $transient_name (optional)
	 * @param null              $timestamp_override (optional)
	 *
	 * @return bool
	 *
	 * @since 2.0/4.0
	 */
	public function save_in_db($transient_name = false, $timestamp_override = null)
	{
		global $wpdb;

		$parsed_data = $this->get_parsed_post_data();

		$timestamp = !empty($timestamp_override) && empty($parsed_data['timestamp']) ? $timestamp_override : $parsed_data['timestamp'];

		$entry_data = array(
			"'" . date('Y-m-d H:i:s') . "'",
			"'" . esc_sql($parsed_data['id']) . "'",
			"'" . esc_sql($timestamp) . "'",
			"'" . esc_sql($timestamp) . "'",
			"'" . esc_sql($this->encryption->encrypt(sbi_json_encode($this->instagram_api_data))) . "'",
			"'pending'",
			"'pending'",
			0,
			"'" . date('Y-m-d H:i:s') . "'",
		);

		$entry_string = implode(',', $entry_data);
		$table_name = $wpdb->prefix . SBI_INSTAGRAM_POSTS_TYPE;

		$timestamp_column = 'time_stamp';
		if (substr($transient_name, 4, 1) === '+') {
			$timestamp_column = 'top_time_stamp';
		}

		$error = $wpdb->query(
			"INSERT INTO $table_name
			(created_on,instagram_id,time_stamp,top_time_stamp,json_data,media_id,sizes,images_done,last_requested)
			VALUES ($entry_string);"
		);

		if ($error !== false) {
			$this->db_id = $wpdb->insert_id;
			$this->insert_sbi_instagram_feeds_posts($transient_name);
		} else {
			global $sb_instagram_posts_manager;

			$error = $wpdb->last_error;
			$query = $wpdb->last_query;

			$sb_instagram_posts_manager->add_error('storage', __('Error inserting post.', 'instagram-feed') . ' ' . $error . '<br><code>' . $query . '</code>');
		}

		return true;
	}

	/**
	 * Uses the saved json for the post to be used for updating records
	 *
	 * @param bool $all
	 *
	 * @return array
	 *
	 * @since 2.0/4.0
	 */
	private function get_parsed_post_data($all = true)
	{

		$instagram_post_id = isset($this->instagram_api_data['id']) ? $this->instagram_api_data['id'] : '';
		$comments_count = isset($this->instagram_api_data['comments_count']) ? $this->instagram_api_data['comments_count'] : '';
		$like_count = isset($this->instagram_api_data['like_count']) ? $this->instagram_api_data['like_count'] : '';

		$parsed_data = array(
			'comments_count' => $comments_count,
			'like_count' => $like_count,
		);

		if ($all) {
			$caption = isset($this->instagram_api_data['caption']) ? $this->instagram_api_data['caption'] : '';
			$media_url = isset($this->instagram_api_data['media_url']) ? $this->instagram_api_data['media_url'] : '';
			$media_type = isset($this->instagram_api_data['media_type']) ? $this->instagram_api_data['media_type'] : '';

			$timestamp = '';
			if (isset($this->instagram_api_data['timestamp'])) {
				$timestamp_parts = explode(' ', $this->instagram_api_data['timestamp']);
				$timestamp = str_replace('T', ' ', $timestamp_parts[0]);
			}

			$username = isset($this->instagram_api_data['username']) ? $this->instagram_api_data['username'] : '';
			$permalink = isset($this->instagram_api_data['permalink']) ? $this->instagram_api_data['permalink'] : '';
			$children = isset($this->instagram_api_data['children']) ? sbi_json_encode($this->instagram_api_data['children']) : '';

			$parsed_data['caption'] = $caption;
			$parsed_data['media_url'] = $media_url;
			$parsed_data['id'] = $instagram_post_id;
			$parsed_data['media_type'] = $media_type;
			$parsed_data['timestamp'] = $timestamp;
			$parsed_data['username'] = $username;
			$parsed_data['permalink'] = $permalink;
			$parsed_data['children'] = $children;
		}

		return $parsed_data;
	}

	/**
	 * Add a record of this post being used for the specified transient name (feed id)
	 *
	 * @param string $transient_name
	 *
	 * @return int
	 *
	 * @since 2.0/4.0
	 */
	public function insert_sbi_instagram_feeds_posts($transient_name)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . SBI_INSTAGRAM_FEEDS_POSTS;
		// the number is removed from the transient name for backwards compatibilty.
		$feed_id_array = explode('#', $transient_name);
		$feed_id = $feed_id_array[0];

		if (!empty($this->db_id)) {
			$entry_data = array(
				$this->db_id,
				"'" . esc_sql($this->instagram_api_data['id']) . "'",
				"'" . esc_sql($feed_id) . "'",
			);

			if (!empty($this->instagram_api_data['term'])) {
				$entry_data[] = "'" . esc_sql(strtolower(str_replace('#', '', $this->instagram_api_data['term']))) . "'";
				$entry_string = implode(',', $entry_data);

				$error = $wpdb->query("INSERT INTO $table_name (id,instagram_id,feed_id,hashtag) VALUES ($entry_string);");
			} else {
				$entry_string = implode(',', $entry_data);
				$error = $wpdb->query("INSERT INTO $table_name (id,instagram_id,feed_id) VALUES ($entry_string);");
			}
		} else {
			global $sb_instagram_posts_manager;

			$sb_instagram_posts_manager->add_error('storage', __('Error inserting post.', 'instagram-feed') . ' ' . __('No database ID.', 'instagram-feed'));
			return false;
		}

		if ($error !== false) {
			return $wpdb->insert_id;
		} else {
			global $sb_instagram_posts_manager;
			$error = $wpdb->last_error;
			$query = $wpdb->last_query;
			$sb_instagram_posts_manager->add_error('storage', __('Error inserting post.', 'instagram-feed') . ' ' . $error . '<br><code>' . $query . '</code>');
		}
	}

	/**
	 * Return relevant data for resized images for this post
	 *
	 * @return array
	 *
	 * @since 2.0/4.0
	 */
	public function get_resized_image_array()
	{
		if (empty($this->resized_image_array)) {
			global $wpdb;

			$posts_table_name = $wpdb->prefix . SBI_INSTAGRAM_POSTS_TYPE;
			$stored = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT media_id, aspect_ratio FROM $posts_table_name
					WHERE instagram_id = %s
					LIMIT 1",
					$this->instagram_post_id
				),
				ARRAY_A
			);

			if (isset($stored[0])) {
				$return = array(
					'id' => $stored[0]['media_id'],
					'ratio' => $stored[0]['aspect_ratio'],
				);
				$this->resized_image_array = $return;
				return $return;
			} else {
				return array();
			}
		} else {
			return $this->resized_image_array;
		}
	}

	/**
	 * Controls whether or not the database record will be updated for this post.
	 * Called after images are successfully created.
	 *
	 * @param bool   $update_last_requested
	 * @param bool   $transient_name
	 * @param array  $image_sizes
	 * @param string $upload_dir
	 * @param string $upload_url
	 * @param bool   $timestamp_for_update
	 *
	 * @return bool
	 *
	 * @since 2.0/4.0
	 */
	public function update_db_data($update_last_requested = true, $transient_name = false, $image_sizes = array(), $upload_dir = '', $upload_url = '', $timestamp_for_update = false)
	{

		if (empty($this->db_id)) {
			return false;
		}

		$to_update = array(
			'json_data' => $this->encryption->encrypt(sbi_json_encode($this->instagram_api_data)),
		);

		if ($update_last_requested) {
			$to_update['last_requested'] = date('Y-m-d H:i:s');
		}

		if ($timestamp_for_update) {
			$to_update['top_time_stamp'] = $timestamp_for_update;
		}

		if ($transient_name) {
			$this->maybe_add_feed_id($transient_name);
		}

		if ($this->media_id === 'pending') {
			$this->resize_and_save_image($image_sizes, $upload_dir, $upload_url);
		} else {
			$this->update_sbi_instagram_posts($to_update);
		}

		return true;
	}

	/**
	 * If a record hasn't been made for this transient name/feed id,
	 * make a record
	 *
	 * @param string $feed_id
	 *
	 * @since 2.0/4.0
	 */
	private function maybe_add_feed_id($feed_id)
	{

		if (empty($this->instagram_post_id)) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . SBI_INSTAGRAM_FEEDS_POSTS;
		// the number is removed from the transient name for backwards compatibilty.
		$feed_id_array = explode('#', $feed_id);
		$feed_id = str_replace('+', '', $feed_id_array[0]);

		$feed_id_match = $wpdb->get_col($wpdb->prepare("SELECT feed_id FROM $table_name WHERE feed_id = %s AND instagram_id = %s", $feed_id, $this->instagram_post_id));

		if (!isset($feed_id_match[0])) {
			$entry_data = array(
				$this->db_id,
				"'" . esc_sql($this->instagram_post_id) . "'",
				"'" . esc_sql($feed_id) . "'",
			);
			$entry_string = implode(',', $entry_data);
			$wpdb->query("INSERT INTO $table_name (id,instagram_id,feed_id) VALUES ($entry_string);");
		}
	}

	/**
	 * Uses the post's data to get a relevant full size image url and resize it
	 *
	 * @param array  $image_sizes
	 * @param string $upload_dir
	 * @param string $upload_url
	 *
	 * @since 2.0/4.0
	 * @since 2.0/5.0 loop through assoc array (res setting => desired width of image) to
	 *                accommodate personal accounts and possible
	 *                custom sizes in the future
	 */
	public function resize_and_save_image($image_sizes, $upload_dir, $upload_url)
	{
		$options = sbi_get_database_settings();
		$image_format = isset($options['image_format']) ? $options['image_format'] : 'webp';

		$webp_supported = false;
		if ($image_format == 'webp') {
			$webp_supported = wp_image_editor_supports(array('mime_type' => 'image/webp'));
		}
		$extension = $webp_supported ? '.webp' : '.jpg';

		if (isset($this->instagram_api_data['id'])) {
			// A post whose last download failed waits out its backoff as pending,
			// however often the feed's JS asks for it.
			if (self::download_retry_waiting($this->instagram_post_id)) {
				return;
			}

			$image_source_set = SB_Instagram_Parse::get_media_src_set($this->instagram_api_data);
			$account_type = SB_Instagram_Parse::get_account_type($this->instagram_api_data);
			$image_sizes_to_make = isset($image_sizes[$account_type]) ? $image_sizes[$account_type] : array();
			// if it's a personal account or a weird url, the post id is used, otherwise the last part of the image url is used.
			if ($account_type === 'business') {
				$new_file_name = explode('?', SB_Instagram_Parse::get_media_url($this->instagram_api_data, 'lightbox'));
				if (strlen(basename($new_file_name[0], '.jpg')) > 10) {
					$new_file_name = basename($new_file_name[0], '.jpg');
				} else {
					$new_file_name = $this->instagram_api_data['id'];
				}
				$new_file_name = str_replace('.webp', '', $new_file_name);
			} else {
				$new_file_name = $this->instagram_api_data['id'];
			}

			// the process is considered a success if one image is successfully resized.
			$one_successful_image_resize = false;
			$download_failed = false;

			foreach ($image_sizes_to_make as $res_setting => $image_size) {
				if ($account_type === 'business') {
					$file_name = SB_Instagram_Parse::get_media_url($this->instagram_api_data, 'lightbox');
				} else {
					$file_name = isset($image_source_set[$image_size]) ? $image_source_set[$image_size] : SB_Instagram_Parse::get_media_url($this->instagram_api_data, 'lightbox');
				}
				if (strpos($file_name, 'placeholder') !== false) {
					$file_name = '';
				}
				if (!empty($file_name)) {
					$sizes = array(
						'height' => 1,
						'width' => 1,
					);

					$suffix = $res_setting;
					$this_image_file_name = $new_file_name . $suffix . $extension;

					$temp_file = '';
					$image_editor = wp_get_image_editor($file_name);

					// If there is an error then lets try a fallback approach
					if (is_wp_error($image_editor)) {
						// Gives us access to the download_url() and wp_handle_sideload() functions.
						require_once ABSPATH . 'wp-admin/includes/file.php';

						$timeout_seconds = 5;

						// Download file to temp dir.
						$temp_file = download_url($file_name, $timeout_seconds);

						// download_url() returns a WP_Error when the download fails (an expired or
						// removed media URL). wp_get_image_editor() throws a TypeError on one under
						// PHP 8, so keep the download error. Below, the post waits and is
						// retried, and is marked as an error after the last attempt.
						$image_editor = is_wp_error($temp_file) ? $temp_file : wp_get_image_editor($temp_file);
						if (is_wp_error($temp_file)) {
							$download_failed = true;
						}

						global $sb_instagram_posts_manager;
						$details = 'Using backup editor method.' . $file_name;
						$sb_instagram_posts_manager->add_error('image_editor', $details);
					}

					// not uncommon for the image editor to not work using it this way
					if (!is_wp_error($image_editor)) {
						$sizes = $image_editor->get_size();

						$image_editor->resize($image_size, null);

						// set_quality() must be called AFTER resize(): since WP 6.8 the core
						// resize() implementations internally invoke set_quality(null, $dims),
						// which would overwrite any quality set beforehand.
						$image_editor->set_quality(80);

						$full_file_name = trailingslashit($upload_dir) . $this_image_file_name;
						$mime_type = $webp_supported ? 'image/webp' : 'image/jpeg';

						$saved_image = $image_editor->save($full_file_name, $mime_type);

						if (is_wp_error($saved_image)) {
							global $sb_instagram_posts_manager;
							$details = __('Error saving edited image.', 'instagram-feed') . ' ' . $full_file_name;
							$sb_instagram_posts_manager->add_error('image_editor', $details);
						} else {
							$one_successful_image_resize = true;
						}
					} else {
						$message = __('Error editing image.', 'instagram-feed');
						if (isset($image_editor) && isset($image_editor->errors)) {
							foreach ($image_editor->errors as $key => $item) {
								$message .= ' ' . $key . ' - ' . $item[0] . ' |';
							}
							if (isset($image_editor) && isset($image_editor->error_data)) {
								$message .= ' ' . sbi_json_encode($image_editor->error_data) . ' |';
							}
						}

						global $sb_instagram_posts_manager;
						$sb_instagram_posts_manager->add_error('image_editor', $message);
					}

					// unlink() throws a TypeError on a WP_Error under PHP 8, even with @.
					if (is_string($temp_file) && $temp_file !== '') {
						@unlink($temp_file);
					}
				}
			}

			if ($one_successful_image_resize) {
				$aspect_ratio = round($sizes['width'] / $sizes['height'], 2);

				$this->update_sbi_instagram_posts(
					array(
						'media_id' => $new_file_name,
						'sizes' => maybe_serialize($image_sizes_to_make),
						'aspect_ratio' => $aspect_ratio,
						'images_done' => 1,
						'mime_type' => $saved_image['mime-type']
					)
				);

				$this->add_resized_image_to_obj_array('id', $new_file_name);
				self::forget_download_retry($this->instagram_post_id);
			} elseif (
				$download_failed
				&& self::record_download_failure($this->instagram_post_id) < self::DOWNLOAD_MAX_ATTEMPTS
			) {
				// A download can fail for a moment (a timeout, a CDN hiccup), so the
				// post stays pending and is tried again after the backoff.
				return;
			} else {
				self::forget_download_retry($this->instagram_post_id);
				// an error status means that image resizing won't be attempted again for this post
				$this->update_sbi_instagram_posts(
					array(
						'media_id' => 'error',
						'sizes' => maybe_serialize($image_sizes_to_make),
						'aspect_ratio' => 1,
						'images_done' => 1,
					)
				);
			}
		}
	}

	/**
	 * Whether a post is still inside the wait after a failed image download.
	 *
	 * @param string $instagram_post_id The Instagram post ID.
	 *
	 * @return bool
	 */
	public static function download_retry_waiting($instagram_post_id)
	{
		$retries = self::get_download_retries();
		if (!isset($retries[$instagram_post_id]['next'])) {
			return false;
		}

		return time() < (int)$retries[$instagram_post_id]['next'];
	}

	/**
	 * Count one more failed download for a post and start its wait.
	 *
	 * The caller marks the post as an error when the result reaches
	 * DOWNLOAD_MAX_ATTEMPTS. When the map is full and the post is new, the
	 * same write first drops stale entries (DOWNLOAD_RETRY_STALE_AFTER).
	 *
	 * @param string $instagram_post_id The Instagram post ID.
	 *
	 * @return int The number of failed downloads so far, this one included.
	 *             DOWNLOAD_MAX_ATTEMPTS for a new post when the map is still
	 *             full after that.
	 */
	public static function record_download_failure($instagram_post_id)
	{
		$retries = self::get_stored_download_retries();

		// A full map takes no new post. Evicting an entry to make room would
		// restart that post at attempt 1, so it might never reach the error state.
		// Only entries whose wait ended more than a day ago make room: their
		// posts have most likely left the feed. Pruning only here keeps every
		// count while there is room, on a site loaded less than once a day too.
		// An entry with no valid "next" is stale.
		if (!isset($retries[$instagram_post_id]) && count($retries) >= self::DOWNLOAD_RETRY_MAX_ENTRIES) {
			$stale_before = time() - self::DOWNLOAD_RETRY_STALE_AFTER;
			foreach ($retries as $post_id => $retry) {
				$next = isset($retry['next']) ? (int)$retry['next'] : 0;
				if ($next < $stale_before) {
					unset($retries[$post_id]);
				}
			}

			if (count($retries) >= self::DOWNLOAD_RETRY_MAX_ENTRIES) {
				return self::DOWNLOAD_MAX_ATTEMPTS;
			}
		}

		$attempts = 1;
		if (isset($retries[$instagram_post_id]['attempts'])) {
			$attempts = (int)$retries[$instagram_post_id]['attempts'] + 1;
		}

		if ($attempts < self::DOWNLOAD_MAX_ATTEMPTS) {
			$retries[$instagram_post_id] = array(
				'attempts' => $attempts,
				'next' => time() + self::DOWNLOAD_RETRY_WAIT,
			);
		} else {
			unset($retries[$instagram_post_id]);
		}

		update_option(self::DOWNLOAD_RETRY_OPTION, $retries, false);

		return $attempts;
	}

	/**
	 * Drop a post's retry state once its image is saved or marked as an error.
	 *
	 * @param string $instagram_post_id The Instagram post ID.
	 *
	 * @return void
	 */
	public static function forget_download_retry($instagram_post_id)
	{
		// Most saved posts have no entry. The copy cached for this request
		// answers for them without a database query. An entry that another
		// request adds after that read stays behind until a full map prunes
		// it as stale.
		$retries = self::get_download_retries();
		if (!isset($retries[$instagram_post_id])) {
			return;
		}

		$retries = self::get_stored_download_retries();
		if (!isset($retries[$instagram_post_id])) {
			return;
		}

		unset($retries[$instagram_post_id]);
		update_option(self::DOWNLOAD_RETRY_OPTION, $retries, false);
	}

	/**
	 * Retry state for failed image downloads, keyed by Instagram post id.
	 *
	 * This is the copy get_option() cached for this request. It is fine for
	 * reads. A write starts from get_stored_download_retries() instead.
	 * Entries that are not arrays are left out.
	 *
	 * @return array
	 */
	private static function get_download_retries()
	{
		$retries = get_option(self::DOWNLOAD_RETRY_OPTION, array());
		if (!is_array($retries)) {
			return array();
		}

		// Every entry this class writes is an array. Anything else came from an
		// outside write. Leaving it out here keeps the reads of a post's own
		// entry from throwing on it, and the next write drops it.
		return array_filter($retries, 'is_array');
	}

	/**
	 * Retry state as the database holds it now, for a write.
	 *
	 * Another request can change the option after this request first read it,
	 * for example during a 5-second download. A writer that wrote back the
	 * cached copy would drop that request's entries. So each writer reads the
	 * option again here, changes only its own post and writes at once.
	 *
	 * This narrows the window but does not close it. Two requests that both
	 * read before either writes still lose one change. That costs an extra
	 * download or leaves a stale entry, which a full map prunes later. It
	 * never shows a wrong image. Like get_download_retries(), it leaves out
	 * entries that are not arrays.
	 *
	 * @return array
	 */
	private static function get_stored_download_retries()
	{
		// The option is not autoloaded. get_option() caches it under its own key
		// in the 'options' group, or lists it in 'notoptions' when it was missing.
		wp_cache_delete(self::DOWNLOAD_RETRY_OPTION, 'options');
		$notoptions = wp_cache_get('notoptions', 'options');
		if (is_array($notoptions) && isset($notoptions[self::DOWNLOAD_RETRY_OPTION])) {
			unset($notoptions[self::DOWNLOAD_RETRY_OPTION]);
			wp_cache_set('notoptions', $notoptions, 'options');
		}

		return self::get_download_retries();
	}

	/**
	 * Updates columns that need to be updated in the posts types table.
	 * Called after images successfully resized and if any information
	 * needs to be updated.
	 *
	 * @param array $to_update assoc array of columns and values to update
	 *
	 * @since 2.0/4.0
	 */
	public function update_sbi_instagram_posts($to_update)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . SBI_INSTAGRAM_POSTS_TYPE;

		foreach ($to_update as $column => $value) {
			$query = $wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_name
					SET $column = %s
					WHERE id = %d;",
					$value,
					$this->db_id
				)
			);

			if ($query === false) {
				global $sb_instagram_posts_manager;
				$error = $wpdb->last_error;
				$query = $wpdb->last_query;

				$sb_instagram_posts_manager->add_error('storage', __('Error updating post.', 'instagram-feed') . ' ' . $error . '<br><code>' . $query . '</code>');
			}
		}
	}

	/**
	 * Record newly created images so they can be returned and used right away.
	 *
	 * Not used in version 2.0/5.0 but can be used to resize and use
	 * images "on the fly" when the feed is being displayed.
	 *
	 * @param string $key
	 * @param string $val
	 *
	 * @since 2.0/4.0
	 */
	public function add_resized_image_to_obj_array($key, $val)
	{
		$this->resized_image_array[$key] = $val;
	}

	/**
	 * Checks database for matching record for post and feed ID.
	 * There shouldn't be duplicate records
	 *
	 * @param string $transient_name
	 *
	 * @return bool
	 *
	 * @since 2.0/4.1
	 */
	public function exists_in_feeds_posts_table($transient_name)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . SBI_INSTAGRAM_FEEDS_POSTS;
		$feed_id_array = explode('#', $transient_name);
		$feed_id = $feed_id_array[0];
		$results = $wpdb->get_results($wpdb->prepare("SELECT feed_id FROM $table_name WHERE instagram_id = %s AND feed_id = %s LIMIT 1", $this->instagram_post_id, $feed_id), ARRAY_A);

		if (isset($results[0]['feed_id'])) {
			return true;
		}
		if (isset($this->instagram_api_data['term'])) {
			$results = $wpdb->get_results($wpdb->prepare("SELECT hashtag FROM $table_name WHERE instagram_id = %s AND hashtag = %s LIMIT 1", $this->instagram_post_id, strtolower(str_replace('#', '', $this->instagram_api_data['term']))), ARRAY_A);
			return isset($results[0]['hashtag']);
		}

		return false;
	}
}
