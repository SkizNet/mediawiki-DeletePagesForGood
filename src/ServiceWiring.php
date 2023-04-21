<?php

use MediaWiki\Extension\DeletePagesForGood\DeletePagesForGood;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'DeletePagesForGood' => static function ( MediaWikiServices $services ): DeletePagesForGood {
		return new DeletePagesForGood();
	}
];
