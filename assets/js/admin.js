( function ( $ ) {
	'use strict';

	function message( text, isError ) {
		$( '.zq-oauth-message' )
			.text( text || '' )
			.toggleClass( 'zq-error', Boolean( isError ) );
	}

	function poll( popup, interval ) {
		window.setTimeout( function () {
			$.post( ZQSmartLinks.ajaxUrl, {
				action: 'zq_oauth_poll',
				nonce: ZQSmartLinks.nonce
			} ).done( function ( response ) {
				if ( ! response.success ) {
					message( response.data && response.data.message ? response.data.message : 'ZipQuantum connection failed.', true );
					return;
				}
				if ( response.data.status === 'connected' ) {
					message( ZQSmartLinks.i18n.connected, false );
					if ( popup && ! popup.closed ) {
						popup.close();
					}
					window.setTimeout( function () { window.location.reload(); }, 500 );
					return;
				}
				poll( popup, interval );
			} ).fail( function ( xhr ) {
				var data = xhr.responseJSON && xhr.responseJSON.data;
				message( data && data.message ? data.message : 'ZipQuantum connection failed.', true );
			} );
		}, interval * 1000 );
	}

	$( document ).on( 'click', '.zq-oauth-start', function ( event ) {
		event.preventDefault();
		var button = $( this );
		var popup = window.open( 'about:blank', 'zipquantum_oauth', 'width=760,height=760,resizable=yes,scrollbars=yes' );
		if ( ! popup ) {
			message( ZQSmartLinks.i18n.popup, true );
			return;
		}
		button.prop( 'disabled', true );
		message( ZQSmartLinks.i18n.connecting, false );
		$.post( ZQSmartLinks.ajaxUrl, {
			action: 'zq_oauth_start',
			nonce: ZQSmartLinks.nonce,
			intent: button.data( 'intent' ) || 'connect'
		} ).done( function ( response ) {
			button.prop( 'disabled', false );
			if ( ! response.success ) {
				popup.close();
				message( response.data && response.data.message ? response.data.message : 'ZipQuantum connection failed.', true );
				return;
			}
			popup.location = response.data.authorization_url;
			poll( popup, response.data.interval || 3 );
		} ).fail( function ( xhr ) {
			button.prop( 'disabled', false );
			popup.close();
			var data = xhr.responseJSON && xhr.responseJSON.data;
			message( data && data.message ? data.message : 'ZipQuantum connection failed.', true );
		} );
	} );

	$( document ).on( 'click', '.zq-copy-link', function () {
		var button = this;
		var value = $( button ).data( 'zq-copy' );
		if ( ! value ) {
			return;
		}
		var copied = function () {
			var original = button.textContent;
			button.textContent = ZQSmartLinks.i18n.copied;
			window.setTimeout( function () { button.textContent = original; }, 1500 );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( value ).then( copied );
			return;
		}
		var input = $( '<textarea readonly>' ).css( { position: 'fixed', opacity: 0 } ).val( value ).appendTo( document.body );
		input[ 0 ].select();
		if ( document.execCommand( 'copy' ) ) {
			copied();
		}
		input.remove();
	} );

	$( document ).on( 'click', '.zq-object-action', function () {
		var button = $( this );
		var action = button.data( 'zq-action' );
		var fields = {
			action: action,
			object_type: button.data( 'zq-object-type' ),
			object_id: button.data( 'zq-object-id' ),
			_wpnonce: button.data( 'zq-nonce' )
		};

		if ( action === 'zq_object_attach' ) {
			var linkIdInput = button.closest( 'details' ).find( '.zq-attach-link-id' )[ 0 ];
			if ( ! linkIdInput || ! linkIdInput.reportValidity() ) {
				return;
			}
			fields.link_id = linkIdInput.value;
		}

		var form = $( '<form>', {
			method: 'post',
			action: ZQSmartLinks.adminPostUrl
		} ).appendTo( document.body );

		$.each( fields, function ( name, value ) {
			$( '<input>', { type: 'hidden', name: name, value: value } ).appendTo( form );
		} );

		button.prop( 'disabled', true );
		form[ 0 ].submit();
	} );
}( jQuery ) );
