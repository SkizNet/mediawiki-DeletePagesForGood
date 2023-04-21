<?php

namespace MediaWiki\Extension\DeletePagesForGood;

use Job;

class DeletePermanentlyJob extends Job {
	public function __construct( $title, $params ) {
		parent::__construct( 'deletePermanently', $title, $params );
	}

	/**
	 * Run the job
	 * @return bool Success
	 */
	public function run() {
		// Don't make this into a `use` statement; we want autoloader to run from this function scope
		$rebuildRC = new \RebuildRecentchanges();

		// Delete orphaned text records from the DB
		$rebuildRC->purgeRedundantText( true );

		// Rebuild RecentChanges
		$rebuildRC->execute();

		return true;
	}
}
