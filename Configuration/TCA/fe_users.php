<?php
if (!defined('TYPO3_MODE')) die ('Access denied.');

$tca = array(
	'ctrl' => array(
		'searchFields' => $GLOBALS['TCA']['fe_users']['ctrl']['searchFields'] . ',usergroup',
	),
	'grid' => array(
		'facets' => array(
			'uid',
			'username',
			'name',
			'first_name',
			'last_name',
			'middle_name',
			'address',
			'telephone',
			'fax',
			'email',
			'title',
			'zip',
			'city',
			'country',
			'company',
			'usergroup',
		),
		'columns' => array(
			'__checkbox' => array(
				'width' => '5px',
				'sortable' => FALSE,
				'html' => '<input type="checkbox" class="checkbox-row-top"/>',
			),
			'uid' => array(
				'visible' => FALSE,
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:uid',
				'width' => '5px',
			),
			'username' => array(
				'visible' => TRUE,
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:username',
				'editable' => TRUE,
			),
			'name' => array(
				'visible' => TRUE,
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:name',
				'editable' => TRUE,
			),
			'email' => array(
				'visible' => TRUE,
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:email',
				'editable' => TRUE,
			),
			'usergroup' => array(
				'visible' => TRUE,
				'renderers' => array(
					'TYPO3\CMS\Vidi\GridRenderer\RelationCreate',
					'TYPO3\CMS\Vidi\GridRenderer\Relation',
				),
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/fe_users.xlf:usergroup',
			),
			'tstamp' => array(
				'visible' => FALSE,
				'format' => 'date',
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/locallang.xlf:tstamp',
			),
			'crdate' => array(
				'visible' => FALSE,
				'format' => 'date',
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/locallang.xlf:crdate',
			),
			'disable' => array(
				'renderer' => 'TYPO3\CMS\Vidi\GridRenderer\Visibility',
				'label' => 'LLL:EXT:vidi/Resources/Private/Language/locallang.xlf:visibility_abbreviation',
				'width' => '3%',
			),
			'__buttons' => array(
				'sortable' => FALSE,
				'width' => '70px',
			),
		)
	)
);

return \TYPO3\CMS\Core\Utility\GeneralUtility::array_merge_recursive_overrule($GLOBALS['TCA']['fe_users'], $tca);
?>