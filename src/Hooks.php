<?php

namespace MediaWiki\Extension\DeletePagesForGood;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Permissions\PermissionManager;
use SkinTemplate;

class Hooks implements SkinTemplateNavigation__UniversalHook {
	private DeletePagesForGood $mainService;
	private PermissionManager $permissionManager;

	public function __construct(
		DeletePagesForGood $mainService,
		PermissionManager $permissionManager
	) {
		$this->mainService = $mainService;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * This hook is called on both content and special pages
	 * after variants have been added.
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links Structured navigation links. This is used to alter the navigation for
	 *   skins which use buildNavigationUrls such as Vector.
	 * @return void This hook must not abort, it must return no value
	 * @since 1.35
	 *
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$this->permissionManager->userHasAllRights( $sktemplate->getUser(), 'delete', 'deleteperm' ) ) {
			return;
		}

		$title = $sktemplate->getRelevantTitle();
		$action = $sktemplate->getActionName();

		if ( $this->mainService->canDeleteTitle( $title ) ) {
			$links['actions']['deleteperm'] = [
				'class' => ( $action === 'deleteperm' ) ? 'selected' : false,
				'text' => $sktemplate->msg( 'deletepagesforgood-delete_permanently' )->text(),
				'href' => $title->getLocalUrl( 'action=deleteperm' ),
				'icon' => 'clear'
			];
		}
	}
}
