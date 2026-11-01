/**
 * SEO Confident admin scripts.
 *
 * Values that come from PHP (the dismiss nonce, separator, site name and post title)
 * are printed as the seocAdmin object just before this file loads.
 */
jQuery( function ( $ ) {
	var data = window.seocAdmin || {};

	// Dismiss the setup notice for a while.
	$( '#seoc-setup-notice' ).on( 'click', '.notice-dismiss', function () {
		$.post( window.ajaxurl, {
			action: 'seoc_dismiss_setup_notice',
			nonce: data.dismissNonce || ''
		} );
	} );

	// Meta box tabs.
	$( '.seoc-tab' ).on( 'click', function () {
		var tab = $( this ).data( 'tab' );
		$( '.seoc-tab' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		$( '.seoc-tab-content' ).removeClass( 'active' );
		$( '#seoc-tab-' + tab ).addClass( 'active' );
	} );

	// Live search preview in the meta box.
	var sep = data.separator || '|';
	var siteName = data.siteName || '';
	var postTitle = data.postTitle || '';

	$( '#seoc_title' ).on( 'input', function () {
		var val = $( this ).val() || ( postTitle + ' ' + sep + ' ' + siteName );
		$( '#seoc-preview-title' ).text( val );
		var len = $( this ).val().length;
		var $count = $( '#seoc-title-count' );
		$count.text( len + '/60' );
		$count.toggleClass( 'seoc-char-warn', len > 60 );
	} );

	$( '#seoc_description' ).on( 'input', function () {
		var val = $( this ).val() || 'Auto-generated from content...';
		$( '#seoc-preview-desc' ).text( val );
		var len = $( this ).val().length;
		var $count = $( '#seoc-desc-count' );
		$count.text( len + '/160' );
		$count.toggleClass( 'seoc-char-warn', len > 160 );
	} );

	// Check the per page custom schema as it is typed.
	$( '#seoc_custom_schema' ).on( 'input', function () {
		var val = $( this ).val().trim();
		var $msg = $( '#seoc-schema-validation' );
		if ( ! val ) {
			$msg.text( '' );
			return;
		}
		try {
			JSON.parse( val );
			$msg.text( 'Valid JSON' ).css( 'color', '#00a32a' );
		} catch ( e ) {
			$msg.text( 'Invalid JSON: ' + e.message ).css( 'color', '#d63638' );
		}
	} );

	// Check the site wide custom schema as it is typed.
	$( '#seoc_custom_wide_schema' ).on( 'input', function () {
		var val = $( this ).val().trim();
		var $msg = $( '#seoc-schema-validation' );
		if ( ! val ) {
			$msg.text( '' );
			return;
		}
		try {
			JSON.parse( val );
			$msg.text( '✓ Valid JSON' ).css( 'color', '#00a32a' );
		} catch ( e ) {
			$msg.text( '✗ Invalid JSON: ' + e.message ).css( 'color', '#d63638' );
		}
	} );

	// Media library picker for image fields.
	$( '.seoc-upload-btn' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		var targetId = $( this ).data( 'target' );
		var frame = window.wp.media( { title: 'Select Image', multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function () {
			var url = frame.state().get( 'selection' ).first().toJSON().url;
			$( '#' + targetId ).val( url );
		} );
		frame.open();
	} );
} );
