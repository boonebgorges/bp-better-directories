jQuery(document).ready(function($) {
	$('#bpbd-filters input[type="checkbox"]').live('click', function(value){
		do_query();
	});
	

},(jQuery));

function do_query() {
	// Get all the criteria
	var c = jQuery('#bpbd-filters li.bpbd-filter-crit');
	
	var args = {
		action: 'bpbd_filter'
	};

	// Look through the criteria
	jQuery(c).each(function(key,criterion){
		var critid = jQuery(criterion).attr('id');
		var critkey = critid.split('-').pop();
		var critinputs = jQuery('#' + critid + ' input');
		var critvals = [];

		jQuery(critinputs).each( function(ckey, cval){
			if ( jQuery(cval).is(':checked') ) {
				critvals.push(jQuery(cval).attr('value'));
			}
		});
		
		if ( critvals.length >= 1 ) {
			args[critkey] = critvals;
		}		
	});
	
	jQuery.post(ajaxurl,args, function(response){
		var object = 'members';
		bp_filter_request( object, jq.cookie('bp-' + object + '-filter'), jq.cookie('bp-' + object + '-scope'), 'div.' + object, '', 1, jq.cookie('bp-' + object + '-extras') );
	});

}