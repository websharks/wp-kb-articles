<?php
namespace wp_kb_articles;
/**
 * @var plugin   $plugin Plugin class.
 * @var template $template Template class.
 *
 * -------------------------------------------------------------------
 * @note In addition to plugin-specific variables & functionality,
 *    you may also use any WordPress functions that you like.
 */
?>
<script type="text/javascript">
	(function($) // WP KB Articles.
	{
		'use strict'; // Strict standards.

		var plugin = {},
			$window = $(window),
			$document = $(document);

		plugin.onReady = function()
		{
			var namespace = '<?php echo esc_js(__NAMESPACE__); ?>',
				namespaceSlug = '<?php echo esc_js($plugin->slug); ?>',
				qvPrefix = '<?php echo esc_js($plugin->qv_prefix); ?>',
				vars = {
					pluginUrl   : '<?php echo esc_js(rtrim($plugin->utils_url->to('/'), '/')); ?>',
					ajaxEndpoint: '<?php echo esc_js(home_url('/')); ?>'
				},
				i18n = {
					tagsSelected    : '<?php echo esc_js(__('Tags Selected', $plugin->text_domain)); ?>',
					selectedTagsNone: '<?php echo esc_js(__('None', $plugin->text_domain)); ?>',
					selectSomeTags  : '<?php echo esc_js(__('(select some tags) and click `filter by tags`', $plugin->text_domain)); ?>'
				},
				$list = $('.' + namespace + '-list'),

				$navigationTabs = $list.find('> .-navigation > .-tabs'),

				$navigationTabsList = $navigationTabs.find('> .-list'),
				$navigationTabsListItems = $navigationTabsList.find('> li'),
				$navigationTabsListItemAnchors = $navigationTabsListItems.find('> a'),

				$navigationTags = $list.find('> .-navigation > .-tags'),

				$navigationSearch = $list.find('> .-navigation > .-search'),
				$navigationSearchForm = $navigationSearch.find('> form'),
				$navigationSearchFormQ = $navigationSearchForm.find('> .-q'),
				$navigationSearchFormButton = $navigationSearchForm.find('> .-button'),

				$navigationTagsFilter = $navigationTags.find('> .-filter'),
				$navigationTagsFilterAnchor = $navigationTagsFilter.find('> a'),

				$navigationTagsOverlay = $navigationTags.find('> .-overlay'),
				$navigationTagsOverlaySelected = $navigationTagsOverlay.find('> .-selected'),
				$navigationTagsOverlayList = $navigationTagsOverlay.find('> .-list'),
				$navigationTagsOverlayListItems = $navigationTagsOverlayList.find('> li'),
				$navigationTagsOverlayListItemAnchors = $navigationTagsOverlayListItems.find('> a'),
				$navigationTagsOverlayButton = $navigationTagsOverlay.find(' > .-button'),

				$clickPageAnchors = $list.find('a[data-click-page]'),
				$clickOrderbyAnchors = $list.find('a[data-click-orderby]'),
				$clickAuthorAnchors = $list.find('a[data-click-author]'),
				$clickCategoryAnchors = $list.find('a[data-click-category]'),
				$clickTagAnchors = $list.find('a[data-click-tag]'),
				$clickQAnchors = $list.find('a[data-click-q]'),

				$attrRaw = $list.find('> .-hidden > .-attr-raw'),
				$attrPage = $list.find('> .-hidden > .-attr-page'),
				$attrOrderby = $list.find('> .-hidden > .-attr-orderby'),
				$attrAuthor = $list.find('> .-hidden > .-attr-author'),
				$attrCategory = $list.find('> .-hidden > .-attr-category'),
				$attrTag = $list.find('> .-hidden > .-attr-tag'),
				$attrQ = $list.find('> .-hidden > .-attr-q');

			var reload = function(qvs)
			{
				if($navigationTagsFilterAnchor.hasClass('-active')) // Close list of tags?
					$navigationTagsFilterAnchor.removeClass('-active'), $navigationTagsOverlay.fadeOut({duration: 100});

				var url, attrRaw = $attrRaw.data('attr'),
					requestAttrs = {}, _prop;

				requestAttrs['page'] = 1; // From the beginning.
				requestAttrs['orderby'] = $attrOrderby.data('attr');
				requestAttrs['author'] = $attrAuthor.data('attr');
				requestAttrs['category'] = activeCategories();
				requestAttrs['tag'] = activeTags();
				requestAttrs['q'] = $attrQ.data('attr');

				if(qvs) // Alter request?
				{
					$.extend(requestAttrs, qvs);

					if(qvs.author) for(_prop in requestAttrs)
						if(_prop !== 'author' && requestAttrs.hasOwnProperty(_prop))
							requestAttrs[_prop] = '';

					if(qvs.category) for(_prop in requestAttrs)
						if(_prop !== 'category' && requestAttrs.hasOwnProperty(_prop))
							requestAttrs[_prop] = '';

					if(qvs.tag) for(_prop in requestAttrs)
						if(_prop !== 'tag' && requestAttrs.hasOwnProperty(_prop))
							requestAttrs[_prop] = '';

					if(qvs.q) for(_prop in requestAttrs)
						if(_prop !== 'q' && requestAttrs.hasOwnProperty(_prop))
							requestAttrs[_prop] = '';
				}
				url = vars.ajaxEndpoint;
				url += url.indexOf('?') === -1 ? '?' : '&';
				url += encodeURIComponent(namespace + '[sc_list_via_ajax]') + '=' + encodeURIComponent(attrRaw);

				for(_prop in requestAttrs)
					if(requestAttrs.hasOwnProperty(_prop))
						url += '&' + encodeURIComponent(qvPrefix + _prop) + '=' + encodeURIComponent(requestAttrs[_prop]);

				$list.css({opacity: 0.5}), $.get(url, function(data)
				{
					$list.replaceWith(data);
					plugin.onReady();
				});
			};
			var activeCategories = function()
			{
				var activeCategories = '';

				$navigationTabsListItemAnchors
					.each(function()
					      {
						      var $this = $(this);
						      if($this.hasClass('-active'))
							      activeCategories += (activeCategories ? ',' : '') + $this.data('category');
					      });
				return activeCategories;
			};
			var activeTags = function()
			{
				var activeTags = '';

				$navigationTagsOverlayListItemAnchors
					.each(function()
					      {
						      var $this = $(this);
						      if($this.hasClass('-active'))
							      activeTags += (activeTags ? ',' : '') + $this.data('tag');
					      });
				return activeTags;
			};
			$navigationTabsListItemAnchors.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				var $this = $(this);

				$navigationTabsListItemAnchors.removeClass('-active'),
					$this.addClass('-active');

				reload();
			});
			$navigationTagsFilterAnchor.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				var $this = $(this);

				if($this.hasClass('-active'))
				{
					$this.removeClass('-active');
					$navigationTagsOverlay.fadeOut({duration: 100});
				}
				else // Show it now.
				{
					$this.addClass('-active');
					$navigationTagsOverlay.fadeIn({duration: 100});
				}
			});
			$navigationTagsOverlayListItemAnchors.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				var $this = $(this),
					selected = '<i class="fa fa-tags"></i>' +
					           ' <strong>' + i18n.tagsSelected + ':</strong>',
					selectedTags = ''; // Initialize.

				$this.toggleClass('-active');

				$navigationTagsOverlayListItemAnchors
					.each(function()
					      {
						      var $this = $(this);
						      if($this.hasClass('-active'))
							      selectedTags += (selectedTags ? ', ' : '') + $this.text();
					      });
				if(!selectedTags) // No tags selected currently?
					selectedTags = '<strong>' + i18n.selectedTagsNone + '</strong> ' + i18n.selectSomeTags;

				$navigationTagsOverlaySelected.html(selected + ' ' + selectedTags);
			});
			$navigationTagsOverlayButton.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				reload();
			});
			$navigationSearchForm.on('submit', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();
			});
			$navigationSearchFormQ.on('keydown', function(e)
			{
				if(e.which !== 13)
					return; // Not applicable.

				e.preventDefault();
				e.stopImmediatePropagation();

				var $this = $(this);

				reload({q: $.trim($this.val())});
			});
			$navigationSearchFormButton.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				var $this = $(this);

				reload({q: $.trim($navigationSearchFormQ.val())});
			});
			$clickPageAnchors.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				reload({page: $(this).data('clickPage')});
			});
			$clickOrderbyAnchors.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				reload({orderby: $(this).data('clickOrderby')});
			});
			$clickAuthorAnchors.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				reload({author: $(this).data('clickAuthor')});
			});
			$clickCategoryAnchors.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				reload({category: $(this).data('clickCategory')});
			});
			$clickTagAnchors.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				reload({tag: $(this).data('clickTag')});
			});
			$clickQAnchors.on('click', function(e)
			{
				e.preventDefault();
				e.stopImmediatePropagation();

				reload({q: $(this).data('clickQ')});
			});
		};
		$document.ready(plugin.onReady);
	})(jQuery);
</script>