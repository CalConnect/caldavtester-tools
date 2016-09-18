/**
 * Analyse and display logs of CalDAVTester
 *
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 *
 * @link https://www.calendarserver.org/CalDAVTester.html
 * @link https://github.com/CalConnect/caldavtester-tools
 */

jQuery().ready(function()
{
	var commit_url = jQuery('table.results').attr('data-commit-url');

	function update_result(_data, output, spinner, tr, no_click)
	{
		output.html(_data);
		//tr[0].scrollIntoView();
		// link revisions to eg. Github
		if (commit_url)
		{
			output.find('td.revision').wrapInner(function()
			{
				return '<a href="'+commit_url+this.textContent+'" target="_blank">';
			});
			output.find('td.expand').attr('title', 'expand');
			output.find('td.collapse').attr('title', 'collapse');
			output.find('th.expandAll').attr('title', 'expand all');
		}
		spinner.removeClass('spinner');
		spinner.addClass('run')
			.attr('title', 'rerun script');

		if (!no_click)
		{
			spinner.click(function(_ev)
			{
				spinner.removeClass('run')
					.attr('title', '')
					.addClass('spinner');

				jQuery.ajax(location.href+'?run='+encodeURIComponent(tr[0].id)).done(function(_data)
				{
					update_result(_data, output, spinner, tr, true);	// dont install click handler again
				});
			});
		}
		// update updated time in tr above
		var etag = output.find('table.details').attr('data-etag');
		tr.find('td.updated').text(etag.substr(0, 16));
	}

	function prepare_page()
	{
		// expand/collapse a details-line, which get loaded via ajax if not already included
		jQuery('table.results').on('click', 'td.expand,td.collapse', function(_ev)
		{
			var tr = jQuery(this.parentElement);
			var details = tr.nextAll('tr').first();
			if (jQuery(this).hasClass('expand'))
			{
				if (!details.hasClass('details'))
				{
					var details = jQuery('<tr class="details">');
					var spinner = jQuery('<td class="spinner">').appendTo(details);
					var output = jQuery('<td colspan="6" class="output">').appendTo(details);
					tr.after(details);
					jQuery.ajax(location.href+'?result='+encodeURIComponent(tr[0].id)).done(function(_data)
					{
						update_result(_data, output, spinner, tr);
					});
				}
				else
				{
					details.show();
				}
			}
			else
			{
				details.hide();
			}
			jQuery(this)
				.toggleClass('expand')
				.toggleClass('collapse')
				.attr('title', jQuery(this).hasClass('expand') ? 'expand' : 'collapse');
		});
		// expand/collapse all tests of a script
		jQuery('table.results').on('click', 'th.expandAll,th.collapseAll', function(_ev)
		{
			var table = jQuery(this).parents('table').first();
			if (jQuery(this).hasClass('expandAll'))
			{
				table.find('tr > td.expand')
					.removeClass('expand')
					.addClass('collapse');
				table.find('tr.details')
					.show();
			}
			else
			{
				table.find('tr > td.collapse')
					.removeClass('collapse')
					.addClass('expand');
				table.find('tr.details')
					.hide();
			}
			jQuery(this)
				.toggleClass('expandAll')
				.toggleClass('collapseAll')
				.attr('title', jQuery(this).hasClass('expandAll') ? 'expand all' : 'collapse all');
		});
		jQuery('td.expand').attr('title', 'expand');
		// allow to fetch scripts
		jQuery('td.script').wrapInner(function()
		{
			return '<a href="'+location.href+'?script='+encodeURIComponent(this.textContent)+'" target="_blank">';
		});
	}
	prepare_page();

	/* poll server for updated results
	var update = window.setInterval(function()
	{
		var etag = jQuery('table.results').attr('data-etag');
		var xhr = jQuery.ajax({
			url: location.href,
			headers: { "If-None-Match": '"'+etag+'"' }
		}).done(function(_data)
		{
			if (_data)
			{
				var expanded = [];
				jQuery('table.results td.collapse').each(function()
				{
					var tr = jQuery(this).parent('tr').first();
					expanded.push(tr.attr('id'));
				});
				jQuery('body').html(_data.match(/<body>([\s\S]+)<\/body>/)[1]);
				prepare_page();
				for(var i=0; i < expanded.length; ++i)
				{
					jQuery(document.getElementById(expanded[i])).find('td.expand').click();
				}
			}
		});
	}, 60000);*/
});
