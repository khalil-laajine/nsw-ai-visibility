/**
 * NSW AI Visibility admin screen: tabs, block presets and robots.txt preview.
 * No dependency. Without JavaScript every section stays visible.
 */
( function () {
	'use strict';

	var root = document.querySelector( '.nsw-aiv' );
	if ( ! root ) {
		return;
	}

	var tabs = root.querySelectorAll( '.nsw-aiv-tabs .nav-tab' );
	var panels = root.querySelectorAll( '.nsw-aiv-panel' );
	var form = root.querySelector( '.nsw-aiv-form' );
	var submit = form ? form.querySelector( '.submit' ) : null;
	var referer = form ? form.querySelector( 'input[name="_wp_http_referer"]' ) : null;
	var names = Array.prototype.map.call( tabs, function ( tab ) {
		return tab.getAttribute( 'data-tab' );
	} );

	root.classList.add( 'has-js' );

	function show( name ) {
		if ( names.indexOf( name ) === -1 ) {
			name = names[ 0 ];
		}
		Array.prototype.forEach.call( tabs, function ( tab ) {
			var active = tab.getAttribute( 'data-tab' ) === name;
			tab.classList.toggle( 'nav-tab-active', active );
			if ( active ) {
				tab.setAttribute( 'aria-current', 'page' );
			} else {
				tab.removeAttribute( 'aria-current' );
			}
		} );
		Array.prototype.forEach.call( panels, function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-panel' ) !== name;
		} );
		// The stats tab has nothing to save.
		if ( submit ) {
			submit.hidden = 'stats' === name;
		}
		// After saving, WordPress sends the user back to the referer: keep the tab in it.
		if ( referer ) {
			var url = new URL( referer.value, window.location.href );
			url.searchParams.set( 'tab', name );
			referer.value = url.pathname + url.search;
		}
	}

	function current() {
		var fromHash = window.location.hash.replace( '#nsw-aiv-', '' );
		if ( names.indexOf( fromHash ) !== -1 ) {
			return fromHash;
		}
		return new URLSearchParams( window.location.search ).get( 'tab' ) || names[ 0 ];
	}

	Array.prototype.forEach.call( tabs, function ( tab ) {
		tab.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			var name = tab.getAttribute( 'data-tab' );
			show( name );
			if ( window.history.replaceState ) {
				window.history.replaceState( null, '', '#nsw-aiv-' + name );
			}
		} );
	} );

	show( current() );

	// Live robots.txt preview and presets.
	var preview = document.getElementById( 'nsw-aiv-robots-preview' );
	var boxes = root.querySelectorAll( 'input[name$="[blocked_bots][]"]' );

	function render() {
		if ( ! preview ) {
			return;
		}
		var lines = [];
		Array.prototype.forEach.call( boxes, function ( box ) {
			if ( box.checked ) {
				lines.push( 'User-agent: ' + box.value, 'Disallow: /', '' );
			}
		} );
		preview.textContent = lines.length
			? '# AI crawlers (NSW AI Visibility)\n' + lines.join( '\n' ).trim() + '\n'
			: preview.getAttribute( 'data-empty' );
	}

	Array.prototype.forEach.call( boxes, function ( box ) {
		box.addEventListener( 'change', render );
	} );

	Array.prototype.forEach.call( root.querySelectorAll( '[data-preset]' ), function ( button ) {
		button.addEventListener( 'click', function () {
			var preset = button.getAttribute( 'data-preset' );
			Array.prototype.forEach.call( boxes, function ( box ) {
				box.checked = 'training' === preset && 'training' === box.getAttribute( 'data-purpose' );
			} );
			render();
		} );
	} );
}() );
