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
				var output = jQuery('<td colspan="5" class="output">').appendTo(details);
				tr.after(details);
				jQuery.ajax(location.href+'?script='+encodeURIComponent(tr[0].id)).done(function(_data)
				{
					output.html(_data);
					// link revisions to eg. Github
					if (commit_url)
					{
						output.find('td.revision').wrapInner(function()
						{
							return '<a href="'+commit_url+this.textContent+'" target="_blank">';
						});
					}
					spinner.removeClass('spinner');
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
		jQuery(this).toggleClass('expand')
			.toggleClass('collapse');
	});
	// allow to fetch scripts
	jQuery('td.script').wrapInner(function()
	{
		return '<a href="'+location.href+'?fetch='+encodeURIComponent(this.textContent)+'" target="_blank">';
	});
});