/**
 * Multipage TinyMCE plugin.
 *
 * Adds the "Subpage" toolbar button and shows [nextpage title="..."]
 * shortcodes as placeholders in the Visual editor (and in the Classic block).
 *
 * Minified copy: plugin.min.js (regenerate with `npx -y terser`).
 *
 * @package Multipage
 * @since   1.3
 */
( function( tinymce ) {
	'use strict';

	/**
	 * Encode a plain-text title for the title="" attribute of a [nextpage] shortcode.
	 *
	 * The title is stored as HTML (like the block editor does), so & < > are
	 * encoded; " [ ] are encoded too, otherwise they would end the attribute
	 * or the shortcode (shortcode_parse_atts(), MPP_PATTERN).
	 *
	 * @param {string} text Plain text.
	 * @return {string} Encoded attribute value.
	 */
	function escapeShortcodeAttribute( text ) {
		return String( text )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /\[/g, '&#91;' )
			.replace( /\]/g, '&#93;' );
	}

	/**
	 * Build the shortcode for a title typed by the user.
	 *
	 * @param {*} title Plain-text title.
	 * @return {string} The shortcode, or an empty string when the title is empty.
	 */
	function buildShortcode( title ) {
		if ( 'string' !== typeof title ) {
			return '';
		}

		title = title.trim();
		if ( ! title.length ) {
			return '';
		}

		return '[nextpage title="' + escapeShortcodeAttribute( title ) + '"]';
	}

	/**
	 * Escape a string for a double-quoted HTML attribute.
	 *
	 * @param {string} value Raw value.
	 * @return {string} Escaped value.
	 */
	function escapeHtmlAttribute( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	/**
	 * Human readable version of a shortcode title (tags stripped, basic entities decoded).
	 * Only used for the placeholder tooltip.
	 *
	 * @param {string} value Shortcode attribute value.
	 * @return {string} Plain text.
	 */
	function toDisplayText( value ) {
		var entities = {
			'&quot;': '"',
			'&#34;': '"',
			'&#39;': '\'',
			'&#039;': '\'',
			'&#91;': '[',
			'&#93;': ']',
			'&lt;': '<',
			'&gt;': '>',
			'&nbsp;': ' ',
			'&amp;': '&'
		};

		return String( value )
			.replace( /<[^>]*>/g, '' )
			.replace( /&(?:quot|#34|#0?39|#91|#93|lt|gt|nbsp|amp);/g, function( entity ) {
				return entities[ entity ];
			} );
	}

	/**
	 * URI-encode a string so that only [A-Za-z0-9_.%-] remain, which no
	 * TinyMCE entity_encoding mode ever turns into an entity.
	 *
	 * @param {string} value Raw value.
	 * @return {string} Encoded value.
	 */
	function encodeValue( value ) {
		return encodeURIComponent( value ).replace( /[!'()*~]/g, function( chr ) {
			return '%' + chr.charCodeAt( 0 ).toString( 16 ).toUpperCase();
		} );
	}

	/**
	 * Whether a shortcode attribute value can be written back verbatim.
	 *
	 * A value restored from a placeholder attribute bypasses the TinyMCE
	 * schema (attribute values are decoded text, not parsed markup), so it
	 * must not be able to create markup (< >) nor close the shortcode ("]).
	 * Such a value can only be text, exactly like a shortcode typed by hand.
	 *
	 * @param {string} value Shortcode attribute value.
	 * @return {boolean} True when the value is safe to restore as is.
	 */
	function isSafeValue( value ) {
		return 'string' === typeof value && ! /[<>]/.test( value ) && -1 === value.indexOf( '"]' );
	}

	/**
	 * Rebuild a safe shortcode attribute value from an untrusted one
	 * (tags stripped, then & " < > [ ] encoded).
	 *
	 * @param {string} value Untrusted attribute value.
	 * @return {string} Safe attribute value.
	 */
	function sanitizeValue( value ) {
		return isSafeValue( value ) ? value : escapeShortcodeAttribute( toDisplayText( value ) );
	}

	/**
	 * Replace [nextpage title="..."] shortcodes with placeholder images.
	 *
	 * The exact attribute value is kept URI-encoded in data-mpp-title (like
	 * core does with data-wp-preserve) so that the shortcode is restored
	 * byte for byte, whatever the TinyMCE entity encoding.
	 *
	 * Shortcodes whose title contains markup (< or >) are left as text: the
	 * markup then goes through the TinyMCE schema like the rest of the content
	 * instead of being hidden from it inside an attribute.
	 *
	 * @param {string} content        Editor content.
	 * @param {string} transparentSrc Placeholder image source.
	 * @return {string} Content with placeholders.
	 */
	function toPlaceholders( content, transparentSrc ) {
		if ( ! content || -1 === content.indexOf( '[nextpage title="' ) ) {
			return content;
		}

		return content.replace( /\[nextpage title="(.*?)"\]/g, function( match, value ) {
			if ( ! isSafeValue( value ) ) {
				return match;
			}

			return '<img src="' + escapeHtmlAttribute( transparentSrc ) + '" data-wp-more="subpage" class="wp-more-tag mce-wp-subpage" ' +
				'data-mpp-title="' + encodeValue( value ) + '" ' +
				'title="' + escapeHtmlAttribute( toDisplayText( value ) ) + '" data-mce-resize="false" data-mce-placeholder="1" />';
		} );
	}

	/**
	 * Decode the value of a serialized HTML attribute (fallback for placeholders without data-mpp-title).
	 *
	 * @param {string} value Serialized attribute value.
	 * @return {string} Decoded value.
	 */
	function decodeHtmlAttribute( value ) {
		if ( tinymce && tinymce.html && tinymce.html.Entities ) {
			return tinymce.html.Entities.decode( value );
		}

		return String( value )
			.replace( /&quot;/g, '"' )
			.replace( /&#96;/g, '`' )
			.replace( /&lt;/g, '<' )
			.replace( /&gt;/g, '>' )
			.replace( /&amp;/g, '&' );
	}

	/**
	 * Replace the placeholder images with [nextpage title="..."] shortcodes.
	 *
	 * Placeholders may also come from pasted HTML, so the decoded value is
	 * only written back verbatim when it cannot create markup or end the
	 * shortcode (isSafeValue()); otherwise it is rebuilt as encoded text.
	 *
	 * @param {string} content Serialized editor content.
	 * @return {string} Content with shortcodes.
	 */
	function fromPlaceholders( content ) {
		if ( ! content || -1 === content.indexOf( 'data-wp-more="subpage"' ) ) {
			return content;
		}

		return content.replace( /<img[^>]+>/g, function( image ) {
			var match;
			var value = '';

			if ( -1 === image.indexOf( 'data-wp-more="subpage"' ) ) {
				return image;
			}

			match = image.match( /\sdata-mpp-title="([^"]*)"/ );
			if ( match ) {
				try {
					value = decodeURIComponent( decodeHtmlAttribute( match[1] ) );
				} catch ( e ) {
					value = decodeHtmlAttribute( match[1] );
				}
			} else {
				// Placeholder made by Multipage <= 1.6: the serialized title
				// attribute was restored as is (still entity-encoded).
				match = image.match( /\stitle="([^"]*)"/ );
				if ( match ) {
					value = match[1];
				}
			}

			return '[nextpage title="' + sanitizeValue( value ) + '"]';
		} );
	}

	/**
	 * TinyMCE plugin callback.
	 *
	 * @param {Object} editor TinyMCE editor instance.
	 */
	function multipagePlugin( editor ) {
		var __ = editor.editorManager.i18n.translate;

		// Register buttons.
		editor.addButton( 'subpage', {
			tooltip: __( 'Start a new Subpage' ),
			onclick: function() {
				editor.execCommand( 'MPP_Subpage' );
			}
		} );

		// Register commands.
		editor.addCommand( 'MPP_Subpage', function() {
			editor.windowManager.open( {
				title: __( 'Enter the subpage title' ),
				body: [ {
					type: 'textbox',
					name: 'title',
					label: __( 'Title' ),
					value: ''
				} ],
				onsubmit: function( event ) {
					var shortcode = buildShortcode( event.data && event.data.title );

					// Do nothing when the title is empty.
					if ( ! shortcode ) {
						return;
					}

					editor.execCommand( 'mceInsertContent', false, shortcode + '<br />' );
				}
			} );
		} );

		// Replace the subpage shortcodes with placeholder images.
		editor.on( 'BeforeSetContent', function( event ) {
			if ( event.content ) {
				event.content = toPlaceholders( event.content, tinymce.Env.transparentSrc );
			}
		} );

		// Replace the placeholder images with the subpage shortcodes.
		editor.on( 'PostProcess', function( event ) {
			if ( event.get && event.content ) {
				event.content = fromPlaceholders( event.content );
			}
		} );
	}

	// Pure helpers, exposed for tests (no global is added).
	multipagePlugin.utils = {
		escapeShortcodeAttribute: escapeShortcodeAttribute,
		buildShortcode: buildShortcode,
		escapeHtmlAttribute: escapeHtmlAttribute,
		encodeValue: encodeValue,
		toDisplayText: toDisplayText,
		isSafeValue: isSafeValue,
		sanitizeValue: sanitizeValue,
		toPlaceholders: toPlaceholders,
		decodeHtmlAttribute: decodeHtmlAttribute,
		fromPlaceholders: fromPlaceholders
	};

	if ( ! tinymce || ! tinymce.PluginManager ) {
		return;
	}

	// Set the minimum value for the modals z-index higher than #wpadminbar (100000).
	if ( tinymce.ui && tinymce.ui.FloatPanel && ( ! tinymce.ui.FloatPanel.zIndex || tinymce.ui.FloatPanel.zIndex < 100100 ) ) {
		tinymce.ui.FloatPanel.zIndex = 100100;
	}

	tinymce.PluginManager.add( 'multipage', multipagePlugin );
}( window.tinymce ) );
