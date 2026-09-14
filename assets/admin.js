/* global jQuery, wp */
/**
 * Schemagic admin: tabs, business type search, hours, media pickers, live preview.
 */
( function ( $ ) {
	'use strict';

	var settings = window.schemagicAdmin || {};
	var i18n = settings.i18n || {};
	var activateTab = function () {};

	/** Tell the live preview something changed. */
	function changed() {
		$( document ).trigger( 'schemagic:changed' );
	}

	/* ------------------------------------------------------------------ Tabs */

	function initTabs() {
		var $editor = $( '.schemagic-editor' );

		if ( ! $editor.length ) {
			return;
		}

		var $tabs = $editor.find( '.schemagic-tab' );
		$editor.addClass( 'has-tabs' );

		activateTab = function ( id, focus ) {
			var $tab = $tabs.filter( '[data-schemagic-tab="' + id + '"]' );

			if ( ! $tab.length || $tab.prop( 'hidden' ) ) {
				$tab = $tabs.not( '[hidden]' ).first();
				id = $tab.data( 'schemagicTab' );
			}

			$tabs.removeClass( 'is-active' ).attr( { 'aria-selected': 'false', tabindex: '-1' } );
			$tab.addClass( 'is-active' ).attr( { 'aria-selected': 'true', tabindex: '0' } );
			$editor.find( '.schemagic-panel' ).removeClass( 'is-active' );
			$( '#schemagic-panel-' + id ).addClass( 'is-active' );

			if ( focus ) {
				$tab.trigger( 'focus' );
			}

			try {
				window.sessionStorage.setItem( 'schemagicTab', id );
			} catch ( e ) {}
		};

		$tabs.on( 'click', function () {
			activateTab( $( this ).data( 'schemagicTab' ) );
		} );

		// Arrow keys move between tabs, as in the ARIA tabs pattern.
		$tabs.on( 'keydown', function ( event ) {
			var $visible = $tabs.not( '[hidden]' );
			var index = $visible.index( this );
			var next = null;

			if ( 'ArrowRight' === event.key ) {
				next = ( index + 1 ) % $visible.length;
			} else if ( 'ArrowLeft' === event.key ) {
				next = ( index - 1 + $visible.length ) % $visible.length;
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = $visible.length - 1;
			}

			if ( null !== next ) {
				event.preventDefault();
				activateTab( $visible.eq( next ).data( 'schemagicTab' ), true );
			}
		} );

		// "Fix" links in the health check jump to the right tab.
		$( document ).on( 'click', 'a[data-schemagic-tab]', function ( event ) {
			event.preventDefault();
			activateTab( $( this ).data( 'schemagicTab' ), true );

			var details = document.getElementById( 'schemagic-details' );
			if ( details ) {
				details.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );

		var stored = null;
		try {
			stored = window.sessionStorage.getItem( 'schemagicTab' );
		} catch ( e ) {}

		activateTab( stored || 'business' );
	}

	/* -------------------------------------------------------- Business type */

	function isA( type, ancestor ) {
		var parents = settings.typeParents || {};
		var guard = 0;

		while ( type && guard++ < 20 ) {
			if ( type === ancestor ) {
				return true;
			}
			type = parents[ type ];
		}

		return false;
	}

	function applyShowIf() {
		var type = $( '.schemagic-type-select' ).val();

		$( '.schemagic-editor [data-show-if-type]' ).each( function () {
			$( this ).prop( 'hidden', ! isA( type, $( this ).data( 'showIfType' ) ) );
		} );

		// If the open tab just disappeared, fall back to the first one.
		var $active = $( '.schemagic-tab.is-active' );
		if ( $active.length && $active.prop( 'hidden' ) ) {
			activateTab( 'business' );
		}
	}

	function initTypeSearch() {
		$( '.schemagic-type-search' ).each( function () {
			var $search = $( this );
			var $select = $( '#' + $search.attr( 'aria-controls' ) );
			var all = $select.find( 'option' ).map( function () {
				return { value: this.value, text: this.text };
			} ).get();

			// Enter would submit the post form.
			$search.on( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
				}
			} );

			$search.on( 'input', function () {
				var query = String( $search.val() ).trim().toLowerCase();
				var current = $select.val();

				$select.empty();

				all.forEach( function ( option ) {
					var plain = option.text.replace( /^(—\s)+/, '' );
					var match = ! query ||
						plain.toLowerCase().indexOf( query ) !== -1 ||
						option.value.toLowerCase().indexOf( query ) !== -1;

					// Always keep the selected type so filtering never changes the value.
					if ( match || option.value === current ) {
						$select.append( new Option( query ? plain : option.text, option.value, false, option.value === current ) );
					}
				} );
			} );
		} );

		$( document ).on( 'change', '.schemagic-type-select', applyShowIf );
		applyShowIf();
	}

	/* ---------------------------------------------------------------- Hours */

	function initHours() {
		var template = $( '#schemagic-range-template' ).html();
		var counter = 1000;

		if ( ! template ) {
			return;
		}

		function addRange( $cell, opens, closes ) {
			var html = template
				.split( '__NAME__' ).join( $cell.data( 'name' ) )
				.split( '__INDEX__' ).join( String( counter++ ) );
			var $range = $( $.parseHTML( html.trim() ) );
			var $inputs = $range.find( 'input' );

			$inputs.eq( 0 ).val( opens || '' );
			$inputs.eq( 1 ).val( closes || '' );
			$cell.find( '.schemagic-hours__list' ).append( $range );

			return $range;
		}

		function sync( $day ) {
			var isOpen = 'open' === $day.find( '.schemagic-hours__mode' ).val();
			var $cell = $day.find( '.schemagic-hours__ranges' );

			$cell.toggleClass( 'is-inactive', ! isOpen );

			if ( isOpen && ! $cell.find( '.schemagic-range' ).length ) {
				addRange( $cell, '09:00', '17:00' );
			}
		}

		$( document ).on( 'change', '.schemagic-hours__mode', function () {
			sync( $( this ).closest( 'tr' ) );
		} );

		$( document ).on( 'click', '.schemagic-hours__add', function () {
			addRange( $( this ).closest( 'td' ) ).find( 'input' ).first().trigger( 'focus' );
			changed();
		} );

		$( document ).on( 'click', '.schemagic-range__remove', function () {
			var $day = $( this ).closest( 'tr' );

			$( this ).closest( '.schemagic-range' ).remove();

			if ( ! $day.find( '.schemagic-range' ).length ) {
				$day.find( '.schemagic-hours__mode' ).val( 'closed' );
				sync( $day );
			}

			changed();
		} );

		$( document ).on( 'click', '.schemagic-hours__copy', function () {
			var $table = $( this ).closest( '.schemagic-field__control' ).find( '.schemagic-hours' );
			var $monday = $table.find( 'tr[data-day="monday"]' );
			var mode = $monday.find( '.schemagic-hours__mode' ).val();
			var ranges = $monday.find( '.schemagic-range' ).map( function () {
				var $inputs = $( this ).find( 'input' );
				return { opens: $inputs.eq( 0 ).val(), closes: $inputs.eq( 1 ).val() };
			} ).get();

			[ 'tuesday', 'wednesday', 'thursday', 'friday' ].forEach( function ( day ) {
				var $day = $table.find( 'tr[data-day="' + day + '"]' );
				var $cell = $day.find( '.schemagic-hours__ranges' );

				$day.find( '.schemagic-hours__mode' ).val( mode );
				$cell.find( '.schemagic-hours__list' ).empty();

				ranges.forEach( function ( range ) {
					addRange( $cell, range.opens, range.closes );
				} );

				sync( $day );
			} );

			changed();
		} );

		$( '.schemagic-hours__day' ).each( function () {
			sync( $( this ) );
		} );
	}

	/* -------------------------------------------------------- Special hours */

	function initSpecialHours() {
		var $wrap = $( '.schemagic-special' );
		var template = $( '#schemagic-special-template' ).html();
		var counter = 1000;

		if ( ! $wrap.length || ! template ) {
			return;
		}

		function syncRow( $row ) {
			// Disabled inputs aren't submitted; times don't apply to a closed day.
			$row.find( '.schemagic-special__time' ).prop( 'disabled', $row.find( '.schemagic-special__closed' ).is( ':checked' ) );
		}

		function syncTable() {
			var $table = $wrap.find( '.schemagic-special__table' );
			$table.prop( 'hidden', ! $table.find( 'tbody tr' ).length );
		}

		$wrap.on( 'click', '.schemagic-special__add', function () {
			var html = template.split( '__INDEX__' ).join( String( counter++ ) );
			var $row = $( $.parseHTML( html.trim() ) );

			$wrap.find( 'tbody' ).append( $row );
			syncRow( $row );
			syncTable();
			$row.find( 'input' ).first().trigger( 'focus' );
			changed();
		} );

		$wrap.on( 'click', '.schemagic-special__remove', function () {
			$( this ).closest( 'tr' ).remove();
			syncTable();
			changed();
		} );

		$wrap.on( 'change', '.schemagic-special__closed', function () {
			syncRow( $( this ).closest( 'tr' ) );
		} );

		$wrap.find( '.schemagic-special__row' ).each( function () {
			syncRow( $( this ) );
		} );
	}

	/* ---------------------------------------------------------------- Media */

	function initMedia() {
		if ( ! window.wp || ! wp.media ) {
			return;
		}

		$( document ).on( 'click', '.schemagic-media__select', function ( event ) {
			event.preventDefault();

			var $box = $( this ).closest( '.schemagic-media' );
			var multiple = '1' === String( $box.data( 'multiple' ) );
			var frame = $box.data( 'frame' );

			if ( ! frame ) {
				frame = wp.media( {
					title: multiple ? i18n.choosePhotos : i18n.chooseImage,
					button: { text: multiple ? i18n.usePhotos : i18n.useImage },
					library: { type: 'image' },
					multiple: multiple ? 'add' : false
				} );

				// Pre-select the current images.
				frame.on( 'open', function () {
					var ids = String( $box.find( '.schemagic-media__value' ).val() ).split( ',' ).filter( Boolean );
					var selection = frame.state().get( 'selection' );

					selection.reset( ids.map( function ( id ) {
						var attachment = wp.media.attachment( id );
						attachment.fetch();
						return attachment;
					} ) );
				} );

				frame.on( 'select', function () {
					var ids = [];
					var $preview = $box.find( '.schemagic-media__preview' ).empty();

					frame.state().get( 'selection' ).each( function ( model ) {
						var attachment = model.toJSON();
						var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

						ids.push( attachment.id );
						$preview.append( $( '<img>', { src: url, alt: '', 'class': 'schemagic-media__thumb' } ) );
					} );

					$box.find( '.schemagic-media__value' ).val( ids.join( ',' ) ).trigger( 'change' );
					$box.find( '.schemagic-media__remove' ).prop( 'hidden', ! ids.length );
				} );

				$box.data( 'frame', frame );
			}

			frame.open();
		} );

		$( document ).on( 'click', '.schemagic-media__remove', function ( event ) {
			event.preventDefault();

			var $box = $( this ).closest( '.schemagic-media' );

			$box.find( '.schemagic-media__value' ).val( '' ).trigger( 'change' );
			$box.find( '.schemagic-media__preview' ).empty();
			$( this ).prop( 'hidden', true );
		} );
	}

	/* -------------------------------------------------------------- Preview */

	function initPreview() {
		var $code = $( '#schemagic-preview-code' );
		var $form = $( '#post' );
		var $status = $( '#schemagic-preview-status' );
		var timer = null;
		var request = null;

		if ( ! $code.length || ! $form.length || ! settings.postId ) {
			return;
		}

		function refresh() {
			if ( request ) {
				request.abort();
			}

			$status.text( i18n.updating );

			var data = $form.find( '[name^="schemagic["], [name="post_title"]' ).serialize() +
				'&action=' + encodeURIComponent( settings.previewAction ) +
				'&nonce=' + encodeURIComponent( settings.nonce ) +
				'&post_id=' + encodeURIComponent( settings.postId );

			request = $.post( settings.ajaxUrl, data )
				.done( function ( response ) {
					if ( ! response || ! response.success ) {
						$status.text( i18n.previewError );
						return;
					}

					$code.text( response.data.json );
					$( '#schemagic-health-output' ).html( response.data.health );
					$( '#schemagic-test-google' ).attr( 'href', response.data.richResults );
					$( '#schemagic-test-validator' ).attr( 'href', response.data.validator );
					$status.text( '' );
				} )
				.fail( function ( xhr, textStatus ) {
					if ( 'abort' !== textStatus ) {
						$status.text( i18n.previewError );
					}
				} );
		}

		function schedule() {
			window.clearTimeout( timer );
			timer = window.setTimeout( refresh, 600 );
		}

		$form.on( 'input change', '[name^="schemagic["], [name="post_title"]', schedule );
		$( document ).on( 'schemagic:changed', schedule );
	}

	function initCopy() {
		var $status = $( '#schemagic-preview-status' );

		function fallbackCopy( text ) {
			var $textarea = $( '<textarea readonly>' ).val( text ).css( { position: 'fixed', top: '-1000px' } ).appendTo( 'body' );
			var ok = false;

			$textarea[ 0 ].select();

			try {
				ok = document.execCommand( 'copy' );
			} catch ( e ) {}

			$textarea.remove();

			return ok;
		}

		$( '#schemagic-copy' ).on( 'click', function () {
			var text = $( '#schemagic-preview-code' ).text();

			function done( ok ) {
				$status.text( ok ? i18n.copied : i18n.copyFailed );
			}

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then(
					function () {
						done( true );
					},
					function () {
						done( fallbackCopy( text ) );
					}
				);
			} else {
				done( fallbackCopy( text ) );
			}
		} );
	}

	$( function () {
		initTabs();
		initTypeSearch();
		initHours();
		initSpecialHours();
		initMedia();
		initCopy();
		initPreview();
	} );
}( jQuery ) );
