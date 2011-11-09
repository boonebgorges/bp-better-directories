jQuery(document).ready(function($) {
	$('#bpbd-filters input[type="checkbox"]').live('click', function(value){
		$('body div#content').mask('Loading...');
		$('div.loadmask-msg').css('top', '300px');
		bpbd_do_query();
	});
	
	$('#bpbd-filters input[type="text"]').live('keypress', function(e){
		var ebox = this;
		if ( e.keyCode == 13 ) {
			e.preventDefault();
		
			// Move the content of the textbox to a separate div, and to the hidden input
			var uval = $(ebox).val();
			var uvalclean = uval.replace(' ','_');
			
			// Create the new LI
			$(ebox).siblings('ul').append('<li id="bpbd-value-' + uvalclean + '"><span class="bpbd-remove"><a href="#">x</a></span> ' + uval + '</li>');
			
			// Bind the remove action to the 'x'
			$('#bpbd-value-' + uvalclean + ' span.bpbd-remove a').bind( 'click', function() { bpbd_remove_item(this); return false; } );
			
			// Delete the value from the box
			$(ebox).val('');
			
			// Stash in the hidden div
			var hidden = $(ebox).siblings('.bpbd-hidden-value');
			var curval = $(hidden).val();	
		
			if ( '' == curval ) {
				curval = [uval];
			} else {				
				curval += ',' + uval;		
			}
		
			$(hidden).val(curval);
						
			$('body div#content').mask('Loading...');
			$('div.loadmask-msg').css('top', '300px');
			bpbd_do_query();
		}
		
	});
	
	/* Removal 'x' on search terms */
	$('.bpbd-remove a').bind( 'click', function() { bpbd_remove_item(this); return false; } );
	
	/* 'Clear' links on individual criteria */
	$('.bpbd-clear-this a').bind('click', function() { bpbd_remove_this_crit(this,true); return false; } );
	
	/* 'Clear All' link for resetting all criteria */
	$('#bpbd-clear-all a').bind('click', function() {
		$('body div#content').mask('Loading...');
		$('div.loadmask-msg').css('top', '300px');
		
		$.each($('.bpbd-filter-crit'), function(k,v){
			var clearthis = $(v).find('.bpbd-clear-this a');
			bpbd_remove_this_crit(clearthis, false); 
		});
		
		/* Refresh */
		var object = 'members';
		bpbd_bp_filter_request( object, $.cookie('bp-' + object + '-filter'), $.cookie('bp-' + object + '-scope'), 'div.' + object, '', 1, $.cookie('bp-' + object + '-extras') );
					
		return false; 
	});
	
},(jQuery));

var jq = jQuery;

function bpbd_do_query() {
	// Get all the criteria
	var c = jQuery('#bpbd-filters li.bpbd-filter-crit');
	
	var args = {};

	// Look through the criteria
	jQuery(c).each(function(key,criterion){
		var critid = jQuery(criterion).attr('id');
		var critkey = critid.split('-').pop();
		var critinputs = jQuery('#' + critid + ' input');
		var critvals = [];
		var crittype = jQuery(criterion).attr('class');
		
		if ( false !== crittype.indexOf('bpbd-filter-crit-type-') ) {
			var ctype = crittype.split('bpbd-filter-crit-type-').pop(); 
		} else {
			var ctype = '';
		}

		if ( 'checkbox' == ctype ) {
			jQuery(critinputs).each( function(ckey, cval){
				if ( jQuery(cval).is(':checked') ) {
					critvals.push(jQuery(cval).attr('value'));
				}
			});
		} else if ( 'textbox' == ctype ) {
			jQuery(critinputs).each(function(ckey,cval){
				if ( 0 < jQuery(cval).attr('id').indexOf('filter-value-') ) {
					dval = jQuery(cval).val().split(',');
					jQuery(dval).each(function(fkey,fval){
						critvals.push(fval);
					});
				}
			});
		}
		
		if ( critvals.length >= 1 ) {
			args[critkey] = critvals;
		}		
	});
	
	jQuery.bpbd_cookie('bpbd-filters', bpbd_JSONstring.make(args), { path: '/' } );
	
	var object = 'members';
	bpbd_bp_filter_request( object, jq.cookie('bp-' + object + '-filter'), jq.cookie('bp-' + object + '-scope'), 'div.' + object, '', 1, jq.cookie('bp-' + object + '-extras') );
}

