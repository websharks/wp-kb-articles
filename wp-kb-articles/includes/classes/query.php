<?php
/**
 * Query Handler
 *
 * @since 150410 Improving searches.
 * @copyright WebSharks, Inc. <http://www.websharks-inc.com>
 * @license GNU General Public License, version 3
 */
namespace wp_kb_articles // Root namespace.
{
	if(!defined('WPINC')) // MUST have WordPress.
		exit('Do NOT access this file directly.');

	if(!class_exists('\\'.__NAMESPACE__.'\\query'))
	{
		/**
		 * Query Handler
		 *
		 * @since 150410 Improving searches.
		 */
		class query extends abs_base
		{
			/**
			 * Query arguments.
			 *
			 * @var \stdClass Arguments.
			 *
			 * @since 150410 Improving searches.
			 */
			protected $args;

			/**
			 * Recent category ID.
			 *
			 * @since 150607 Adding recent.
			 *
			 * @var integer Recent category ID.
			 */
			protected $recent = 0;

			/**
			 * A recent view?
			 *
			 * @since 150607 Adding recent.
			 *
			 * @var boolean A recent view?
			 */
			protected $is_recent = FALSE;

			/**
			 * Trending category ID.
			 *
			 * @since 150410 Improving searches.
			 *
			 * @var integer Trending category ID.
			 */
			protected $trending = 0;

			/**
			 * A trending view?
			 *
			 * @since 150410 Improving searches.
			 *
			 * @var boolean A trending view?
			 */
			protected $is_trending = FALSE;

			/**
			 * Popular category ID.
			 *
			 * @since 150410 Improving searches.
			 *
			 * @var integer Popular category ID.
			 */
			protected $popular = 0;

			/**
			 * A popular view?
			 *
			 * @since 150410 Improving searches.
			 *
			 * @var boolean A popular view?
			 */
			protected $is_popular = FALSE;

			/**
			 * An array of all results.
			 *
			 * @since 150410 Improving searches.
			 *
			 * @var array Results array.
			 */
			protected $results = array();

			/**
			 * Pagination properties.
			 *
			 * @since 150410 Improving searches.
			 *
			 * @var \stdClass Pagination properties.
			 */
			protected $pagination;

			/**
			 * WordPress query class instance.
			 *
			 * @since 150410 Improving searches.
			 *
			 * @var \WP_Query WordPress query class.
			 */
			protected $wp_query;

			/**
			 * Default query args.
			 *
			 * @since 150410 Improving searches.
			 *
			 * @var array Default query args.
			 */
			public static $default_args = array(
				'page'           => 1, // Page number.
				'per_page'       => 25, // Cannot exceed max limit.

				'orderby'        => array(
					'relevance'     => 'DESC', // By search relevance.
					'popularity'    => 'DESC', // By article popularity/hearts.
					'visits'        => 'DESC', // By total unique visitors.
					'comment_count' => 'DESC', // By article comment count.
					'views'         => 'DESC', // By total hits; i.e., page views.
					'date'          => 'DESC', // By article date.
				),
				'author'          => array(), // Satisfy all. Comma-delimited slugs/IDs; or an array of slugs/IDs.
				'category'        => array(), // Satisfy all. Comma-delimited slugs/IDs; or an array of slugs/IDs.
				'category_no_rtp' => array(), // For internal use only. Categories without trending or popular IDs.
				'tag'             => array(), // Satisfy all. Comma-delimited slugs/IDs; or an array of slugs/IDs.
				'q'               => '', // Search query. Correleates with `snippet` and `relevance`.

				'recent_days'    => 36500, // Number of days to use in recent calculation.
				'trending_days'  => 7, // Number of days to use in trending calculation.
				'snippet_size'   => 100, // Total characters in snippet; for searches only.

				'strings'        => array(), // For internal use only; args converted to strings.
			);

			/**
			 * Class constructor.
			 *
			 * @since 150410 Improving searches.
			 *
			 * @param array $args Query arguments.
			 */
			public function __construct(array $args = array())
			{
				parent::__construct();

				# Establish arguments.

				if(isset($args['orderbys']) && !isset($args['orderby']))
					$args['orderby'] = $args['orderbys'];

				if(isset($args['authors']) && !isset($args['author']))
					$args['author'] = $args['authors'];

				if(isset($args['categories']) && !isset($args['category']))
					$args['category'] = $args['categories'];

				if(isset($args['tags']) && !isset($args['tag']))
					$args['tag'] = $args['tags'];

				$args       = array_merge(static::$default_args, $args);
				$args       = array_intersect_key($args, static::$default_args);
				$this->args = (object)$args; // Convert to object now.

				# Collect recent/trending/popular category IDs; if they exist on this site.

				if(($_term_info = term_exists('recent', $this->plugin->post_type.'_category')))
					$this->recent = (integer)$_term_info['term_id'];

				if(($_term_info = term_exists('trending', $this->plugin->post_type.'_category')))
					$this->trending = (integer)$_term_info['term_id'];

				if(($_term_info = term_exists('popular', $this->plugin->post_type.'_category')))
					$this->popular = (integer)$_term_info['term_id'];

				unset($_term_info); // Housekeeping.

				# Resolve, typecast, and validate all arguments.

				$this->args->page     = max(1, (integer)$this->args->page);
				$this->args->per_page = max(1, (integer)$this->args->per_page);
				$this->args->per_page = min((integer)apply_filters(__CLASS__.'_upper_max_limit', 1000), $this->args->per_page);

				$this->args->orderby = !is_array($this->args->orderby) ? preg_split('/,+/', $this->args->orderby, NULL, PREG_SPLIT_NO_EMPTY) : $this->args->orderby;
				$this->args->orderby = $this->plugin->utils_array->remove_emptys($this->plugin->utils_string->trim_deep($this->args->orderby));
				$_orderby            = $this->args->orderby; // Temporary container; for iteration below.
				$this->args->orderby = array(); // Reset; convert to associative array.
				foreach($_orderby as $_key => $_order) // Validate each orderby.
				{
					$_order = (string)$_order; // Force string.

					if(!is_string($_key) && strpos($_order, ':', 1) !== FALSE)
						list($_key, $_order) = explode(':', $_order, 2);
					$_order = strtoupper($_order); // e.g. `ASC`, `DESC`.

					if(!in_array($_key, array_keys(static::$default_args['orderby']), TRUE))
						continue; // Invalid syntax; i.e., invalid orderby column key.

					if(!in_array($_order, array('ASC', 'DESC'), TRUE))
						continue; // Invalid syntax; i.e., invalid order.

					$this->args->orderby[$_key] = $_order;
				}
				unset($_orderby, $_key, $_order); // Housekeeping.
				if(!$this->args->orderby) $this->args->orderby = static::$default_args['orderby'];

				$this->args->author = !is_array($this->args->author) ? preg_split('/,+/', (string)$this->args->author, NULL, PREG_SPLIT_NO_EMPTY) : $this->args->author;
				$this->args->author = $this->plugin->utils_array->remove_emptys($this->plugin->utils_string->trim_deep($this->args->author));
				foreach($this->args->author as $_key => &$_author) // Validate each author.
				{
					if(is_numeric($_author)) // Convert username to ID.
					{
						if(!($_author = (integer)$_author))
							unset($this->args->author[$_key]);
						continue; // All done here.
					}
					$_author = \WP_User::get_data_by('login', $_author);
					if(!$_author || !($_author = $_author->ID))
						unset($this->args->author[$_key]);
				}
				unset($_key, $_author); // Housekeeping.

				$this->args->category = !is_array($this->args->category) ? preg_split('/,+/', (string)$this->args->category, NULL, PREG_SPLIT_NO_EMPTY) : $this->args->category;
				$this->args->category = $this->plugin->utils_array->remove_emptys($this->plugin->utils_string->trim_deep($this->args->category));
				foreach($this->args->category as $_key => &$_category) // Validate each category.
				{
					if(is_numeric($_category))
					{
						if(!($_category = (integer)$_category))
							unset($this->args->category[$_key]);
						continue; // All done here.
					}
					$_term = get_term_by('slug', $_category, $this->plugin->post_type.'_category');
					if(!$_term || !($_category = (integer)$_term->term_id))
						unset($this->args->category[$_key]);
				}
				unset($_key, $_category, $_term); // Housekeeping.

				$this->args->tag = !is_array($this->args->tag) ? preg_split('/,+/', (string)$this->args->tag, NULL, PREG_SPLIT_NO_EMPTY) : $this->args->tag;
				$this->args->tag = $this->plugin->utils_array->remove_emptys($this->plugin->utils_string->trim_deep($this->args->tag));
				foreach($this->args->tag as $_key => &$_tag) // Validate each tag.
				{
					if(is_numeric($_tag))
					{
						if(!($_tag = (integer)$_tag))
							unset($this->args->tag[$_key]);
						continue; // All done here.
					}
					$_term = get_term_by('slug', $_tag, $this->plugin->post_type.'_tag');
					if(!$_term || !($_tag = (integer)$_term->term_id))
						unset($this->args->tag[$_key]);
				}
				unset($_key, $_tag, $_term); // Housekeeping.

				$this->args->q              = trim((string)$this->args->q);
				$this->args->recent_days    = max(1, (integer)$this->args->recent_days);
				$this->args->trending_days  = max(1, (integer)$this->args->trending_days);
				$this->args->snippet_size   = max(20, (integer)$this->args->snippet_size);

				$this->args->category_no_rtp = array_diff($this->args->category, array($this->recent, $this->trending, $this->popular));
				$this->is_recent            = $this->recent && in_array($this->recent, $this->args->category, TRUE);
				$this->is_trending          = !$this->is_recent && $this->trending && in_array($this->trending, $this->args->category, TRUE);
				$this->is_popular           = !$this->is_recent && !$this->is_trending && $this->popular && in_array($this->popular, $this->args->category, TRUE);

				if($this->is_recent) $this->args->orderby = array(
					'relevance'     => 'DESC', // By search relevance.
					'date'          => 'DESC', // By article date.
					'visits'        => 'DESC', // By total unique visitors.
					'popularity'    => 'DESC', // By article popularity/hearts.
					'comment_count' => 'DESC', // By article comment count.
					'views'         => 'DESC', // By total hits; i.e., page views.
				);
				else if($this->is_trending) $this->args->orderby = array(
					'relevance'     => 'DESC', // By search relevance.
					'visits'        => 'DESC', // By total unique visitors.
					'popularity'    => 'DESC', // By article popularity/hearts.
					'comment_count' => 'DESC', // By article comment count.
					'views'         => 'DESC', // By total hits; i.e., page views.
					'date'          => 'DESC', // By article date.
				);
				else if($this->is_popular) $this->args->orderby = array(
					'relevance'     => 'DESC', // By search relevance.
					'popularity'    => 'DESC', // By article popularity/hearts.
					'visits'        => 'DESC', // By total unique visitors.
					'comment_count' => 'DESC', // By article comment count.
					'views'         => 'DESC', // By total hits; i.e., page views.
					'date'          => 'DESC', // By article date.
				);
				# Convert all arguments into strings; needed by some callers.

				$_arg_strings = array(); // Initialize.
				foreach($this->args as $_key => $_value)
				{
					if($_key === 'strings') continue; // Skip.

					if(is_array($_value)) // Implode all arrays.
					{
						if($_key === 'orderby') // Associative.
						{
							$_arg_strings[$_key] = '';
							foreach($_value as $__key => $__value)
								$_arg_strings[$_key] .= ','.$__key.':'.$__value;
							$_arg_strings[$_key] = trim($_arg_strings[$_key], ',');
						}
						else $_arg_strings[$_key] = implode(',', $_value);
					}
					else $_arg_strings[$_key] = (string)$_value;
				}
				$this->args->strings = $_arg_strings; // Special property.
				unset($_arg_strings, $_key, $_value, $__key, $__value); // Housekeeping.

				# Initialize query-related props.

				$this->results    = array();
				$this->pagination = (object)array(
					'total_results' => 0, // Set after query.
					'total_pages'   => 0, // Set after query.
					'per_page'      => $this->args->per_page,
					'current_page'  => $this->args->page,
				);
				$this->wp_query   = NULL; // Set after query.

				# Set read-only overload properties.

				$this->___overload->args       = &$this->args;
				$this->___overload->results    = &$this->results;
				$this->___overload->pagination = &$this->pagination;
				$this->___overload->wp_query   = &$this->wp_query;

				# Perform the DB query.

				$this->do_query(); // Perform the DB query.
			}

			/**
			 * Performs DB query; fills results.
			 *
			 * @since 150410 Improving searches.
			 */
			protected function do_query()
			{
				$snippet_before_after_size = ceil($this->args->snippet_size / 2);

				$sql      = // Complex DB query that uses a custom fulltext-enabled index table.

					"SELECT SQL_CALC_FOUND_ROWS `index`.`post_id` AS `post_id`,".
					" SUM(`stats`.`visits`) AS `visits`,".
					" SUM(`stats`.`views`) AS `views`,".
					" MAX(`stats`.`ymd_time`) AS `last_view_time`,".
					" CAST(`popularity`.`meta_value` AS UNSIGNED) AS `hearts`,".
					" `posts`.`post_date_gmt`". // For possible HAVING clause.

					($this->args->q // Performing a search query?
						? ", (". // Break these down to give each column different weights.
						  " (1.5 * (MATCH(`index`.`post_title`) AGAINST('".esc_sql($this->args->q)."' IN BOOLEAN MODE))) +".
						  " (1.0 * (MATCH(`index`.`post_tags`) AGAINST('".esc_sql($this->args->q)."' IN BOOLEAN MODE))) +".
						  " (0.5 * (MATCH(`index`.`post_content`) AGAINST('".esc_sql($this->args->q)."' IN BOOLEAN MODE)))".
						  ") AS `relevance`,". // For ordering below.

						  " SUBSTRING(`index`.`post_content`,". // Collect a snippet of the content based on the configured before/after length.
						  "   IF(LOCATE('".esc_sql($this->args->q)."', `index`.`post_content`) > ".$snippet_before_after_size.", LOCATE('".esc_sql($this->args->q)."', `index`.`post_content`) - ".$snippet_before_after_size.", 1),".
						  "   ".$snippet_before_after_size." + LENGTH('".esc_sql($this->args->q)."') + ".$snippet_before_after_size.
						  " ) AS `snippet`"
						: ", 0 AS `relevance`, '' AS `snippet`").
					" FROM ". // Which tables are we selecting/joining on?

					" `".esc_sql($this->plugin->utils_db->prefix().'index')."` AS `index`".
					" INNER JOIN `".esc_sql($this->plugin->utils_db->wp->posts)."` AS `posts` ON `index`.`post_id` = `posts`.`ID`".
					($this->args->category_no_rtp // Do we need the term relationships table for category(s)? Note: this is not necessary for tag filters.
						? " INNER JOIN `".esc_sql($this->plugin->utils_db->wp->term_relationships)."` AS `term_relationships` ON `index`.`post_id` = `term_relationships`.`object_id`" : '').
					" LEFT JOIN `".esc_sql($this->plugin->utils_db->prefix().'stats')."` AS `stats` ON `index`.`post_id` = `stats`.`post_id`".
					" LEFT JOIN `".esc_sql($this->plugin->utils_db->wp->postmeta)."` AS `popularity` ON `index`.`post_id` = `popularity`.`post_id` AND `popularity`.`meta_key` = '".esc_sql(__NAMESPACE__.'_popularity')."'".

					" WHERE 1=1". // Required matches and other query filters.

					" AND `posts`.`post_type` = '".esc_sql($this->plugin->post_type)."'".
					" AND `posts`.`post_status` = '".esc_sql('publish')."'".

					($this->args->author // Filter by author(s)?
						? " AND `posts`.`post_author` IN('".implode("','", $this->args->author)."')"
						: '').
					($this->args->category_no_rtp // Filter by category(s)? This is an OR/any check.
						? " AND `term_relationships`.`term_taxonomy_id` IN('".implode("','", $this->args->category_no_rtp)."')"
						: '').
					($this->args->tag // Filter by tag(s)? This is an AND/all check; i.e., has all of the tags?
						? " AND (SELECT COUNT(1) FROM `".esc_sql($this->plugin->utils_db->wp->term_relationships)."` WHERE `term_taxonomy_id` IN('".implode("','", $this->args->tag)."') AND `object_id` = `index`.`post_id`) = ".count($this->args->tag)
						: '').
					($this->args->q // Performing a search query?
						? " AND MATCH(`index`.`post_title`, `index`.`post_tags`, `index`.`post_content`) AGAINST('".esc_sql($this->args->q)."' IN BOOLEAN MODE)"
						: '').
					" GROUP BY `index`.`post_id`". // Required for SUM ordering below.

					($this->args->q ? " HAVING `relevance` > 0" : ''). // Relevant results only.

					($this->is_recent // Filter down to recent (i.e., recently published) articles?
						? ($this->args->q ? " AND " : " HAVING "). // Second condition or first?
						  "`posts`.`post_date_gmt` >= '".esc_sql(date('Y-m-d H:i:s', strtotime('-'.$this->args->recent_days.' days')))."'"
						: '').
					($this->is_trending // Filter down to trending (i.e., recently viewed) articles?
						? ($this->args->q ? " AND " : " HAVING "). // Second condition or first?
						  "`last_view_time` >= '".esc_sql(strtotime('-'.$this->args->trending_days.' days'))."'"
						: '');
				$_orderby = ''; // Initialize list of ordered orderby items.
				// This results in an ordered list of orderby items; as configured by query args.
				foreach($this->args->orderby as $_key => $_value) switch($_key)
				{
					case 'relevance': // by search relevance.
						if($this->args->q) // only if applicable.
							$_orderby .= " `relevance` ".esc_sql($_value).",";
						break; // break switch handler.

					case 'popularity': // by article popularity/hearts.
						$_orderby .= " `hearts` ".esc_sql($_value).",";
						break; // break switch handler.

					case 'visits': // by total unique visitors.
						$_orderby .= " `visits` ".esc_sql($_value).",";
						break; // break switch handler.

					case 'comment_count': // by article comment count.
						$_orderby .= " `posts`.`comment_count` ".esc_sql($_value).",";
						break; // break switch handler.

					case 'views': // by total hits; i.e., page views.
						$_orderby .= " `views` ".esc_sql($_value).",";
						break; // break switch handler.

					case 'date': // by article date.
						$_orderby .= " `posts`.`post_date` ".esc_sql($_value).",";
						break; // break switch handler.
				}
				$sql .= " ORDER BY ".$this->plugin->utils_string->trim($_orderby, '', ',');
				unset($_orderby, $_key, $_value); // Housekeeping.

				$sql .= " LIMIT ".(($this->args->page - 1) * $this->args->per_page).", ".$this->args->per_page;

				$this->results                   = $this->plugin->utils_db->wp->get_results($sql, OBJECT_K);
				$this->results                   = $this->plugin->utils_db->typify_deep($this->results);
				$this->pagination->total_results = (integer)$this->plugin->utils_db->wp->get_var("SELECT FOUND_ROWS()");
				$this->pagination->total_pages   = ceil($this->pagination->total_results / $this->args->per_page);

				foreach($this->results as $_result) // Sanitize snippets.
					// @TODO Replace this hackety sanitizer and other MySQL functions with regex.
					//    Currently, there is no REGEXP_REPLACE function in MySQL. Research needed.
					//    Ideally, regex would be applied during indexing to sanitize the snippet.
					//    We do some of this already, but only what it possible given MySQL limitations.
					$_result->snippet = preg_replace('/\s+/', ' ', strip_shortcodes($_result->snippet));
				unset($_result); // Housekeeping.

				$this->do_wp_query(); // Now do a WP query with the post IDs we need for this page.
			}

			/**
			 * Performs WP DB query; fills results.
			 *
			 * @since 150410 Improving searches.
			 */
			protected function do_wp_query()
			{
				$args           = array(
					'nopaging'            => TRUE,
					'ignore_sticky_posts' => TRUE,
					'suppress_filters'    => TRUE,
					'no_found_rows'       => TRUE,
					'orderby'             => 'post__in',
					'post__in'            => $this->results ? array_keys($this->results) : array(0),
					// ↑ Don't let an empty array slide through. See: <http://jas.xyz/1EmDjvm>
					'post_type'           => $this->plugin->post_type,
				);
				$this->wp_query = new \WP_Query($args);
			}
		}
	}
}
