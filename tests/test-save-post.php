<?php
/**
 * Tests of save_post: the `_mpp_data` post meta.
 *
 * @package Multipage
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_Mpp_ prefix and tests apply core and third-party hooks.

/**
 * Multipage::save_post() keeps the 1.6-compatible `_mpp_data` meta up to date.
 *
 * @group multipage
 * @group meta
 */
class Test_Mpp_Save_Post extends Mpp_UnitTestCase {

	/**
	 * Saving a multipage post writes `_mpp_data` (slug => title).
	 */
	public function test_save_post_writes_meta() {
		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo\n\n[nextpage]\n\nThree" );

		$this->assertSame(
			array(
				'intro'  => '%%intro%%',
				'deux'   => 'Deux',
				'page-3' => '',
			),
			get_post_meta( $post_id, '_mpp_data', true )
		);
		$this->assertCount( 1, get_post_meta( $post_id, '_mpp_data', false ), 'A single meta row.' );
	}

	/**
	 * Block posts too (saved through the REST API in the block editor).
	 */
	public function test_save_post_writes_meta_for_blocks() {
		$post_id = $this->create_post( Mpp_Test_Corpus::items()['blocks legacy 1.6 exact']['content'] );

		$this->assertSame(
			array(
				'intro' => '%%intro%%',
				'deux'  => 'Deux',
			),
			get_post_meta( $post_id, '_mpp_data', true )
		);
	}

	/**
	 * Backslashes in titles are preserved (update_metadata() unslashes its value).
	 */
	public function test_backslashes_in_titles_are_preserved() {
		// Shortcode attribute values go through stripcslashes() (core): a doubled backslash in the content is one backslash in the title.
		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"C:\\\\Temp\"]\n\nTwo\n\n" . Mpp_Test_Corpus::block( 'a\\b \\o/' ) . "\n\nThree" );

		$this->assertStringContainsString( 'C:\\\\Temp', get_post( $post_id )->post_content, 'The content kept its backslashes.' );

		$meta = get_post_meta( $post_id, '_mpp_data', true );
		$this->assertSame( array( '%%intro%%', 'C:\\Temp', 'a\\b \\o/' ), array_values( $meta ) );
	}

	/**
	 * The meta is updated when the subpages change, and deleted when the post is no longer multipage.
	 */
	public function test_meta_is_updated_then_deleted() {
		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => "Intro\n\n[nextpage title=\"Autre\"]\n\nTwo",
			)
		);
		$this->assertSame(
			array(
				'intro' => '%%intro%%',
				'autre' => 'Autre',
			),
			get_post_meta( $post_id, '_mpp_data', true )
		);

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => "No more subpages\n<!--nextpage-->\nCore only",
			)
		);
		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ) );
	}

	/**
	 * Plain posts get no meta.
	 */
	public function test_plain_post_has_no_meta() {
		$post_id = $this->create_post( 'Hello [[nextpage title="escaped"]]' );

		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ) );
	}

	/**
	 * Revisions are ignored.
	 */
	public function test_revisions_are_ignored() {
		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => "Intro\n\n[nextpage title=\"Trois\"]\n\nTwo",
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertNotEmpty( $revisions );

		foreach ( $revisions as $revision ) {
			$this->assertSame( array(), get_post_meta( $revision->ID, '_mpp_data', false ), 'Revision ' . $revision->ID );

			// Even when save_post is called for it explicitly.
			multipage()->save_post( $revision->ID );
			$this->assertSame( array(), get_post_meta( $revision->ID, '_mpp_data', false ) );
		}
	}

	/**
	 * Autosaves are ignored.
	 */
	public function test_autosaves_are_ignored() {
		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );

		$autosave_id = _wp_put_post_revision(
			array(
				'ID'           => $post_id,
				'post_type'    => 'post',
				'post_title'   => 'Autosave',
				'post_content' => "Intro\n\n[nextpage title=\"Autosaved\"]\n\nTwo",
				'post_author'  => 1,
			),
			true
		);

		$this->assertIsInt( $autosave_id );
		$this->assertNotFalse( wp_is_post_autosave( $autosave_id ) );

		multipage()->save_post( $autosave_id );

		$this->assertSame( array(), get_post_meta( $autosave_id, '_mpp_data', false ) );
		$this->assertSame(
			array(
				'intro' => '%%intro%%',
				'deux'  => 'Deux',
			),
			get_post_meta( $post_id, '_mpp_data', true ),
			'The parent meta is untouched.'
		);
	}

	/**
	 * Unknown post IDs do nothing.
	 */
	public function test_unknown_post_id() {
		multipage()->save_post( PHP_INT_MAX );

		$this->assertSame( array(), get_post_meta( PHP_INT_MAX, '_mpp_data', false ) );
	}

	/**
	 * Drafts and pages are indexed too.
	 */
	public function test_drafts_and_pages_are_indexed() {
		$draft_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo", array( 'post_status' => 'draft' ) );
		$page_id  = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo", array( 'post_type' => 'page' ) );

		$this->assertNotEmpty( get_post_meta( $draft_id, '_mpp_data', true ) );
		$this->assertNotEmpty( get_post_meta( $page_id, '_mpp_data', true ) );
	}
}
