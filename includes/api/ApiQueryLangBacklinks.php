<?php
/**
 * API for MediaWiki 1.17+
 *
 * Created on May 14, 2011
 *
 * Copyright © 2011 Sam Reed
 * Copyright © 2006 Yuri Astrakhan "<Firstname><Lastname>@gmail.com"
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * This gives links pointing to the given interwiki
 * @ingroup API
 */
class ApiQueryLangBacklinks extends ApiQueryGeneratorBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'lbl' );
	}

	public function execute() {
		$this->run();
	}

	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	/**
	 * @param ApiPageSet $resultPageSet
	 * @return void
	 */
	public function run( $resultPageSet = null ) {
		$params = $this->extractRequestParams();

		if ( isset( $params['title'] ) && !isset( $params['lang'] ) ) {
			$this->dieUsageMsg( array( 'missingparam', 'lang' ) );
		}

		if ( !is_null( $params['continue'] ) ) {
			$cont = explode( '|', $params['continue'] );
			$this->dieContinueUsageIf( count( $cont ) != 3 );

			$db = $this->getDB();
			$op = $params['dir'] == 'descending' ? '<' : '>';
			$prefix = $db->addQuotes( $cont[0] );
			$title = $db->addQuotes( $cont[1] );
			$from = intval( $cont[2] );
			$this->addWhere(
				"ll_lang $op $prefix OR " .
				"(ll_lang = $prefix AND " .
				"(ll_title $op $title OR " .
				"(ll_title = $title AND " .
				"ll_from $op= $from)))"
			);
		}

		$prop = array_flip( $params['prop'] );
		$lllang = isset( $prop['lllang'] );
		$lltitle = isset( $prop['lltitle'] );

		$this->addTables( array( 'langlinks', 'page' ) );
		$this->addWhere( 'll_from = page_id' );

		$this->addFields( array( 'page_id', 'page_title', 'page_namespace', 'page_is_redirect',
			'll_from', 'll_lang', 'll_title' ) );

		$sort = ( $params['dir'] == 'descending' ? ' DESC' : '' );
		if ( isset( $params['lang'] ) ) {
			$this->addWhereFld( 'll_lang', $params['lang'] );
			if ( isset( $params['title'] ) ) {
				$this->addWhereFld( 'll_title', $params['title'] );
				$this->addOption( 'ORDER BY', 'll_from' . $sort );
			} else {
				$this->addOption( 'ORDER BY', array(
					'll_title' . $sort,
					'll_from' . $sort
				) );
			}
		} else {
			$this->addOption( 'ORDER BY', array(
				'll_lang' . $sort,
				'll_title' . $sort,
				'll_from' . $sort
			) );
		}

		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		$res = $this->select( __METHOD__ );

		$pages = array();

		$count = 0;
		$result = $this->getResult();
		foreach ( $res as $row ) {
			if ( ++$count > $params['limit'] ) {
				// We've reached the one extra which shows that there are
				// additional pages to be had. Stop here... Continue string
				// preserved in case the redirect query doesn't pass the limit.
				$this->setContinueEnumParameter(
					'continue',
					"{$row->ll_lang}|{$row->ll_title}|{$row->ll_from}"
				);
				break;
			}

			if ( !is_null( $resultPageSet ) ) {
				$pages[] = Title::newFromRow( $row );
			} else {
				$entry = array( 'pageid' => $row->page_id );

				$title = Title::makeTitle( $row->page_namespace, $row->page_title );
				ApiQueryBase::addTitleInfo( $entry, $title );

				if ( $row->page_is_redirect ) {
					$entry['redirect'] = '';
				}

				if ( $lllang ) {
					$entry['lllang'] = $row->ll_lang;
				}

				if ( $lltitle ) {
					$entry['lltitle'] = $row->ll_title;
				}

				$fit = $result->addValue( array( 'query', $this->getModuleName() ), null, $entry );
				if ( !$fit ) {
					$this->setContinueEnumParameter(
						'continue',
						"{$row->ll_lang}|{$row->ll_title}|{$row->ll_from}"
					);
					break;
				}
			}
		}

		if ( is_null( $resultPageSet ) ) {
			$result->setIndexedTagName_internal( array( 'query', $this->getModuleName() ), 'll' );
		} else {
			$resultPageSet->populateFromTitles( $pages );
		}
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams() {
		return array(
			'lang' => null,
			'title' => null,
			'continue' => array(
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			),
			'limit' => array(
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			),
			'prop' => array(
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_DFLT => '',
				ApiBase::PARAM_TYPE => array(
					'lllang',
					'lltitle',
				),
			),
			'dir' => array(
				ApiBase::PARAM_DFLT => 'ascending',
				ApiBase::PARAM_TYPE => array(
					'ascending',
					'descending'
				)
			),
		);
	}

	protected function getExamplesMessages() {
		return array(
			'action=query&list=langbacklinks&lbltitle=Test&lbllang=fr'
				=> 'apihelp-query+langbacklinks-example-simple',
			'action=query&generator=langbacklinks&glbltitle=Test&glbllang=fr&prop=info'
				=> 'apihelp-query+langbacklinks-example-generator',
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/API:Langbacklinks';
	}
}
