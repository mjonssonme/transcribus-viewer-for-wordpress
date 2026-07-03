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
	//
	// The height controls are also applied here directly (not just left to the
	// server-rendered inline style) so dragging the RangeControl updates the
	// preview immediately, instead of waiting on ServerSideRender's own re-fetch.
	useEffect( () => {
		if ( ! documentId || ! previewRef.current ) {
			return;
		}

		let cancelled = false;
		let attempts = 0;

		const applyHeight = () => {
			// Set directly on the shared container (not a CSS custom property -
			// that only takes effect if the stylesheet defining var(...) is
			// loaded in this context, which isn't reliable inside the editor's
			// iframe canvas). Both panes fill it via height:100% in the
			// stylesheet, so nothing else needs updating here.
			const mainContentEl = previewRef.current?.querySelector( '.tvwp-main-content' );
			if ( ! mainContentEl ) {
				return;
			}
			mainContentEl.style.height = customHeight ? viewerHeight + 'px' : '';
		};

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
				.forEach( window.initializeViewer );
		};

		tryInit();

		const observer = new MutationObserver( tryInit );
		observer.observe( previewRef.current, { childList: true, subtree: true } );

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