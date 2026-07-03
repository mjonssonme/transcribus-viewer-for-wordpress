import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl, Spinner, ToggleControl } from '@wordpress/components';
import { useEffect, useMemo, useRef } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const { documentId, customHeight, viewerHeight } = attributes;
	const previewRef = useRef( null );

	// Stabilize the query object
	const query = useMemo( () => ( {
		per_page: -1,
		status: 'publish', // Only show published documents
	} ), [] );

	// The block editor's canvas renders inside an iframe, but tvwp-viewer.js's own
	// DOMContentLoaded/MutationObserver logic only watches the top-level document,
	// so it never sees ServerSideRender's output and the preview stays blank. Init
	// it directly against our ref instead (works across the iframe boundary since
	// MutationObserver/querySelectorAll operate per-node, not per-document), with
	// a short retry loop in case tvwp-viewer.js hasn't finished executing yet.
	useEffect( () => {
		if ( ! documentId || ! previewRef.current ) {
			return;
		}

		let cancelled = false;
		let attempts = 0;

		// Applied both up front and from inside tryInit below (on every DOM
		// mutation within the preview - including ones tvwp-viewer.js causes
		// itself, like drawOverlays() adding SVG children on page load), so
		// it always wins regardless of timing: without a custom height,
		// tvwp-viewer.js's own default fits the widget to the loaded image,
		// which is correct on the real page but produces a much taller
		// widget here, since the editor's narrower canvas gives the image
		// the full canvas width instead of sharing it with the text pane
		// like it does side by side on the real page. A data attribute read
		// by tvwp-viewer.js before it computes that default is set below too
		// as a first line of defense, but tvwp-viewer.js also auto-initializes
		// any .tvwp-viewer it sees via its own MutationObserver - on a truly
		// fresh block insertion that can win the race and construct the
		// widget before the attribute is even set, and the one-time init
		// guard means the flag arrives too late. Reapplying a real height
		// directly on every mutation self-heals from that regardless of
		// which side won.
		const applyHeight = () => {
			const mainContentEl = previewRef.current?.querySelector( '.tvwp-main-content' );
			if ( mainContentEl ) {
				mainContentEl.style.height = ( customHeight ? viewerHeight : 500 ) + 'px';
			}
		};

		applyHeight();

		const tryInit = () => {
			if ( cancelled || ! previewRef.current ) {
				return;
			}
			applyHeight();
			if ( typeof window.initializeViewer !== 'function' ) {
				if ( attempts++ < 20 ) {
					setTimeout( tryInit, 150 );
				}
				return;
			}
			previewRef.current
				.querySelectorAll( '.tvwp-viewer' )
				.forEach( ( el ) => {
					el.dataset.tvwpEditorPreview = 'true';
					window.initializeViewer( el );
				} );
		};

		tryInit();

		const observer = new MutationObserver( tryInit );
		// attributes/style watches for tvwp-viewer.js's own height changes
		// specifically - its default-height calculation can retry
		// asynchronously (requestAnimationFrame) if the image isn't
		// measurable on the first try, which sets a height later without
		// adding/removing any child node, so childList alone can miss it.
		observer.observe( previewRef.current, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'style' ],
		} );

		return () => {
			cancelled = true;
			observer.disconnect();
		};
	}, [ documentId, customHeight, viewerHeight ] );

	// --- THIS IS THE SIMPLIFIED FIX ---
	// We simplify the hook to *only* select the posts.
	const posts = useSelect(
		( select ) => {
			return select( 'core' ).getEntityRecords(
				'postType',
				'transkribus_document',
				query
			);
		},
		[ query ] // The only dependency is the stable query object
	);

	// We determine if it has resolved by checking if 'posts' is no longer null.
	const hasResolved = posts !== null;
	// --- END FIX ---

	// Build the options for the dropdown
	const options = [
		{ label: __( '— Select a Document —', 'tvwp' ), value: 0 },
		...( posts || [] ).map( ( post ) => ( {
			label:
				post.title.rendered ||
				__( `(ID: ${ post.id } - No Title)`, 'tvwp' ),
			value: post.id,
		} ) ),
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Document Settings', 'tvwp' ) }>
					<SelectControl
						label={ __( 'Select Document', 'tvwp' ) }
						value={ documentId }
						options={ options }
						onChange={ ( newId ) =>
							setAttributes( { documentId: parseInt( newId, 10 ) } )
						}
						disabled={ ! hasResolved }
					/>
					<ToggleControl
						label={ __( 'Custom viewer height', 'tvwp' ) }
						help={ __( 'Visitors can also drag the bottom-right corner of the image/text panes to resize them.', 'tvwp' ) }
						checked={ customHeight }
						onChange={ ( value ) => setAttributes( { customHeight: value } ) }
					/>
					{ customHeight && (
						<RangeControl
							label={ __( 'Height (px)', 'tvwp' ) }
							value={ viewerHeight }
							onChange={ ( value ) => setAttributes( { viewerHeight: value } ) }
							min={ 200 }
							max={ 1200 }
							step={ 25 }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
                { /* Show a spinner *only* while resolving */ }
				{ ! hasResolved && <Spinner /> }

                { /* Show messages if resolved but there's a problem */ }
				{ hasResolved && ! posts?.length && (
					<p>{ __( 'No published Transkribus documents found.', 'tvwp' ) }</p>
				) }
				{ hasResolved && posts?.length > 0 && documentId === 0 && (
					<p>{ __( 'Please select a document from the settings panel.', 'tvwp' ) }</p>
				) }

                { /* Show the live preview if everything is good */ }
				{ documentId > 0 && (
					<div ref={ previewRef }>
						<ServerSideRender
							block="tvwp/document-viewer"
							attributes={ attributes }
						/>
					</div>
				) }
			</div>
		</>
	);
}