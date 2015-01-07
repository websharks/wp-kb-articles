<?php
/**
 * GitHub API Class
 *
 * @since 150107 First documented version.
 * @copyright WebSharks, Inc. <http://www.websharks-inc.com>
 * @license GNU General Public License, version 3
 */
namespace wp_kb_articles // Root namespace.
{
	if(!defined('WPINC')) // MUST have WordPress.
		exit('Do NOT access this file directly: '.basename(__FILE__));

	if(!class_exists('\\'.__NAMESPACE__.'\\github_api'))
	{
		/**
		 * GitHub API Class
		 *
		 * @since 150107 First documented version.
		 */
		class github_api extends abs_base
		{

			/**
			 * Repo owner; e.g. `https://github.com/[owner]`.
			 *
			 * @since 141111 First documented version.
			 *
			 * @var string Repo owner; e.g. `https://github.com/[owner]`.
			 */
			protected $owner;

			/**
			 * Repo name; e.g. `https://github.com/[owner]/[repo]`.
			 *
			 * @since 141111 First documented version.
			 *
			 * @var string Repo name; e.g. `https://github.com/[owner]/[repo]`.
			 */
			protected $repo;

			/**
			 * Repo owner; e.g. `https://github.com/[owner]/[repo]/[branch]`.
			 *
			 * @since 141111 First documented version.
			 *
			 * @var string Repo owner; e.g. `https://github.com/[owner]/[repo]/[branch]`.
			 */
			protected $branch;

			/**
			 * API key; e.g. `Authorization: token [api_key]`.
			 *
			 * @since 141111 First documented version.
			 *
			 * @var string API key; e.g. `Authorization: token [api_key]`.
			 */
			protected $api_key;

			/**
			 * GitHub username; e.g. `https://[username]@github.com/`.
			 *
			 * @since 141111 First documented version.
			 *
			 * @var string GitHub username; e.g. `https://[username]@github.com/`.
			 */
			protected $username;

			/**
			 * GitHub password or API key; e.g. `https://[username]:[password]@github.com/`.
			 *
			 * @since 141111 First documented version.
			 *
			 * @var string GitHub password or API key; e.g. `https://[username]:[password]@github.com/`.
			 */
			protected $password;

			/**
			 * Class constructor.
			 *
			 * @since 141111 First documented version.
			 *
			 * @param array $args Array of arguments specific to the GitHub integration.
			 */
			public function __construct(array $args)
			{
				parent::__construct();

				$default_args = array(
					'owner'    => '',
					'repo'     => '',

					'branch'   => 'HEAD',

					'username' => '',
					'password' => '',
					'api_key'  => '',
				);
				$args         = array_merge($default_args, $args);
				$args         = array_intersect_key($args, $default_args);

				$this->owner = trim(strtolower((string)$args['owner']));
				$this->repo  = trim(strtolower((string)$args['repo']));

				$this->branch = trim((string)$args['branch']);

				$this->username = trim(strtolower((string)$args['username']));
				$this->password = trim((string)$args['password']);
				$this->api_key  = trim((string)$args['api_key']);
			}

			/* === Public Methods === */

			/**
			 * Retrieves an array of data for all `.MD` files within a repo.
			 *
			 * @since 141111 First documented version.
			 *
			 * @param bool $get_body If TRUE, this function will retrieve body contents for each `.md` file in the request.
			 *
			 * @return array|false
			 */
			public function retrieve_articles($get_body = FALSE)
			{
				$tree  = $this->retrieve_tree();
				$posts = array();

				if(!$tree)
					return FALSE; // Error.

				foreach($tree['tree'] as $blob)
				{
					if($blob['type'] !== 'blob')
						continue;

					if(!preg_match('/\.md$/i', $blob['path']))
						continue;

					$post = array(
						'headers' => array(),
						'body'    => '',
						'sha'     => $blob['sha'],
						'url'     => $blob['url'],
						'path'    => $blob['path']
					);
					if($get_body)
					{
						$body = $this->retrieve_body($post['sha']);

						if(!$body) // TODO error handling.
							return FALSE;

						$post = array_merge_recursive($post, $this->parse_article($body));
					}
					$posts[$post['path']] = $post;
				}
				return $posts;
			}

			/**
			 * Retrieves an associative array of information on a particular article, including the body.
			 *
			 * @since 141111 First documented version.
			 *
			 * @param string $a SHA1 key or path to file.
			 *
			 * @return array|boolean Array with the following elements, else `FALSE` on failure.
			 *
			 *    - `sha` SHA1 of the current body content data.
			 *    - `headers` An associative array of all YAML headers.
			 *    - `body` The body part of the article after YAML headers were parsed.
			 */
			public function retrieve_article($a)
			{
				if(!($body = $this->retrieve_body($a)))
					return FALSE; // Error.

				$post = array('sha' => sha1($body));

				return array_merge($post, $this->parse_article($body));
			}

			/* === Base GitHub Retrieval === */

			/**
			 * Wrapper function for retrieve_blob and retrieve_file based on `$a`.
			 *
			 * @since 141111 First documented version.
			 *
			 * @param string $a SHA1 key or path to file.
			 *
			 * @return string|boolean String body from GitHub, else `FALSE` on error.
			 */
			protected function retrieve_body($a)
			{
				if(($is_sha = (boolean)preg_match('/^[0-9a-f]{40}$/i', $a)))
				{
					if(!($blob = $this->retrieve_blob($a)))
						return FALSE; // Error.

					if($blob['encoding'] === 'base64')
						return base64_decode($blob['content']);
					return $blob['content'];
				}
				return $this->retrieve_file($a);
			}

			/**
			 * Retrieves list of files (as in, directory list) recursively from GitHub repo.
			 *
			 * @since 141111 First documented version.
			 *
			 * @return array|boolean Array of files from GitHub, else `FALSE` on error.
			 */
			protected function retrieve_tree()
			{
				$url      = 'api.github.com/repos/%1$s/%2$s/git/trees/%3$s?recursive=1';
				$url      = sprintf($url, $this->owner, $this->repo, $this->branch);
				$response = $this->get_response($url);

				return $response ? json_decode($response['body'], TRUE) : FALSE;
			}

			/**
			 * Retrieves UTF-8 encoded file from GitHub via SHA1 key.
			 *
			 * @since 141111 First documented version.
			 *
			 * @param string $sha SHA1 value to be retrieved from the GitHub repo.
			 *
			 * @return string|boolean String body from GitHub, else `FALSE` on error.
			 */
			protected function retrieve_blob($sha)
			{
				$url      = 'api.github.com/repos/%1$s/%2$s/git/blobs/%3$s';
				$url      = sprintf($url, $this->owner, $this->repo, $sha);
				$response = $this->get_response($url);

				return $response ? json_decode($response['body'], TRUE) : FALSE;
			}

			/**
			 * Retrieves a UTF-8 encoded raw file from GitHub via path.
			 *
			 * @since 141111 First documented version.
			 *
			 * @param string $path The path to the file to be retrieved.
			 *
			 * @return string|boolean String body from GitHub, else `FALSE` on error.
			 */
			protected function retrieve_file($path)
			{
				$url      = 'raw.githubusercontent.com/%1$s/%2$s/%3$s/%4$s';
				$url      = sprintf($url, $this->owner, $this->repo, $this->branch, $path);
				$response = $this->get_response($url);

				return $response ? $response['body'] : FALSE;
			}

			/**
			 * Parses a KB article w/ possible YAML front matter.
			 *
			 * @since 141111 First documented version.
			 *
			 * @param string $article Input article content to parse.
			 *
			 * @return array An array with two elements.
			 *
			 *    - `headers` An associative array of all YAML headers.
			 *    - `body` The body part of the article after YAML headers were parsed.
			 */
			protected function parse_article($article)
			{
				$parts = array(
					'headers' => array(),
					'body'    => '',
				);
				// Normalize line breaks. Use "\n" for all line breaks.
				$article = str_replace(array("\r\n", "\r"), "\n", $article);
				$article = trim($article);

				if(strpos($article, '---'."\n") !== 0)
				{
					$parts['body'] = $article;
					return $parts;
				}
				$article_parts = preg_split('/^\-{3}$/m', $article, 3);

				// If the article does NOT have three parts, it contains no YAML front matter.
				if(count($article_parts) !== 3)
				{
					$parts['body'] = $article;
					return $parts;
				}
				list(, $article_headers_part, $article_body_part) = $article_parts;

				foreach(explode("\n", trim($article_headers_part)) as $_line)
				{
					if(!($_line = trim($_line)))
						continue; // Skip over empty lines; i.e. with whitespace only.

					if(strpos($_line, ':', 1) !== FALSE)
					{
						list($_name, $_value) = explode(':', $_line, 2);
						$parts['headers'][strtolower(trim($_name))] = trim($_value);
					}
				}
				unset($_line, $_name, $_value); // Housekeeping.

				$parts['body'] = trim($article_body_part);

				return $parts;
			}

			/**
			 * Universal GitHub HTTP request method.
			 *
			 * @since 141111 First documented version.
			 *
			 * @param string $url The URL to request.
			 * @param array  $args An associative array of arguments that can be used to overwrite the defaults used by the function.
			 *
			 * @return array|boolean An array with the following elements; else `FALSE` on error.
			 *
			 *    - `request` = Result from `wp_remote_request()` call.
			 *    - `body` = Result from `wp_remote_retrieve_body()` call.
			 *    - `headers` = Result from `wp_remote_retrieve_headers()` call.
			 *    - `response_code` = Result from `wp_remote_retrieve_response_code()` call.
			 */
			protected function get_response($url, array $args = array())
			{
				$default_args = array(
					'headers'    => array(),
					'user-agent' => apply_filters(__METHOD__.'_user_agent', $this->plugin->name)
				);
				$args         = array_merge($default_args, $args);
				$args         = array_intersect_key($args, $default_args);

				if($this->api_key) // If Authorization done via GitHub API key.
					$args['headers'][] = 'Authorization: token '.$this->api_key;

				$user_pass_prefix = ''; // Initialize.
				if(isset($this->username[0], $this->password[0]))
					$user_pass_prefix = $this->username.':'.$this->password.'@';
				$url = 'https://'.$user_pass_prefix.$url;

				if(is_wp_error($request = wp_remote_request($url, $args)))
					return FALSE; // Error.

				$body          = wp_remote_retrieve_body($request);
				$headers       = wp_remote_retrieve_headers($request);
				$response_code = wp_remote_retrieve_response_code($request);

				if($response_code !== 302 && $response_code !== 200)
					return FALSE; // Error.

				return compact('request', 'body', 'headers', 'response_code');
			}
		}
	}
}