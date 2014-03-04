/**
* This javascript is based on the code by Ben Doherty @ Oomph, Inc. (http://thinkoomph.com/ben-doherty) and
* is licensed under GPLv2 or later
*
* 	Copyright © 2012 Oomph, Inc. <http://oomphinc.com>
*
* 	This program is free software: you can redistribute it and/or modify
* 	it under the terms of the GNU General Public License as published by
* 	the Free Software Foundation, either version 3 of the License, or
* 	(at your option) any later version.
*
* 	This program is distributed in the hope that it will be useful,
* 	but WITHOUT ANY WARRANTY; without even the implied warranty of
* 	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* 	GNU General Public License for more details.
*
* 	You should have received a copy of the GNU General Public License
* 	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

var go_copylayout = {};

(function( $ ) {
	"use strict";

	go_copylayout.init = function() {
		go_copylayout.$active_widgets = $('#widgets-right');

		go_copylayout.$active_widgets.on( 'click', '.clone-widget', go_copylayout.clone );

		go_copylayout.bind();
	};

	go_copylayout.bind = function() {
		go_copylayout.$active_widgets.off( 'DOMSubtreeModified', go_copylayout.bind );
		$('.widgets-sortables .widget-title-action:not(.go-copylayout-cloneable)')
			.prepend('<a href="javascript:void(0);" class="clone-widget" title="Clone this Widget">+</a>')
			.addClass('go-copylayout-cloneable');
		go_copylayout.$active_widgets.on( 'DOMSubtreeModified', go_copylayout.bind );
	};

	go_copylayout.clone = function( e ) {
		e.stopPropagation();
		e.preventDefault();

		var $original = $(this).closest('.widget');
		var $title      = $original.find('.widget-title h4').clone();

		if ( ! confirm( 'Are you sure you wish to clone this "' + $title.get(0).childNodes[0].nodeValue + '" widget?' ) ) {
			return;
		}//end if

		var $new      = $original.clone();

		var id_base      = $new.find('input[name = "id_base"]').val();
		var number       = $new.find('input[name = "widget_number"]').val();
		var multi_number = $new.find('input[name = "multi_number"]').val();

		var highest      = 0;

		$('input.widget-id[value|="' + id_base + '"]').each( function() {
			var match = this.value.match( /-(\d+)$/ );

			if ( match ) {
				var widget_id = parseInt( match[1], 10 );

				highest = widget_id > highest ? widget_id : highest;
			}//end if
		});

		var new_number = highest + 1;

		$new.find('.widget-content').find('input,select,textarea').each(function() {
			if ( $(this).attr('name') ) {
				$(this).attr('name', $(this).attr('name').replace( number, new_number ) );
			}//end if
		});

		// assign a unique id to this widget:
		highest = 0;
		$('.widget').each(function() {
			var match = this.id.match( /^widget-(\d+)/ );

			if ( match ) {
				var widget_id = parseInt( match[1], 10 );

				highest = widget_id > highest ? widget_id : highest;
			}//end if
		});

		var new_id = highest + 1;

		// Figure out the value of add_new from the source widget:
		var add = $('#widget-list .id_base[value="' + id_base + '"]').siblings('.add_new').val();
		$new[0].id = 'widget-' + new_id + '_' + id_base + '-' + new_number;
		$new.find('input.widget-id').val( id_base + '-' + new_number );
		$new.find('input.widget_number').val( new_number );
		$original.after( $new );

		// Not exactly sure what multi_number is used for.
		$new.find('.multi_number').val( new_number );

		wpWidgets.save($new, 0, 0, 1);
	};
})( jQuery );

jQuery( function( $ ) {
	go_copylayout.init();
});
