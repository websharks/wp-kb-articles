<?php
namespace wp_kb_articles;

/**
 * @var plugin      $plugin Plugin class.
 * @var template    $template Template class.
 *
 * Other variables made available in this template file:
 *
 * @var \stdClass[] $tab_categories An array of categories; for tabs.
 * @var \stdClass[] $tags An array of all KB article tags.
 * @var \stdClass   $attr Parsed/normalized/validated shortcode attributes.
 * @var array       $attr_ Unparsed/raw shortcode attributes.
 * @var \WP_Query   $query WP Query class instance ready for iteration.
 * @var \stdClass   $pagination_vars Object containing pagination vars.
 *
 * -------------------------------------------------------------------
 * @note In addition to plugin-specific variables & functionality,
 *    you may also use any WordPress functions that you like.
 */
?>
<div class="<?php echo esc_attr(__NAMESPACE__.'-list'); ?>">

	<div class="-navigation">
		<?php if($tab_categories): ?>
			<div class="-tabs">
				<ul class="-list">
					<?php foreach($tab_categories as $_term): ?>
						<li>
							<a href="#" data-category="<?php echo esc_attr($_term->term_id); ?>"
								<?php if(in_array((integer)$_term->term_id, $attr->category, TRUE)): ?> class="-active"<?php endif; ?>
								><?php echo esc_html($_term->name); ?></a>
						</li>
					<?php endforeach; // End category iteration.
					unset($_term); // Housekeeping. ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if($tags): ?>
			<div class="-tags">
				<div class="-filter">
					<a href="#"><?php echo __('Filter by Tag', $plugin->text_domain); ?></a>
				</div>
				<div class="-overlay">
					<div class="-selected">
						<i class="fa fa-tags"></i>
						<strong><?php echo __('Tags Selected', $plugin->text_domain); ?>:</strong>
						<?php $_tags = ''; // Initialize.

						foreach($attr->tag as $_term_id) // Iterate tags in query.
							foreach($tags as $_term) if($_term_id === (integer)$_term->term_id)
							{
								$_tags .= ($_tags ? ', ' : '').esc_html($_term->name);
								break; // Break the inner iteration; we found this tag.
							}
						if(!$_tags) // There are no tags selected right now?
							$_tags = '<strong>'.__('None', $plugin->text_domain).'</strong>'.
							         ' '.__('(select some tags) and click `filter by tags`', $this->plugin->text_domain);
						echo $_tags; // Currently selected tags.

						unset($_term, $_tags); // Housekeeping. ?>
					</div>
					<ul class="-list">
						<?php foreach($tags as $_term): ?>
							<li><a href="#" data-tag="<?php echo esc_attr($_term->term_id); ?>"
									<?php if(in_array((integer)$_term->term_id, $attr->tag, TRUE)): ?> class="-active"<?php endif; ?>
									><?php echo esc_html($_term->name); ?></a></li>
						<?php endforeach; // End tag iteration.
						unset($_term); // Housekeeping. ?>
					</ul>
					<button type="button" class="-button">
						<?php echo __('Filter by Tags', $plugin->text_domain); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="-articles">
		<?php if($query->have_posts()): ?>
			<?php while($query->have_posts()): $query->the_post(); ?>
				<div class="-article">
					<?php $_tags = ''; // Initialize.
					if(($_tag_terms = get_the_terms(get_the_ID(), $plugin->post_type.'_tag'))):
						foreach($_tag_terms as $_term) // Iterate the tags that it has.
							$_tags .= ($_tags ? ', ' : ''). // Comma-delimited tags.
							          '<a href="#" data-click-tag="'.esc_attr($_term->term_id).'">'.esc_attr($_term->name).'</a>';
					endif; // End if article has tags.
					unset($_tag_terms, $_term); // Housekeeping.

					echo $template->snippet(
						'list-article.php', array(
						'tags'                   => $_tags,
						'comments_open'          => comments_open(),
						'comments_number'        => get_comments_number(),

						'[permalink]'            => esc_attr(get_permalink()),
						'[title]'                => esc_html(get_the_title()),

						'[popularity]'           => esc_html($plugin->utils_post->get_popularity(get_the_ID())),
						'[author_posts_url]'     => esc_attr(get_author_posts_url(get_the_author_meta('ID'))),
						'[author]'               => esc_html(get_the_author()),

						'[tags]'                 => $_tags, // Contains raw HTML markup.

						'[comments_number_text]' => esc_html(get_comments_number_text()),
						'[date]'                 => esc_html(get_the_date()),
					));
					unset($_tags); // Housekeeping. ?>
				</div>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		<?php else: ?>
			<p><?php echo __('No articles matching search criteria.', $plugin->text_domain); ?></p>
		<?php endif; ?>
	</div>

	<?php if($pagination_vars->total_pages > 1): ?>
		<div class="-pagination">
			<div class="-pages">
				<ul class="-list">
					<?php if($pagination_vars->current_page > 1): // Create a previous page link? ?>
						<li class="-prev -prev-next">
							<a href="#" data-click-page="<?php echo esc_attr($pagination_vars->current_page - 1); ?>">&laquo; <?php echo __('prev', $plugin->text_domain); ?></a>
						</li>
					<?php else: // Not possible; this is the first page. ?>
						<li class="-prev -prev-next">
							<a href="#" class="-disabled">&laquo; <?php echo __('prev', $plugin->text_domain); ?></a>
						</li>
					<?php endif; ?>

					<?php // Individual page links now.
					$_max_page_links           = 10; // Max individual page links to show on each page.
					$_page_links_start_at_page = // This is a mildly complex calculation that we can do w/ help from the plugin class.
						$plugin->utils_db->pagination_links_start_page($pagination_vars->current_page, $pagination_vars->total_pages, $_max_page_links);

					for($_i = 1, $_page = $_page_links_start_at_page; $_i <= $_max_page_links && $_page <= $pagination_vars->total_pages; $_i++ && $_page++): ?>
						<li>
							<a href="#" data-click-page="<?php echo esc_attr($_page); ?>"
								<?php if($_page === $pagination_vars->current_page): ?> class="-active"<?php endif; ?>
								><?php echo esc_html($_page); ?></a>
						</li>
					<?php endfor; // End the iteration of page links.
					unset($_max_page_links, $_page_links_start_at_page, $_page, $_i); // Housekeeping. ?>

					<?php if($pagination_vars->current_page < $pagination_vars->total_pages): // Create a next page link? ?>
						<li class="-next -prev-next">
							<a href="#" data-click-page="<?php echo esc_attr($pagination_vars->current_page + 1); ?>"><?php echo __('next', $plugin->text_domain); ?> &raquo;</a>
						</li>
					<?php else: // Not possible; this is the last page. ?>
						<li class="-next -prev-next">
							<a href="#" class="-disabled"><?php echo __('next', $plugin->text_domain); ?> &raquo;</a>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>

	<div class="-hidden">
		<div class="-attr-raw" data-attr="<?php echo esc_attr($plugin->utils_enc->encrypt(serialize($attr_))); ?>"></div>
		<div class="-attr-page" data-attr="<?php echo esc_attr($attr->page); ?>"></div>
		<div class="-attr-orderby" data-attr="<?php echo esc_attr(implode(',', $attr->orderbys)); ?>"></div>
		<div class="-attr-author" data-attr="<?php echo esc_attr(implode(',', $attr->author)); ?>"></div>
		<div class="-attr-category" data-attr="<?php echo esc_attr(implode(',', $attr->category)); ?>"></div>
		<div class="-attr-tag" data-attr="<?php echo esc_attr(implode(',', $attr->tag)); ?>"></div>
		<div class="-attr-q" data-attr="<?php echo esc_attr($attr->q); ?>"></div>
	</div>

</div>