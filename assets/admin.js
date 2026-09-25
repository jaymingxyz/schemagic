/* global jQuery, wp */
/**
 * Schemagic admin: tabs, business type search, hours, media pickers, schema import, live preview.
 */
( function ( $ ) {
	'use strict';

	var settings = window.schemagicAdmin || {};
	var i18n = settings.i18n || {};

	// Set by the init functions below, so the importer can reuse them.
	var activateTab = function () {};
	var setHours = function () {};
	var setSpecialHours = function () {};

	/** Tell the live preview something changed. */
	function changed() {
		$( document ).trigger( 'schemagic:changed' );
	}

	/** The form input for a field key. */
	function fieldInput( key ) {
		return $( '[name="schemagic[' + key + ']"]' );
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

		/** Replace one day's mode and ranges. */
		function setDay( $day, mode, ranges ) {
			var $cell = $day.find( '.schemagic-hours__ranges' );

			$day.find( '.schemagic-hours__mode' ).val( mode );
			$cell.find( '.schemagic-hours__list' ).empty();

			( ranges || [] ).forEach( function ( range ) {
				addRange( $cell, range.opens, range.closes );
			} );

			sync( $day );
		}

		setHours = function ( hours ) {
			$( '.schemagic-hours__day' ).each( function () {
				var row = hours[ $( this ).data( 'day' ) ] || { mode: 'closed', ranges: [] };
				setDay( $( this ), row.mode, row.ranges );
			} );
		};

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
				setDay( $table.find( 'tr[data-day="' + day + '"]' ), mode, ranges );
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

		function addRow( values ) {
			var html = template.split( '__INDEX__' ).join( String( counter++ ) );
			var $row = $( $.parseHTML( html.trim() ) );

			if ( values ) {
				$row.find( '[name$="[from]"]' ).val( values.from || '' );
				$row.find( '[name$="[through]"]' ).val( values.through || '' );
				$row.find( '.schemagic-special__closed' ).prop( 'checked', !! values.closed );
				$row.find( '[name$="[opens]"]' ).val( values.opens || '' );
				$row.find( '[name$="[closes]"]' ).val( values.closes || '' );
			}

			$wrap.find( 'tbody' ).append( $row );
			syncRow( $row );
			syncTable();

			return $row;
		}

		setSpecialHours = function ( rows ) {
			$wrap.find( 'tbody' ).empty();

			( rows || [] ).forEach( function ( row ) {
				addRow( row );
			} );

			syncTable();
		};

		$wrap.on( 'click', '.schemagic-special__add', function () {
			addRow().find( 'input' ).first().trigger( 'focus' );
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

	/**
	 * Show images in a picker and store their IDs.
	 *
	 * @param {jQuery} $box  The .schemagic-media wrapper.
	 * @param {Array}  items Objects with id and url (thumbnail).
	 */
	function setMedia( $box, items ) {
		var $preview = $box.find( '.schemagic-media__preview' ).empty();

		items.forEach( function ( item ) {
			$preview.append( $( '<img>', { src: item.url, alt: '', 'class': 'schemagic-media__thumb' } ) );
		} );

		$box.find( '.schemagic-media__value' ).val( items.map( function ( item ) {
			return item.id;
		} ).join( ',' ) ).trigger( 'change' );

		$box.find( '.schemagic-media__remove' ).prop( 'hidden', ! items.length );
	}

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
					var items = frame.state().get( 'selection' ).map( function ( model ) {
						var attachment = model.toJSON();

						return {
							id: attachment.id,
							url: attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url
						};
					} );

					setMedia( $box, items );
				} );

				$box.data( 'frame', frame );
			}

			frame.open();
		} );

		$( document ).on( 'click', '.schemagic-media__remove', function ( event ) {
			event.preventDefault();
			setMedia( $( this ).closest( '.schemagic-media' ), [] );
		} );
	}

	/* --------------------------------------------------------------- Import */

	/**
	 * Put the business name in the post title if that's empty, otherwise in the
	 * Business name field (only when it differs from the title).
	 */
	function fillName( name ) {
		var $title = $( '#title' );
		var $name = fieldInput( 'name' );
		var title = $title.length ? String( $title.val() ).trim() : '';

		if ( $title.length && '' === title ) {
			$title.val( name ).trigger( 'input' );
			$( '#title-prompt-text' ).addClass( 'screen-reader-text' );
			$name.val( '' );
		} else if ( title === name ) {
			$name.val( '' );
		} else {
			$name.val( name );
		}
	}

	/**
	 * Fill the form from imported data. Only keys present in data are changed.
	 */
	function fillForm( data, media ) {
		var types = settings.fieldTypes || {};

		Object.keys( data ).forEach( function ( key ) {
			var value = data[ key ];
			var $input = fieldInput( key );

			switch ( types[ key ] ) {
				case 'business_type':
					// Clear any search filter so the imported type is in the list.
					$( '.schemagic-type-search' ).val( '' ).trigger( 'input' );
					$input.val( value ).trigger( 'change' );
					break;

				case 'checkbox':
					$input.prop( 'checked', !! value );
					break;

				case 'lines':
				case 'url_lines':
					$input.val( [].concat( value ).join( '\n' ) );
					break;

				case 'image':
				case 'gallery':
					setMedia( $input.closest( '.schemagic-media' ), media[ key ] || [] );
					break;

				case 'hours':
					setHours( value );
					break;

				case 'special_hours':
					setSpecialHours( value );
					break;

				default:
					if ( 'name' === key ) {
						fillName( value );
					} else {
						$input.val( value );
					}
			}
		} );

		changed();
	}

	/** Whether any text field already has a value that an import could replace. */
	function formHasData() {
		return $( '#post' )
			.find( 'input[type="text"][name^="schemagic["], input[type="tel"][name^="schemagic["], textarea[name^="schemagic["]' )
			.filter( function () {
				return '' !== String( this.value ).trim();
			} ).length > 0;
	}

	function initImport() {
		var $button = $( '#schemagic-import-run' );
		var $code = $( '#schemagic-import-code' );
		var $result = $( '#schemagic-import-result' );
		var $choose = $( '.schemagic-import__choose' );
		var $choice = $( '#schemagic-import-choice' );
		var $spinner = $( '.schemagic-import__actions .spinner' );

		if ( ! $button.length || ! settings.postId ) {
			return;
		}

		function notice( type, message, items ) {
			var $notice = $( '<div>', { 'class': 'notice inline notice-' + type } ).append( $( '<p>' ).text( message ) );

			if ( items && items.length ) {
				var $list = $( '<ul>' );

				items.forEach( function ( item ) {
					$list.append( $( '<li>' ).text( item ) );
				} );

				$notice.append( $list );
			}

			return $notice;
		}

		function showError( message ) {
			$result.empty().append( notice( 'error', message || i18n.importFailed ) );
		}

		function showChoices( businesses, index ) {
			$choice.empty();

			if ( businesses.length < 2 ) {
				$choose.prop( 'hidden', true );
				return;
			}

			businesses.forEach( function ( label, i ) {
				$choice.append( new Option( label, String( i ), false, i === index ) );
			} );

			$choose.prop( 'hidden', false );
		}

		function run( index ) {
			var code = String( $code.val() ).trim();

			if ( '' === code ) {
				showError( i18n.importEmpty );
				$code.trigger( 'focus' );
				return;
			}

			$button.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );

			$.post( settings.ajaxUrl, {
				action: settings.importAction,
				nonce: settings.importNonce,
				post_id: settings.postId,
				code: code,
				index: index
			} )
				.done( function ( response ) {
					if ( ! response || ! response.success ) {
						showError( response && response.data && response.data.message );
						return;
					}

					var result = response.data;

					fillForm( result.data, result.media || {} );
					showChoices( result.businesses || [], result.index );

					$result.empty().append( notice( 'success', i18n.importFilled + ' ' + result.filled.join( ', ' ) + '. ' + i18n.importReview ) );

					if ( result.notes && result.notes.length ) {
						$result.append( notice( 'warning', i18n.importNotes, result.notes ) );
					}
				} )
				.fail( function ( xhr ) {
					var data = xhr.responseJSON && xhr.responseJSON.data;
					showError( data && data.message );
				} )
				.always( function () {
					$button.prop( 'disabled', false );
					$spinner.removeClass( 'is-active' );
				} );
		}

		$button.on( 'click', function () {
			if ( formHasData() && ! window.confirm( i18n.importConfirm ) ) {
				return;
			}

			run( 0 );
		} );

		$choice.on( 'change', function () {
			run( parseInt( $choice.val(), 10 ) || 0 );
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
		initImport();
		initCopy();
		initPreview();
	} );
}( jQuery ) );
