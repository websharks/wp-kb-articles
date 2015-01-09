<?php
/**
 * Shortcode for Articles List
 *
 * @since 150107 First documented version.
 * @copyright WebSharks, Inc. <http://www.websharks-inc.com>
 * @license GNU General Public License, version 3
 */
namespace wp_kb_articles // Root namespace.
{
	if(!defined('WPINC')) // MUST have WordPress.
		exit('Do NOT access this file directly: '.basename(__FILE__));

	if(!class_exists('\\'.__NAMESPACE__.'\\sc_articles_list'))
	{
		/**
		 * Shortcode for Articles List
		 *
		 * @since 150107 First documented version.
		 */
		class sc_articles_list extends abs_base
		{
			/**
			 * Shortcode attributes.
			 *
			 * @since 141111 First documented version.
			 *
			 * @var \stdClass Shortcode attributes.
			 */
			protected $attr;

			/**
			 * Shortcode content string.
			 *
			 * @since 141111 First documented version.
			 *
			 * @var string Shortcode content string.
			 */
			protected $content;

			/**
			 * Class constructor.
			 *
			 * @since 141111 First documented version.
			 *
			 * @param array  $attr Shortcode attributes.
			 * @param string $content Shortcode content string.
			 */
			public function __construct(array $attr, $content = '')
			{
				parent::__construct();

				$default_attr = array(
					'max_limit' => '25',
				);
				$attr         = array_merge($default_attr, $attr);
				$attr         = array_intersect_key($attr, $default_attr);

				$this->attr    = (object)$attr;
				$this->content = (string)$content;

				foreach($this->attr as $_prop => &$_value)
					if(in_array($_prop, array('max_limit'), TRUE))
						$_value = (integer)trim($_value);
					else $_value = trim((string)$_value);
				unset($_prop, $_value); // Housekeeping.
			}

			/**
			 * Shortcode parser.
			 *
			 * @since 141111 First documented version.
			 *
			 * @return string Parsed shortcode; i.e. HTML markup.
			 */
			public function parse()
			{
				$template_vars = get_defined_vars();
				$template      = new template('site/articles/list.php');

				return $template->parse($template_vars);
			}
		}
	}
}