function bpbd_remove_item( item ){
	var j = jQuery;
	
	j('body div#content').mask('Loading...');
	j('div.loadmask-msg').css('top', '300px');
	
	var searchterm = j(item).parent().parent().attr('id').split('bpbd-value-').pop();
	
	/* Remove from search terms list */
	var parli = j(item).parents('.bpbd-filter-crit').attr('id');
	var hidd = j('#' + parli + ' .bpbd-hidden-value');
	var hidval = j(hidd).val().split(',');

	j.each(hidval,function(index, value){
		if ( searchterm == value ) {
			hidval.splice(index,1);
		}
	});
	
	j(hidd).val(hidval);
	
	/* Now to remove from the cookie */
	var thekey = (parli).split('bpbd-filter-crit-').pop();
	var thecookie = bpbd_JSONstring.toObject(j.cookie('bpbd-filters'));
	var curvals = thecookie[thekey];

	j(curvals).each(function(index, value){
		if ( searchterm == value ) {
			curvals.splice(index,1);
		}
	});
	thecookie[thekey] = curvals;
	j.bpbd_cookie('bpbd-filters', bpbd_JSONstring.make(thecookie), { path: '/' } );
	
	/* Remove the list item itself */
	j(item).parent().parent().remove();
	
	/* Refresh */
	var object = 'members';
	bpbd_bp_filter_request( object, jq.cookie('bp-' + object + '-filter'), jq.cookie('bp-' + object + '-scope'), 'div.' + object, '', 1, jq.cookie('bp-' + object + '-extras') );
	
	return false;				
}

function bpbd_remove_this_crit( item, dorefresh ) {
	var j = jQuery;
	
	if ( dorefresh ) {
		j('body div#content').mask('Loading...');
		j('div.loadmask-msg').css('top', '300px');
	}
	
	var paritem = j(item).parents('.bpbd-filter-crit');
	var thecookie = bpbd_JSONstring.toObject(j.cookie('bpbd-filters'));
	
	if(j(paritem).hasClass('bpbd-filter-crit-type-checkbox')){		
		/* Uncheck the items */
		j.each(j(paritem).find('li input[type="checkbox"]'), function(index,value){
			if(j(value).is(':checked')){
				j(value).attr('checked',false);
			}
		});
	} else {
		/* Textboxes */
		/* Clear the hidden value */
		j(paritem).find('.bpbd-hidden-value').val('');
		
		/* Clear markup */
		j(paritem).find('ul.bpbd-search-terms').html('');
	}
		
	/* Clear the cookie */
	var cookiekey = j(paritem).attr('id').split('bpbd-filter-crit-').pop();
	delete thecookie[cookiekey];
	j.bpbd_cookie('bpbd-filters', bpbd_JSONstring.make(thecookie), { path: '/' } );
	
	if ( dorefresh ) {
		/* Refresh */
		var object = 'members';
		bpbd_bp_filter_request( object, jq.cookie('bp-' + object + '-filter'), jq.cookie('bp-' + object + '-scope'), 'div.' + object, '', 1, jq.cookie('bp-' + object + '-extras') );
	}
	
	return false;
}

function bpbd_bp_filter_request( object, filter, scope, target, search_terms, page, extras ) {
	if ( 'activity' == object )
		return false;

	if ( jq.query.get('s') && !search_terms )
		search_terms = jq.query.get('s');

	if ( null == scope )
		scope = 'all';

	/* Save the settings we want to remain persistent to a cookie */
	jq.cookie( 'bp-' + object + '-scope', scope, {path: '/'} );
	jq.cookie( 'bp-' + object + '-filter', filter, {path: '/'} );
	jq.cookie( 'bp-' + object + '-extras', extras, {path: '/'} );

	/* Set the correct selected nav and filter */
	jq('div.item-list-tabs li').each( function() {
		jq(this).removeClass('selected');
	});
	jq('div.item-list-tabs li#' + object + '-' + scope + ', div.item-list-tabs#object-nav li.current').addClass('selected');
	jq('div.item-list-tabs select option[value="' + filter + '"]').prop( 'selected', true );

	if ( 'friends' == object )
		object = 'members';

	if ( bp_ajax_request )
		bp_ajax_request.abort();

	bp_ajax_request = jq.post( ajaxurl, {
		action: object + '_filter',
		'cookie': encodeURIComponent(document.cookie),
		'object': object,
		'filter': filter,
		'search_terms': search_terms,
		'scope': scope,
		'page': page,
		'extras': extras,
		'cache': false
	},
	function(response)
	{
		jq(target).fadeOut( 100, function() {
			jq(this).html(response);
			jq(this).fadeIn(100);
	 	});
		
		jq('body div#content').unmask();
	});
}

/**
 * jQuery Cookie plugin
 *
 * Prefixed for this plugin to prevent potential conflicts
 *
 * Copyright (c) 2010 Klaus Hartl (stilbuero.de)
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 *
 */
jQuery.bpbd_cookie = function (key, value, options) {

    // key and at least value given, set cookie...
    if (arguments.length > 1 && String(value) !== "[object Object]") {
        options = jQuery.extend({}, options);

        if (value === null || value === undefined) {
            options.expires = -1;
        }

        if (typeof options.expires === 'number') {
            var days = options.expires, t = options.expires = new Date();
            t.setDate(t.getDate() + days);
        }

        value = String(value);

        return (document.cookie = [
            encodeURIComponent(key), '=',
            options.raw ? value : encodeURIComponent(value),
            options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
            options.path ? '; path=' + options.path : '',
            options.domain ? '; domain=' + options.domain : '',
            options.secure ? '; secure' : ''
        ].join(''));
    }

    // key and possibly options given, get cookie...
    options = value || {};
    var result, decode = options.raw ? function (s) { return s; } : decodeURIComponent;
    return (result = new RegExp('(?:^|; )' + encodeURIComponent(key) + '=([^;]*)').exec(document.cookie)) ? decode(result[1]) : null;
};

