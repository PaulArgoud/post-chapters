/**
 * Multipage "Subpage" block (multipage/subpage) - editor script.
 *
 * Buildless: plain ES5 using the wp.* globals, no JSX and no build step.
 * The block metadata (title, category, attributes, supports...) lives in
 * block.json and is bootstrapped by WordPress from the server registration.
 * A copy of it is also passed to registerBlockType() so that the block still
 * registers on screens that load this script without bootstrapping the
 * server definitions (third-party or front-end editors); where both exist,
 * the server definition wins.
 *
 * @package Multipage
 * @since   1.7.0
 */
( function( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor || ! wp.i18n || ! wp.primitives ) {
		return;
	}

	var el = wp.element.createElement;
	var RawHTML = wp.element.RawHTML;
	var registerBlockType = wp.blocks.registerBlockType;
	var createBlock = wp.blocks.createBlock;
	var getDefaultBlockName = wp.blocks.getDefaultBlockName;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var RichText = wp.blockEditor.RichText;
	var SVG = wp.primitives.SVG;
	var Path = wp.primitives.Path;
	var __ = wp.i18n.__;

	/**
	 * Client copy of block.json (keep both files in sync).
	 *
	 * Used only when WordPress did not bootstrap the server definition:
	 * core ignores it otherwise (the bootstrapped, PHP-translated, one wins).
	 * The "title" attribute default MUST stay `false`: save() depends on it.
	 */
	var metadata = {
		apiVersion: 3,
		name: 'multipage/subpage',
		title: 'Subpage',
		category: 'design',
		description: 'Add a subpage break to split your post into multiple pages.',
		keywords: [ 'multipage', 'page' ],
		textdomain: 'sgr-nextpage-titles',
		attributes: {
			title: {
				type: 'string',
				'default': false
			}
		},
		supports: {
			className: false,
			customClassName: false,
			html: true
		}
	};

	/**
	 * Build the [nextpage] shortcode saved inside the block.
	 *
	 * WARNING: this output must stay byte-identical to the one produced by
	 * previous Multipage versions, otherwise every existing post would show
	 * "This block contains unexpected or invalid content". Only double quotes
	 * are encoded, and non-string values are stringified as before (the
	 * attribute default is `false`, which serializes as title="false").
	 *
	 * @param {Object} attributes Block attributes.
	 * @return {string} The shortcode.
	 */
	function getShortcode( attributes ) {
		return '[nextpage title="' + ( '' + attributes.title ).replace( /"/g, '&quot;' ) + '"]';
	}

	var icon = el(
		SVG,
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24' },
		el( Path, {
			d: 'M18,2H6A2,2,0,0,0,4,4V20a2,2,0,0,0,2,2h8l6-6V4A2,2,0,0,0,18,2Zm0,6v6H14a2,2,0,0,0-2,2v4H6V4H18ZM9.8,9.4H7.5V8.1H9.8V5.8h1.3V8.1h2.2V9.4H11.1v2.3H9.8Z'
		} )
	);

	/**
	 * Editor component.
	 *
	 * @param {Object} props Block edit props.
	 * @return {Element} The editable subpage title.
	 */
	function SubpageEdit( props ) {
		var title = props.attributes.title;
		var setAttributes = props.setAttributes;
		var insertBlocksAfter = props.insertBlocksAfter;
		var blockProps = useBlockProps( { className: 'mpp-block-subpage' } );

		// Enter at the end of the title starts a new default block after it,
		// like the core Post Title block does.
		var onSplitAtEnd;
		if ( insertBlocksAfter ) {
			onSplitAtEnd = function() {
				var defaultBlockName = getDefaultBlockName();
				if ( defaultBlockName ) {
					insertBlocksAfter( createBlock( defaultBlockName ) );
				}
			};
		}

		return el(
			RichText,
			Object.assign(
				{
					identifier: 'title',
					tagName: 'h2',
					value: 'string' === typeof title ? title : '',
					onChange: function( value ) {
						setAttributes( { title: value } );
					},
					placeholder: __( 'Write subpage title…', 'sgr-nextpage-titles' ),
					allowedFormats: [],
					withoutInteractiveFormatting: true,
					disableLineBreaks: true,
					__unstableOnSplitAtEnd: onSplitAtEnd
				},
				blockProps
			)
		);
	}

	registerBlockType( metadata, {
		// Settings override the (server or client) metadata: keep the block
		// on apiVersion 3 whatever the bootstrapped definition says.
		apiVersion: 3,
		icon: icon,
		edit: SubpageEdit,
		save: function( props ) {
			return el( RawHTML, null, getShortcode( props.attributes ) );
		}
	} );
}( window.wp ) );
