/**
 * SPARXSTAR Policy Domain Renderer — Admin JavaScript
 *
 * © Starisian Technologies. All rights reserved.
 */

/* global spxPolicyAdmin */

( function ( $ ) {
	'use strict';

	// =========================================================================
	// Placeholder token clipboard copy
	// =========================================================================

	$( document ).on( 'click', '.spx-placeholder-token', function () {
		var $token = $( this );
		var text   = $token.data( 'token' ) || $token.text();

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( function () {
				showCopied( $token );
			} );
		} else {
			// Fallback for non-secure contexts.
			var $temp = $( '<textarea>' ).val( text ).appendTo( 'body' ).select();
			document.execCommand( 'copy' );
			$temp.remove();
			showCopied( $token );
		}
	} );

	function showCopied( $el ) {
		var label = ( spxPolicyAdmin && spxPolicyAdmin.copied ) ? spxPolicyAdmin.copied : 'Copied!';
		var originalTitle = $el.attr( 'title' );

		$el.addClass( 'spx-copied' ).attr( 'title', label );

		setTimeout( function () {
			$el.removeClass( 'spx-copied' ).attr( 'title', originalTitle );
		}, 1500 );
	}

	// =========================================================================
	// Policy settings meta box — show / hide fields by scope
	// =========================================================================

	function updatePolicyScopeFields() {
		var $select = $( '#spx_policy_scope' );
		if ( ! $select.length ) {
			return;
		}

		var $metaBox = $select.closest( '.postbox' );
		var scope    = $select.val();

		// Remove all scope classes.
		$metaBox
			.removeClass( 'spx-scope-active-default' )
			.removeClass( 'spx-scope-active-policy_set' )
			.removeClass( 'spx-scope-active-profile_override' )
			.removeClass( 'spx-scope-active-host_override' );

		if ( scope ) {
			$metaBox.addClass( 'spx-scope-active-' + scope );
		}
	}

	$( document ).on( 'change', '#spx_policy_scope', updatePolicyScopeFields );

	// Run on page load.
	$( function () {
		updatePolicyScopeFields();
	} );

} )( jQuery );
