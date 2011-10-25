jQuery(document).ready(function($) {
	$('#bpbd-filters input[type="checkbox"]').live('click', function(value){
		var onoff = $(this).is(':checked');
		get_all_filters();
	});
	

},(jQuery));

function get_all_filters() {
	var f = jQuery('#bpbd-filters input');
	var g = [];
	jQuery(f).each(function(key,i){
		if ( jQuery(i).is(':checked') ) {
			g.push(jQuery(i).attr('value'));
		}
	});	
}