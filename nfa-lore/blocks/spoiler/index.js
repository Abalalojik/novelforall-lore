/* Spoiler block (editor). No build step: uses the wp.* globals. */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;

	var chapters = ( window.nfaLoreSpoiler && window.nfaLoreSpoiler.chapters ) || [];

	function chapterLabel( id ) {
		for ( var i = 0; i < chapters.length; i++ ) {
			if ( chapters[ i ].id === id ) {
				return chapters[ i ].label;
			}
		}
		return '';
	}

	wp.blocks.registerBlockType( 'nfa-lore/spoiler', {
		edit: function ( props ) {
			var chapter = props.attributes.chapter;
			var options = [ { value: 0, label: __( '— Choose a chapter —', 'nfa-lore' ) } ].concat(
				chapters.map( function ( c ) {
					return { value: c.id, label: c.label };
				} )
			);
			var badge = chapter
				? __( 'Hidden until published:', 'nfa-lore' ) + ' ' + chapterLabel( chapter )
				: __( 'Spoiler: choose the unlocking chapter in the block settings', 'nfa-lore' );

			return el(
				'div',
				useBlockProps( { className: 'nfa-spoiler-editor' } ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Spoiler', 'nfa-lore' ) },
						el( SelectControl, {
							label: __( 'Visible from chapter', 'nfa-lore' ),
							value: chapter,
							options: options,
							onChange: function ( value ) {
								props.setAttributes( { chapter: parseInt( value, 10 ) || 0 } );
							},
						} )
					)
				),
				el( 'div', { className: 'nfa-spoiler-editor__badge' }, badge ),
				el( InnerBlocks, null )
			);
		},
		save: function () {
			return el( InnerBlocks.Content, null );
		},
	} );
} )( window.wp );
