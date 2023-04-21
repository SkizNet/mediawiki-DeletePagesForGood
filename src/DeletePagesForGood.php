<?php

namespace MediaWiki\Extension\DeletePagesForGood;

use Title;

class DeletePagesForGood {
	/**
	 * @param Title $title
	 * @return bool
	 */
	public function canDeleteTitle( Title $title ): bool {
		return $title->getNamespace() >= 0 && ( $title->exists() || $title->hasDeletedEdits() );
	}
}