/*
JSONstring v 1.02
copyright 2006-2010 Thomas Frank
(Small sanitizer added to the toObject-method, May 2008)
(Scrungus fix to some problems with quotes in strings added in July 2010)

This EULA grants you the following rights:

Installation and Use. You may install and use an unlimited number of copies of the SOFTWARE PRODUCT.

Reproduction and Distribution. You may reproduce and distribute an unlimited number of copies of the SOFTWARE PRODUCT either in whole or in part; each copy should include all copyright and trademark notices, and shall be accompanied by a copy of this EULA. Copies of the SOFTWARE PRODUCT may be distributed as a standalone product or included with your own product.

Commercial Use. You may sell for profit and freely distribute scripts and/or compiled scripts that were created with the SOFTWARE PRODUCT.

Based on Steve Yen's implementation:
http://trimpath.com/project/wiki/JsonLibrary

Sanitizer regExp:
Andrea Giammarchi 2007

*/

bpbd_JSONstring={
	compactOutput:false, 		
	includeProtos:false, 	
	includeFunctions: false,
	detectCirculars:true,
	restoreCirculars:true,
	make:function(arg,restore) {
		this.restore=restore;
		this.mem=[];this.pathMem=[];
		return this.toJsonStringArray(arg).join('');
	},
	toObject:function(x){
		if(!this.cleaner){
			try{this.cleaner=new RegExp('^("(\\\\.|[^"\\\\\\n\\r])*?"|[,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t])+?$')}
			catch(a){this.cleaner=/^(true|false|null|\[.*\]|\{.*\}|".*"|\d+|\d+\.\d+)$/}
		};
		if(!this.cleaner.test(x)){return {}};
		eval("this.myObj="+x);
		if(!this.restoreCirculars || !alert){return this.myObj};
		if(this.includeFunctions){
			var x=this.myObj;
			for(var i in x){if(typeof x[i]=="string" && !x[i].indexOf("JSONincludedFunc:")){
				x[i]=x[i].substring(17);
				eval("x[i]="+x[i])
			}}
		};
		this.restoreCode=[];
		this.make(this.myObj,true);
		var r=this.restoreCode.join(";")+";";
		eval('r=r.replace(/\\W([0-9]{1,})(\\W)/g,"[$1]$2").replace(/\\.\\;/g,";")');
		eval(r);
		return this.myObj
	},
	toJsonStringArray:function(arg, out) {
		if(!out){this.path=[]};
		out = out || [];
		var u; // undefined
		switch (typeof arg) {
		case 'object':
			this.lastObj=arg;
			if(this.detectCirculars){
				var m=this.mem; var n=this.pathMem;
				for(var i=0;i<m.length;i++){
					if(arg===m[i]){
						out.push('"JSONcircRef:'+n[i]+'"');return out
					}
				};
				m.push(arg); n.push(this.path.join("."));
			};
			if (arg) {
				if (arg.constructor == Array) {
					out.push('[');
					for (var i = 0; i < arg.length; ++i) {
						this.path.push(i);
						if (i > 0)
							out.push(',\n');
						this.toJsonStringArray(arg[i], out);
						this.path.pop();
					}
					out.push(']');
					return out;
				} else if (typeof arg.toString != 'undefined') {
					out.push('{');
					var first = true;
					for (var i in arg) {
						if(!this.includeProtos && arg[i]===arg.constructor.prototype[i]){continue};
						this.path.push(i);
						var curr = out.length; 
						if (!first)
							out.push(this.compactOutput?',':',\n');
						this.toJsonStringArray(i, out);
						out.push(':');                    
						this.toJsonStringArray(arg[i], out);
						if (out[out.length - 1] == u)
							out.splice(curr, out.length - curr);
						else
							first = false;
						this.path.pop();
					}
					out.push('}');
					return out;
				}
				return out;
			}
			out.push('null');
			return out;
		case 'unknown':
		case 'undefined':
		case 'function':
			if(!this.includeFunctions){out.push(u);return out};
			arg="JSONincludedFunc:"+arg;
			out.push('"');
			var a=['\\','\\\\','\n','\\n','\r','\\r','"','\\"'];arg+=""; 
			for(var i=0;i<8;i+=2){arg=arg.split(a[i]).join(a[i+1])};
			out.push(arg);
			out.push('"');
			return out;
		case 'string':
			if(this.restore && arg.indexOf("JSONcircRef:")==0){
				this.restoreCode.push('this.myObj.'+this.path.join(".")+"="+arg.split("JSONcircRef:").join("this.myObj."));
			};
			out.push('"');
			var a=['\n','\\n','\r','\\r','"','\\"'];
			arg+=""; for(var i=0;i<6;i+=2){arg=arg.split(a[i]).join(a[i+1])};
			out.push(arg);
			out.push('"');
			return out;
		default:
			out.push(String(arg));
			return out;
		}
	}
};
