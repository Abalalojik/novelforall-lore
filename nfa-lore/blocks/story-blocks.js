/* Story blocks (editor). Server-rendered previews; no build step. */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;

	function preview( name, attributes, emptyText ) {
		return el( ServerSideRender, {
			block: name,
			attributes: attributes,
			EmptyResponsePlaceholder: function () {
				return el( 'p', { className: 'nfa-block-placeholder' }, emptyText );
			},
		} );
	}

	wp.blocks.registerBlockType( 'nfa-lore/chapter-nav', {
		edit: function () {
			return el( 'div', useBlockProps(), el( 'p', { className: 'nfa-block-placeholder' }, __( '← Previous chapter · Contents · Next chapter →', 'nfa-lore' ) ) );
		},
		save: function () {
			return null;
		},
	} );

	wp.blocks.registerBlockType( 'nfa-lore/story-breadcrumb', {
		edit: function () {
			return el( 'div', useBlockProps(), el( 'p', { className: 'nfa-block-placeholder' }, __( 'Story › Arc › Tome', 'nfa-lore' ) ) );
		},
		save: function () {
			return null;
		},
	} );

	var stories = window.nfaStories || [];

	wp.blocks.registerBlockType( 'nfa-lore/story-contents', {
		edit: function ( props ) {
			var options = [ { value: 0, label: __( 'Current story (automatic)', 'nfa-lore' ) } ].concat(
				stories.map( function ( s ) {
					return { value: s.id, label: s.label };
				} )
			);
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Contents', 'nfa-lore' ) },
						el( SelectControl, {
							label: __( 'Story', 'nfa-lore' ),
							value: props.attributes.storyId,
							options: options,
							onChange: function ( v ) {
								props.setAttributes( { storyId: parseInt( v, 10 ) || 0 } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show arc summaries', 'nfa-lore' ),
							checked: props.attributes.showDescriptions,
							onChange: function ( v ) {
								props.setAttributes( { showDescriptions: v } );
							},
						} )
					)
				),
				preview( 'nfa-lore/story-contents', props.attributes, __( 'Story contents (shown on the story page).', 'nfa-lore' ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
