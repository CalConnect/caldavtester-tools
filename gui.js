/**
 * GUI for CalDAVTester
 *
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/Apache-2.0 Apache License, Version 2.0
 *
 * @link https://github.com/CalConnect/caldavtester
 * @link https://github.com/CalConnect/caldavtester-tools
 */

jQuery().ready(function()
{
	var commit_url = jQuery('table.results').attr('data-commit-url');
	var revision = jQuery('input#revision');

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
		// allow to click on scripts-names to fetch them
		output.find('td.script').wrapInner(function()
		{
			return '<a href="'+location.href+'?script='+encodeURIComponent(this.textContent)+'" target="_blank">';
		});
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

				var all = _ev.shiftKey ? '&all=1' : '';
				var rev = revision.val() ? '&revision='+encodeURIComponent(revision.val()) : '';
				jQuery.ajax(location.href+'?run='+encodeURIComponent(tr[0].id)+all+rev).done(function(_data)
				{
					update_result(_data, output, spinner, tr, true);	// dont install click handler again
				});
			});
		}
		// update updated time in tr above
		var etag = output.find('table.details').attr('data-etag');
		tr.find('td.updated').text(etag.substr(0, 16));
		tr.find('td.time').text((etag.match(/\d+\.\d{2}/)||[''])[0]);
		// update color, percent, success and failed in tr above
		var failed=0, success=0, time=0.0;
		output.find('tr.red,tr.yellow,tr.green').each(function(_key, _tr) {
			var revisions = jQuery(_tr).find('td.revision');	// last-success, failed, first-failed
			console.log(revisions);
			if (revisions.eq(1).text())
			{
				++failed;
			}
			else if (revisions.text())
			{
				++success;
			}
		});
		tr.find('td.success').text(success);
		tr.find('td.failed').text(failed);
		var percent = (success+failed) ? (100.0 * success / (success+failed)).toFixed(1) : '';
		tr.find('td.percent').text(percent);
		tr.removeClass('red').removeClass('yellow').removeClass('green');
		if (percent) tr.addClass(percent >= 100.0 ? 'green' : (percent < 50.0 ? 'red' : 'yellow'));
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
					var output = jQuery('<td colspan="8" class="output">&nbsp;</td>').appendTo(details);
					var rev = revision.val() ? '&revision='+encodeURIComponent(revision.val()) : '';
					tr.after(details);
					jQuery.ajax(location.href+'?result='+encodeURIComponent(tr[0].id)+rev).done(function(_data)
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
		// expand/collapse a notes-line
		jQuery('table.results').on('click', 'td.noNotes,td.haveNotes', function(_ev)
		{
			var tr = jQuery(this.parentElement);
			var notes = tr.nextAll('tr.notes').first();
			if (!notes.is(':visible'))
			{
				notes.show();
			}
			else
			{
				notes.hide();
			}
		});
		// update notes
		jQuery('table.results').on('click', 'td.updateNotes > button', function(_ev)
		{
			var tr = jQuery(this).parents('tr.notes');
			var notes = tr.find('textarea.notes').val();
			jQuery.ajax({
				url: location.href+'?update='+encodeURIComponent(tr[0].id),
				method: 'POST',
				data: {
					notes: notes
				}
			}).done(function(_data)
			{
				if (_data) alert(_data);
				// update haveNotes indicator
				tr.prevAll('tr.green,tr.yellow,tr.red').first().find('td.noNotes,td.haveNotes')
					.toggleClass('noNotes', notes === '')
					.toggleClass('haveNotes', notes !== '');
			});
		});
		jQuery('td.expand').attr('title', 'expand');
		// allow to click on scripts-names to fetch them
		jQuery('td.script').wrapInner(function()
		{
			return '<a href="'+location.href+'?script='+encodeURIComponent(this.textContent)+'" target="_blank">';
		});
	}
	prepare_page();

	// simple accordion for editing serverinfo document
	function activate_accordion(id)
	{
		var active=false;
		jQuery('table.serverinfo tr').each(function(i, node)
		{
			if (node.id)
			{
				active = node.id === id && !jQuery(node).hasClass('collapse');
				jQuery(node).toggleClass('collapse', active);
			}
			else if (active)
			{
				jQuery(node).show('fast', 'swing');
			}
			else
			{
				jQuery(node).hide();
			}
		});
	}
	jQuery('table.serverinfo tr.accordionHeader').on('click', function()
	{
		activate_accordion(this.id);
	});
	activate_accordion('access');

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
