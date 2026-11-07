/**
 * Nazari SEO Assistant: "Review structure" window for the Elementor editor.
 * Adds a right-click item to every element, sends the element's settings to the
 * site's own REST endpoint, and shows the advice in a floating, draggable window.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.NZSA_EDITOR || {};
	var t = cfg.i18n || {};
	var win = null; // The floating window (DOM node).
	var state = { id: '', scope: 'element', role: 'auto', showOk: false, busy: false, data: null, tree: null };

	/* ---------------------------------------------------------------- *
	 * Small DOM helpers (all text goes through textContent, never HTML)
	 * ---------------------------------------------------------------- */

	function h( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text && null !== text ) {
			node.textContent = String( text );
		}
		return node;
	}

	function fmt( template, args ) {
		var i = 0;
		return String( template ).replace( /%(?:(\d+)\$)?d/g, function ( m, pos ) {
			var value = args[ pos ? parseInt( pos, 10 ) - 1 : i++ ];
			return String( parseInt( value, 10 ) || 0 );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Reading the editor
	 * ---------------------------------------------------------------- */

	function getContainer( id ) {
		return window.elementor && elementor.getContainer ? elementor.getContainer( id ) : null;
	}

	function documentContainer() {
		var doc = window.elementor && elementor.documents ? elementor.documents.getCurrent() : null;
		return doc && doc.container ? doc.container : null;
	}

	function modelData( container ) {
		var data = container.model.toJSON( { remove: [ 'default', 'editSettings', 'defaultEditSettings' ] } );
		return strip( data );
	}

	/** Drops bulky settings the checks never read. */
	function strip( node ) {
		if ( node && node.settings ) {
			delete node.settings.__dynamic__;
			delete node.settings.__globals__;
			delete node.settings.custom_css;
		}
		if ( node && Array.isArray( node.elements ) ) {
			node.elements.forEach( strip );
		}
		return node;
	}

	function topLevelOf( container, doc ) {
		var c = container;
		while ( c.parent && doc && c.parent.id !== doc.id ) {
			c = c.parent;
		}
		return c;
	}

	/** Elements to analyse plus where the clicked element sits on the page. */
	function collect( id, scope ) {
		var doc = documentContainer();
		var container = getContainer( id );
		if ( ! container || ! doc ) {
			return null;
		}

		var topList = doc.children || [];
		var top = topLevelOf( container, doc );
		var index = Math.max( 0, topList.indexOf( top ) );

		var elements;
		var containers;
		if ( 'page' === scope ) {
			containers = topList.slice();
			elements = topList.map( modelData );
		} else {
			containers = [ container ];
			elements = [ modelData( container ) ];
		}

		return { elements: elements, containers: containers, index: index, total: Math.max( 1, topList.length ), postId: ( window.elementor && elementor.config && elementor.config.document ) ? elementor.config.document.id : 0 };
	}

	function titleOf( model ) {
		var title = '';
		try {
			title = model.getTitle ? model.getTitle() : '';
		} catch ( e ) {}
		if ( ! title ) {
			title = t[ model.get( 'elType' ) ] || t.widget;
		}
		return title;
	}

	/** Flat list in document order, each with a numbered name ("Image 2"). */
	function flatten( container, depth, counters, out ) {
		var title = titleOf( container.model );
		counters[ title ] = ( counters[ title ] || 0 ) + 1;
		out.push( { id: container.id, depth: depth, name: title + ' ' + counters[ title ], type: container.model.get( 'elType' ) } );
		( container.children || [] ).forEach( function ( child ) {
			flatten( child, depth + 1, counters, out );
		} );
		return out;
	}

	function buildTree( id, scope ) {
		var out = [];
		var counters = {};
		if ( 'page' === scope ) {
			var doc = documentContainer();
			( doc ? doc.children || [] : [] ).forEach( function ( c ) {
				flatten( c, 0, counters, out );
			} );
		} else {
			var container = getContainer( id );
			if ( container ) {
				flatten( container, 0, counters, out );
			}
		}
		return out;
	}

	/* ---------------------------------------------------------------- *
	 * Measuring the live preview
	 * ---------------------------------------------------------------- */

	function currentDevice() {
		try {
			return elementor.channels.deviceMode.request( 'currentMode' ) || 'desktop';
		} catch ( e ) {
			return 'desktop';
		}
	}

	function previewWidth() {
		try {
			return Math.round( elementor.$preview[ 0 ].contentWindow.innerWidth ) || 0;
		} catch ( e ) {
			return 0;
		}
	}

	function round1( value ) {
		return Math.round( value * 10 ) / 10;
	}

	/** Drawn width of every element (and of each widget's first image) in the preview. */
	function measureNodes( containers ) {
		var widths = {};
		var images = {};
		var count = 0;

		function visit( c ) {
			if ( ! c || count >= 3000 ) {
				return;
			}
			count++;

			var el = c.view && c.view.el;
			if ( el && el.getBoundingClientRect ) {
				var w = el.getBoundingClientRect().width;
				if ( w > 0 ) {
					widths[ c.id ] = round1( w );
				}
				if ( c.model && 'widget' === c.model.get( 'elType' ) ) {
					var img = el.querySelector( 'img' );
					var iw = img ? img.getBoundingClientRect().width : 0;
					if ( iw > 0 ) {
						images[ c.id ] = round1( iw );
					}
				}
			}
			( c.children || [] ).forEach( visit );
		}

		( containers || [] ).forEach( visit );
		return { widths: widths, images: images };
	}

	/* ---------------------------------------------------------------- *
	 * Talking to the site
	 * ---------------------------------------------------------------- */

	function request( payload ) {
		return window.fetch( cfg.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( payload ),
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			return response.json();
		} );
	}

	function run() {
		var picked = collect( state.id, state.scope );
		if ( ! picked ) {
			render( null, t.error );
			return;
		}

		state.busy = true;
		state.tree = buildTree( state.id, state.scope );
		render( null, t.checking );

		var m = measureNodes( picked.containers );

		request( {
			elements: picked.elements,
			scope: state.scope,
			index: picked.index,
			total: picked.total,
			post_id: picked.postId,
			role: state.role,
			measure: { device: currentDevice(), viewport: previewWidth(), widths: m.widths, images: m.images },
		} )
			.then( function ( data ) {
				state.busy = false;
				state.data = data;
				render( data );
			} )
			.catch( function () {
				state.busy = false;
				render( null, t.error );
			} );
	}

	/* ---------------------------------------------------------------- *
	 * Window
	 * ---------------------------------------------------------------- */

	function ensureWindow() {
		if ( win && document.body.contains( win ) ) {
			return win;
		}

		win = h( 'div', 'nzsa-win' );
		win.setAttribute( 'role', 'dialog' );
		win.setAttribute( 'aria-label', t.title );

		var head = h( 'div', 'nzsa-win-head' );
		head.appendChild( h( 'span', 'nzsa-win-title', t.title ) );
		head.appendChild( h( 'span', 'nzsa-chip', '' ) );
		var close = h( 'button', 'nzsa-icon-btn', '×' );
		close.type = 'button';
		close.setAttribute( 'aria-label', t.close );
		close.addEventListener( 'click', closeWindow );
		head.appendChild( close );
		win.appendChild( head );

		var bar = h( 'div', 'nzsa-win-bar' );
		var scopes = h( 'div', 'nzsa-seg' );
		[ [ 'element', t.scopeElement ], [ 'page', t.scopePage ] ].forEach( function ( item ) {
			var b = h( 'button', '', item[ 1 ] );
			b.type = 'button';
			b.setAttribute( 'data-scope', item[ 0 ] );
			b.addEventListener( 'click', function () {
				if ( state.scope !== item[ 0 ] && ! state.busy ) {
					state.scope = item[ 0 ];
					run();
				}
			} );
			scopes.appendChild( b );
		} );
		bar.appendChild( scopes );

		var roleBox = h( 'label', 'nzsa-role' );
		roleBox.appendChild( h( 'span', '', t.role ) );
		var select = document.createElement( 'select' );
		select.setAttribute( 'data-nzsa', 'role' );
		var auto = document.createElement( 'option' );
		auto.value = 'auto';
		auto.textContent = String( t.roleAuto || '%s' ).replace( '%s', '…' );
		select.appendChild( auto );
		( cfg.roles || [] ).forEach( function ( role ) {
			var opt = document.createElement( 'option' );
			opt.value = role.key;
			opt.textContent = role.label;
			select.appendChild( opt );
		} );
		select.addEventListener( 'change', function () {
			state.role = select.value;
			if ( ! state.busy ) {
				run();
			}
		} );
		roleBox.appendChild( select );
		bar.appendChild( roleBox );

		var label = h( 'label', 'nzsa-check' );
		var box = document.createElement( 'input' );
		box.type = 'checkbox';
		box.addEventListener( 'change', function () {
			state.showOk = box.checked;
			if ( state.data ) {
				render( state.data );
			}
		} );
		label.appendChild( box );
		label.appendChild( h( 'span', '', t.showAll ) );
		bar.appendChild( label );

		var again = h( 'button', 'nzsa-btn', t.recheck );
		again.type = 'button';
		again.setAttribute( 'data-nzsa', 'recheck' );
		again.addEventListener( 'click', function () {
			if ( ! state.busy ) {
				run();
			}
		} );
		bar.appendChild( again );
		win.appendChild( bar );

		win.appendChild( h( 'div', 'nzsa-win-body' ) );
		document.body.appendChild( win );
		makeDraggable( win, head );
		return win;
	}

	function closeWindow() {
		if ( win && win.parentNode ) {
			win.parentNode.removeChild( win );
		}
		win = null;
	}

	function makeDraggable( node, handle ) {
		handle.addEventListener( 'pointerdown', function ( event ) {
			if ( event.target.closest( 'button' ) ) {
				return;
			}
			var rect = node.getBoundingClientRect();
			var dx = event.clientX - rect.left;
			var dy = event.clientY - rect.top;
			handle.setPointerCapture( event.pointerId );

			function move( e ) {
				node.style.insetInlineEnd = 'auto';
				node.style.left = Math.max( 0, Math.min( window.innerWidth - 80, e.clientX - dx ) ) + 'px';
				node.style.top = Math.max( 0, Math.min( window.innerHeight - 40, e.clientY - dy ) ) + 'px';
			}
			function up() {
				handle.removeEventListener( 'pointermove', move );
				handle.removeEventListener( 'pointerup', up );
			}
			handle.addEventListener( 'pointermove', move );
			handle.addEventListener( 'pointerup', up );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Rendering
	 * ---------------------------------------------------------------- */

	function grade( points, max ) {
		var ratio = max ? points / max : 0;
		if ( ratio >= 0.9 ) {
			return { key: 'excellent', label: t.excellent };
		}
		if ( ratio >= 0.7 ) {
			return { key: 'good', label: t.good };
		}
		if ( ratio >= 0.45 ) {
			return { key: 'needs', label: t.needsWork };
		}
		return { key: 'poor', label: t.poor };
	}

	function locate( id ) {
		var container = getContainer( id );
		if ( ! container ) {
			return;
		}
		try {
			$e.run( 'document/elements/select', { container: container } );
		} catch ( e ) {}
		var node = container.view && container.view.el;
		if ( node && node.scrollIntoView ) {
			node.scrollIntoView( { block: 'center', behavior: 'smooth' } );
			var old = node.style.outline;
			node.style.outline = '3px solid #2f6bff';
			window.setTimeout( function () {
				node.style.outline = old;
			}, 1600 );
		}
	}

	function issueNode( issue ) {
		var li = h( 'li', 'nzsa-issue is-' + issue.severity );
		var head = h( 'div', 'nzsa-issue-head' );
		head.appendChild( h( 'span', 'nzsa-tag', issue.label ) );
		head.appendChild( h( 'span', 'nzsa-issue-text', issue.message ) );
		li.appendChild( head );
		if ( issue.fix ) {
			var fix = h( 'div', 'nzsa-fix' );
			fix.appendChild( h( 'strong', '', t.fix + ': ' ) );
			fix.appendChild( document.createTextNode( issue.fix ) );
			li.appendChild( fix );
		}
		return li;
	}

	function renderList( body, data ) {
		var byId = {};
		( data.nodes || [] ).forEach( function ( n ) {
			byId[ n.id ] = n.issues;
		} );

		var list = h( 'ol', 'nzsa-list' );
		( state.tree || [] ).forEach( function ( item ) {
			var issues = byId[ item.id ] || [];
			if ( ! issues.length && ! state.showOk && item.depth > 0 ) {
				return;
			}

			var row = h( 'li', 'nzsa-node' + ( issues.length ? ' has-issues' : ' is-ok' ) );
			row.style.paddingInlineStart = ( item.depth * 14 ) + 'px';

			var head = h( 'div', 'nzsa-node-head' );
			head.appendChild( h( 'span', 'nzsa-dot ' + ( issues.length ? 'is-' + issues[ 0 ].severity : 'is-pass' ) ) );
			var name = h( 'button', 'nzsa-node-name', item.name );
			name.type = 'button';
			name.title = t.locate;
			name.addEventListener( 'click', function () {
				locate( item.id );
			} );
			head.appendChild( name );
			if ( ! issues.length ) {
				head.appendChild( h( 'span', 'nzsa-muted', t.ok ) );
			}
			row.appendChild( head );

			if ( issues.length ) {
				var ul = h( 'ul', 'nzsa-issues' );
				issues.forEach( function ( issue ) {
					ul.appendChild( issueNode( issue ) );
				} );
				row.appendChild( ul );
			}
			list.appendChild( row );
		} );
		body.appendChild( list );
	}

	function measureNote( measure ) {
		if ( measure && measure.used ) {
			return t.measured;
		}
		if ( measure && 'device' === measure.reason ) {
			return t.measureDevice;
		}
		if ( measure && 'viewport' === measure.reason ) {
			return t.measureNarrow;
		}
		return t.estimated;
	}

	function roleLabel( key ) {
		var found = ( cfg.roles || [] ).filter( function ( r ) {
			return r.key === key;
		} )[ 0 ];
		return found ? found.label : key;
	}

	function renderSummary( body, data ) {
		var s = data.summary;
		var g = grade( s.total, s.max );

		var box = h( 'section', 'nzsa-summary' );
		box.appendChild( h( 'h3', '', t.summary ) );

		var top = h( 'div', 'nzsa-score is-' + g.key );
		var ring = h( 'div', 'nzsa-ring' );
		ring.style.setProperty( '--nzsa-pct', String( s.max ? Math.round( 100 * s.total / s.max ) : 0 ) );
		ring.appendChild( h( 'strong', '', s.total ) );
		top.appendChild( ring );
		var text = h( 'div', 'nzsa-score-text' );
		text.appendChild( h( 'strong', '', g.label ) );
		text.appendChild( h( 'span', '', t.score + ': ' + s.total + ' ' + t.outOf + ' ' + s.max ) );
		top.appendChild( text );
		box.appendChild( top );

		var cats = h( 'ul', 'nzsa-cats' );
		s.categories.forEach( function ( c ) {
			var li = h( 'li', 'nzsa-cat is-' + grade( c.points, c.max ).key );
			var line = h( 'div', 'nzsa-cat-line' );
			line.appendChild( h( 'span', '', c.label ) );
			line.appendChild( h( 'b', '', c.points + ' ' + t.outOf + ' ' + c.max ) );
			li.appendChild( line );
			var bar = h( 'div', 'nzsa-bar' );
			var fill = h( 'i' );
			fill.style.inlineSize = ( c.max ? Math.round( 100 * c.points / c.max ) : 0 ) + '%';
			bar.appendChild( fill );
			li.appendChild( bar );
			cats.appendChild( li );
		} );
		box.appendChild( cats );

		box.appendChild( h( 'h4', '', t.priorities ) );
		if ( s.top.length ) {
			var ol = h( 'ol', 'nzsa-top' );
			s.top.forEach( function ( item ) {
				var li = h( 'li' );
				var found = ( state.tree || [] ).filter( function ( n ) {
					return n.id === item.node;
				} )[ 0 ];
				if ( found ) {
					li.appendChild( h( 'b', '', found.name + ': ' ) );
				}
				li.appendChild( document.createTextNode( item.message ) );
				ol.appendChild( li );
			} );
			box.appendChild( ol );
		} else {
			box.appendChild( h( 'p', 'nzsa-muted', t.nothingTop ) );
		}

		var st = data.stats || {};
		box.appendChild( h( 'p', 'nzsa-muted', fmt( t.stats, [ st.elements, st.images, st.kb ] ) ) );
		box.appendChild( h( 'p', 'nzsa-muted', measureNote( data.measure ) ) );

		var again = h( 'button', 'nzsa-btn nzsa-btn-wide', t.recheck );
		again.type = 'button';
		again.addEventListener( 'click', function () {
			if ( ! state.busy ) {
				run();
			}
		} );
		box.appendChild( again );
		body.appendChild( box );
	}

	function render( data, message ) {
		var w = ensureWindow();
		var body = w.querySelector( '.nzsa-win-body' );
		body.textContent = '';
		w.classList.toggle( 'is-busy', state.busy );

		Array.prototype.forEach.call( w.querySelectorAll( '.nzsa-seg button' ), function ( b ) {
			b.classList.toggle( 'is-active', b.getAttribute( 'data-scope' ) === state.scope );
		} );

		var roleSelect = w.querySelector( 'select[data-nzsa="role"]' );
		if ( roleSelect ) {
			var detected = data && data.role ? roleLabel( data.role.detected ) : '…';
			roleSelect.options[ 0 ].textContent = String( t.roleAuto || '%s' ).replace( '%s', detected );
			roleSelect.value = state.role;
		}

		var chip = w.querySelector( '.nzsa-chip' );
		chip.textContent = data ? data.summary.total + '/' + data.summary.max : '';

		if ( ! data ) {
			body.appendChild( h( 'p', 'nzsa-message', message || '' ) );
			return;
		}

		renderList( body, data );
		renderSummary( body, data );
	}

	/* ---------------------------------------------------------------- *
	 * Entry point: the right-click item
	 * ---------------------------------------------------------------- */

	function review( container ) {
		if ( ! container ) {
			return;
		}
		state.id = container.id;
		state.scope = 'element';
		state.role = 'auto';
		state.data = null;
		run();
	}

	function addMenuItem( behaviors, view ) {
		if ( ! behaviors || ! behaviors.contextMenu || ! Array.isArray( behaviors.contextMenu.groups ) ) {
			return behaviors;
		}
		var groups = behaviors.contextMenu.groups;
		var group = {
			name: 'nzsa',
			actions: [ {
				name: 'nzsa-review',
				icon: 'eicon-search-results',
				title: t.menu,
				callback: function () {
					review( view.getContainer ? view.getContainer() : view.container );
				},
			} ],
		};
		var at = groups.findIndex( function ( g ) {
			return 'delete' === g.name;
		} );
		if ( at < 0 ) {
			groups.push( group );
		} else {
			groups.splice( at, 0, group );
		}
		return behaviors;
	}

	/*
	 * Register as early as Elementor allows. `elementor.hooks` only exists after the
	 * editor starts, and `elementor:init` fires AFTER the first document has been
	 * rendered, so views built before it would never get the menu item. `elementor:loaded`
	 * fires before the document opens. The flag keeps the registration to once.
	 */
	var registered = false;

	function register() {
		if ( registered || ! window.elementor || ! elementor.hooks ) {
			return;
		}
		registered = true;
		elementor.hooks.addFilter( 'elements/base/behaviors', addMenuItem );
	}

	register();
	$( window ).on( 'elementor:loaded elementor:init', register );

	// Small hook for tests and for other plugins.
	window.NZSA_EDITOR_API = { review: review, close: closeWindow };
}( jQuery ) );
