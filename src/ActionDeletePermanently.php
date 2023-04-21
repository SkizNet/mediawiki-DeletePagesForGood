<?php

namespace MediaWiki\Extension\DeletePagesForGood;

use Article;
use FormAction;
use HTMLForm;
use IContextSource;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Page\DeletePageFactory;
use RepoGroup;
use Status;
use StatusValue;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

class ActionDeletePermanently extends FormAction {
	private DeletePagesForGood $mainService;
	private DeletePageFactory $deletePageFactory;
	private ILoadBalancer $db;
	private JobQueueGroupFactory $jobQueueGroupFactory;
	private RepoGroup $repoGroup;

	public function __construct(
		Article $article,
		IContextSource $context,
		DeletePagesForGood $mainService,
		DeletePageFactory $deletePageFactory,
		ILoadBalancer $db,
		JobQueueGroupFactory $jobQueueGroupFactory,
		RepoGroup $repoGroup
	) {
		$this->mainService = $mainService;
		$this->deletePageFactory = $deletePageFactory;
		$this->db = $db;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->repoGroup = $repoGroup;
		parent::__construct( $article, $context );
	}

	/** @inheritDoc */
	public function getName() {
		return 'deleteperm';
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/** @inheritDoc */
	public function getDescription() {
		return '';
	}

	/** @inheritDoc */
	protected function usesOOUI() {
		return true;
	}

	/**
	 * @param mixed $data
	 * @return StatusValue|string[]
	 */
	public function onSubmit( $data ) {
		if ( $this->mainService->canDeleteTitle( $this->getTitle() ) ) {
			return $this->deletePermanently( $this->getTitle() );
		}

		return [ 'deletepagesforgood-del_impossible' ];
	}

	/**
	 * @param Title $title
	 * @return StatusValue
	 */
	public function deletePermanently( Title $title ) {
		$dbw = $this->db->getConnection( DB_PRIMARY );
		$namespace = $title->getNamespace();
		$dbKey = $title->getDBkey();
		$file = false;

		if ( $namespace == NS_FILE ) {
			$file = $this->repoGroup->findFile(
				$title,
				[
					'latest' => true,
					'ignoreRedirect' => true,
					'private' => $this->getUser()
				] );
		}

		// do a regular delete to get all revisions into archive and so extensions can do their own cleanup
		if ( $title->exists() ) {
			$deletePage = $this->deletePageFactory->newDeletePage( $title->toPageIdentity(), $this->getAuthority() );
			$deletePage->forceImmediate( true );
			$status = $deletePage->deleteIfAllowed( '' );
			if ( !$status->isOK() ) {
				// delete failed
				return $status;
			}
		}

		if ( $file !== false ) {
			$status = $file->deleteFile( '', $this->getUser() );
			if ( !$status->isOK() ) {
				return $status;
			}

			$res = $dbw->newSelectQueryBuilder()
				->select( [ 'fa_id', 'fa_storage_group', 'fa_storage_key', 'fa_name' ] )
				->from( 'filearchive' )
				->where( [ 'fa_name' => $file->getName() ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			$paths = [];
			$repo = $file->getRepo();

			foreach ( $res as $row ) {
				$key = $row->fa_storage_key;
				if ( !strlen( $key ) ) {
					continue;
				}

				$paths[] = $repo->getZonePath( 'deleted' ) .
					'/' . $repo->getDeletedHashPath( $key ) . $key;
			}

			$status = $file->getRepo()->quickPurgeBatch( $paths );
			if ( !$status->isOK() ) {
				return $status;
			}

			$dbw->delete(
				'filearchive',
				[ 'fa_name' => $file->getName() ],
				__METHOD__
			);
		}

		// now purge it from the archive
		$dbw->delete(
			'archive',
			[
				'ar_namespace' => $namespace,
				'ar_title' => $dbKey
			],
			__METHOD__
		);

		// now purge logs
		$dbw->delete(
			'logging',
			[
				'log_namespace' => $namespace,
				'log_title' => $dbKey
			],
			__METHOD__
		);

		$job = new DeletePermanentlyJob( $title, [] );
		$this->jobQueueGroupFactory->makeJobQueueGroup()->push( $job );

		return Status::newGood();
	}

	/**
	 * Returns the name that goes in the \<h1\> page title
	 *
	 * @return string
	 */
	protected function getPageTitle() {
		return $this->msg( 'deletepagesforgood-deletepagetitle', $this->getTitle()->getPrefixedText() );
	}

	/**
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		$title = $this->getTitle();
		$output = $this->getOutput();

		$output->addBacklinkSubtitle( $title );
		$form->addPreHtml( $this->msg( 'confirmdeletetext' )->parseAsBlock() );
		$form->addPreHtml(
			$this->msg( 'deletepagesforgood-ask_deletion' )->parseAsBlock()
		);

		$form->setSubmitTextMsg( 'deletepagesforgood-yes' );
	}

	/** @inheritDoc */
	public function getRestriction() {
		return 'deleteperm';
	}

	/**
	 * @return void
	 */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'deletepagesforgood-del_done' );
	}
}
