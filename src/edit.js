import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const { documentId } = attributes;

	// Stabilize the query object
	const query = useMemo( () => ( {
		per_page: -1,
		status: 'publish', // Only show published documents
	} ), [] );
	
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
					<ServerSideRender
						block="tvwp/document-viewer"
						attributes={ attributes }
					/>
				) }
			</div>
		</>
	);
}