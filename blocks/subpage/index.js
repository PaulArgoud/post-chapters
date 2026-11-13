/**
 * Paginova "Subpage" block (multipage/subpage) - editor script.
 *
 * Buildless: plain ES5 using the wp.* globals, no JSX and no build step.
 * The block metadata (title, category, attributes, supports...) lives in
 * block.json and is bootstrapped by WordPress from the server registration.
 * A copy of it is also passed to registerBlockType() so that the block still
 * registers on screens that load this script without bootstrapping the
 * server definitions (third-party or front-end editors); where both exist,
 * the server definition wins.
 *
 * @package Paginova
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
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;

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
		title: 'Chapter',
		category: 'design',
		description: 'Start a new chapter: split your post into pages, each with its own title.',
		keywords: [ 'chapter', 'multipage', 'page' ],
		textdomain: 'paginova',
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
					placeholder: __( 'Write chapter title…', 'paginova' ),
					allowedFormats: [],
					withoutInteractiveFormatting: true,
					disableLineBreaks: true,
					__unstableOnSplitAtEnd: onSplitAtEnd
				},
				blockProps
			)
		);
	}

	/**
	 * Transforms. A heading or a core page break becomes a chapter, and a chapter becomes
	 * a heading. They only change what is in the editor: save() is not concerned.
	 */
	var transforms = {
		from: [
			{
				type: 'block',
				blocks: [ 'core/heading' ],
				transform: function( attributes ) {
					// The content of a heading may be a RichTextData object: its string is the HTML.
					var content = '' + ( attributes.content || '' );
					return createBlock( metadata.name, '' === content ? {} : { title: content } );
				}
			},
			{
				type: 'block',
				blocks: [ 'core/nextpage' ],
				transform: function() {
					return createBlock( metadata.name );
				}
			}
		],
		to: [
			{
				type: 'block',
				blocks: [ 'core/heading' ],
				transform: function( attributes ) {
					return createBlock( 'core/heading', {
						level: 2,
						content: 'string' === typeof attributes.title ? attributes.title : ''
					} );
				}
			}
		]
	};

	registerBlockType( metadata, {
		// Settings override the (server or client) metadata: keep the block
		// on apiVersion 3 whatever the bootstrapped definition says.
		apiVersion: 3,
		icon: icon,
		edit: SubpageEdit,
		transforms: transforms,
		save: function( props ) {
			return el( RawHTML, null, getShortcode( props.attributes ) );
		}
	} );

	/**
	 * Chapters of a list of blocks, at any depth, in document order.
	 *
	 * @param {Array} blocks Blocks.
	 * @param {Array} found  Chapter blocks found so far.
	 * @return {Array} The Chapter blocks.
	 */
	function getChapterBlocks( blocks, found ) {
		blocks.forEach( function( block ) {
			if ( metadata.name === block.name ) {
				found.push( block );
			}
			if ( block.innerBlocks && block.innerBlocks.length ) {
				getChapterBlocks( block.innerBlocks, found );
			}
		} );

		return found;
	}

	/**
	 * Text of a title, which is HTML.
	 *
	 * @param {string} html Title.
	 * @return {string} Text.
	 */
	function plainText( html ) {
		return new window.DOMParser().parseFromString( '<body>' + html, 'text/html' ).body.textContent || '';
	}

	/**
	 * Show a block of the post in the editor canvas.
	 *
	 * @param {string} clientId Block client ID.
	 */
	function scrollToBlock( clientId ) {
		window.setTimeout( function() {
			var canvas = document.querySelector( 'iframe[name="editor-canvas"]' );
			var doc = canvas && canvas.contentDocument ? canvas.contentDocument : document;
			var node = doc.querySelector( '[data-block="' + clientId + '"]' );

			if ( node && node.scrollIntoView ) {
				node.scrollIntoView( { block: 'center' } );
			}
		}, 50 );
	}

	/**
	 * Document panel: the chapters of the post, and a button that turns the headings into chapters.
	 *
	 * @param {Object} props Props: Panel, the PluginDocumentSettingPanel component.
	 * @return {Element} The panel.
	 */
	function ChaptersPanel( props ) {
		var useState = wp.element.useState;
		var useSelect = wp.data.useSelect;
		var useDispatch = wp.data.useDispatch;
		var Button = wp.components.Button;
		var SelectControl = wp.components.SelectControl;

		var blocks = useSelect( function( select ) {
			return select( 'core/block-editor' ).getBlocks();
		}, [] );
		var levelState = useState( 2 );
		var level = levelState[ 0 ];
		var setLevel = levelState[ 1 ];
		var blockEditor = useDispatch( 'core/block-editor' );
		var notices = useDispatch( 'core/notices' );

		var chapters = getChapterBlocks( blocks, [] );
		var headings = blocks.filter( function( block ) {
			return 'core/heading' === block.name && level === block.attributes.level;
		} );

		var turnHeadingsIntoChapters = function() {
			var count = headings.length;

			// Each heading is replaced where it stands: replaceBlocks() would gather the
			// replacements at the place of the first heading, as the headings are not adjacent.
			// The changes after the first are not persistent changes of their own, so a single
			// undo restores every heading.
			headings.forEach( function( heading, index ) {
				if ( index > 0 && blockEditor.__unstableMarkNextChangeAsNotPersistent ) {
					blockEditor.__unstableMarkNextChangeAsNotPersistent();
				}

				blockEditor.replaceBlock( heading.clientId, transforms.from[ 0 ].transform( heading.attributes ) );
			} );

			notices.createSuccessNotice(
				sprintf(
					/* translators: %d: number of headings. */
					_n( '%d heading turned into a chapter. You can undo it.', '%d headings turned into chapters. You can undo it.', count, 'paginova' ),
					count
				),
				{ type: 'snackbar' }
			);
		};

		var items = chapters.map( function( chapter ) {
			var text = 'string' === typeof chapter.attributes.title ? plainText( chapter.attributes.title ) : '';

			return el(
				'li',
				{ key: chapter.clientId },
				el(
					Button,
					{
						variant: 'link',
						onClick: function() {
							blockEditor.selectBlock( chapter.clientId );
							scrollToBlock( chapter.clientId );
						}
					},
					'' !== text ? text : __( 'Untitled chapter', 'paginova' )
				)
			);
		} );

		return el(
			props.Panel,
			{ name: 'paginova', title: __( 'Chapters', 'paginova' ), className: 'mpp-chapters-panel' },
			items.length
				? el( 'ol', { className: 'mpp-chapters-list' }, items )
				: el( 'p', null, __( 'No chapters yet. Add a Chapter block where a chapter starts, or turn headings into chapters.', 'paginova' ) ),
			el( SelectControl, {
				label: __( 'Heading level', 'paginova' ),
				value: level,
				options: [
					{ label: 'H2', value: 2 },
					{ label: 'H3', value: 3 },
					{ label: 'H4', value: 4 }
				],
				onChange: function( value ) {
					setLevel( parseInt( value, 10 ) );
				},
				help: __( 'Only the headings at the top level of the post.', 'paginova' )
			} ),
			el(
				Button,
				{ variant: 'secondary', disabled: ! headings.length, onClick: turnHeadingsIntoChapters },
				headings.length
					? sprintf(
						/* translators: %d: number of headings. */
						_n( 'Turn %d heading into a chapter', 'Turn %d headings into chapters', headings.length, 'paginova' ),
						headings.length
					)
					: __( 'No heading of this level', 'paginova' )
			)
		);
	}

	// The panel needs the post editor (the Site Editor has no document settings panel).
	if ( wp.plugins && wp.plugins.registerPlugin && wp.data && wp.components && wp.domReady ) {
		wp.domReady( function() {
			var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

			if ( ! Panel ) {
				return;
			}

			wp.plugins.registerPlugin( 'paginova', {
				render: function() {
					return el( ChaptersPanel, { Panel: Panel } );
				}
			} );
		} );
	}
}( window.wp ) );
