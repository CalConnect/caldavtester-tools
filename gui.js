/**
 * Analyse and display logs of CalDAVTester
 *
 * @author Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 *
 * @link https://www.calendarserver.org/CalDAVTester.html
 * @link https://github.com/CalConnect/caldavtester-tools
 */

jQuery().ready(function()
{
	jQuery('td.expand,td.collapse').click(function(_ev)
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
				jQuery.ajax(location.href+'?script='+tr[0].id).done(function(_data)
				{
					output.text(_data);
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
});