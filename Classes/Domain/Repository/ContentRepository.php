<?php
namespace TYPO3\CMS\Vidi\Domain\Repository;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Fabien Udriot <fabien.udriot@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Repository for accessing Content
 */
class ContentRepository implements \TYPO3\CMS\Extbase\Persistence\RepositoryInterface {

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseHandle;

	/**
	 * Tell whether it is a raw result (array) or object being returned.
	 *
	 * @var bool
	 */
	protected $rawResult = FALSE;

	/**
	 * The data type to be returned, e.g fe_users, fe_groups, tt_content, etc...
	 * @var string
	 */
	protected $dataType;

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 */
	protected $objectManager;

	/**
	 * @var \TYPO3\CMS\Vidi\Persistence\QuerySettings
	 */
	protected $querySettings;

	/**
	 * Constructor
	 *
	 * @param string $dataType
	 */
	public function __construct($dataType) {
		$this->dataType = $dataType;
		$this->databaseHandle = $GLOBALS['TYPO3_DB'];
		$this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Object\ObjectManager');
	}

	/**
	 * Update a content with new information.
	 *
	 * @throws \TYPO3\CMS\Vidi\Exception\MissingUidException
	 * @param \TYPO3\CMS\Vidi\Domain\Model\Content $content
	 * @return void
	 */
	public function update($content) {
		if ($content->getUid() <= 0) {
			throw new \TYPO3\CMS\Vidi\Exception\MissingUidException('Missing Uid', 1351605542);
		}

		$data[$content->getDataType()][$content->getUid()] = $content->toArray();

		/** @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
		$tce = $this->objectManager->get('TYPO3\CMS\Core\DataHandling\DataHandler');
		$tce->start($data, array());
		$tce->process_datamap();
	}

	/**
	 * Returns all objects of this repository.
	 *
	 * @return \TYPO3\CMS\Vidi\Domain\Model\Content[]
	 */
	public function findAll() {
		$query = $this->createQuery();
		return $query->execute();
	}

	/**
	 * Returns all objects with unique value for a given property.
	 *
	 * @return \TYPO3\CMS\Vidi\Domain\Model\Content[]
	 */
	public function findDistinctValues($propertyName) {
		$query = $this->createQuery();
		$constraint = $query->logicalNot($query->equals($propertyName, ''));
		$query->setDistinct($propertyName)
			->matching($constraint);

		return $query->execute();
	}

	/**
	 * Finds an object matching the given identifier.
	 *
	 * @param int $uid The identifier of the object to find
	 * @return \TYPO3\CMS\Vidi\Domain\Model\Content The matching object
	 * @api
	 */
	public function findByUid($uid) {
		return $this->findByIdentifier($uid);
	}

	/**
	 * Finds all Contents given specified matches.
	 *
	 * @param string $propertyName
	 * @param array $values
	 * @return \TYPO3\CMS\Vidi\Domain\Model\Content[]
	 */
	public function findIn($propertyName, array $values) {
		$query = $this->createQuery();
		$query->matching($query->in($propertyName, $values));
		return $query->execute();
	}

	/**
	 * Finds all Contents given specified matches.
	 *
	 * @param \TYPO3\CMS\Vidi\Persistence\Matcher $matcher
	 * @param \TYPO3\CMS\Vidi\Persistence\Order $order The order
	 * @param int $limit
	 * @param int $offset
	 * @return \TYPO3\CMS\Vidi\Domain\Model\Content[]
	 */
	public function findBy(\TYPO3\CMS\Vidi\Persistence\Matcher $matcher, \TYPO3\CMS\Vidi\Persistence\Order $order = NULL, $limit = NULL, $offset = NULL) {

		$query = $this->createQuery();

		$constraints = $this->computeConstraints($query, $matcher);

		if ($constraints) {
			$query->matching($constraints);
		}

		if ($limit) {
			$query->setLimit($limit);
		}

		if ($order) {
			$query->setOrderings($order->getOrderings());
		}

		if ($offset) {
			$query->setOffset($offset);
		}

		return $query->execute();
	}

	/**
	 * Get the constraints
	 *
	 * @param \TYPO3\CMS\Vidi\Persistence\Query $query
	 * @param \TYPO3\CMS\Vidi\Persistence\Matcher $matcher
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\Constraint|NULL
	 */
	protected function computeConstraints(\TYPO3\CMS\Vidi\Persistence\Query $query, \TYPO3\CMS\Vidi\Persistence\Matcher $matcher) {

		$result = NULL;

		$constraints = array();

		// Search term
		$constraint = $this->computeSearchTermConstraint($query, $matcher);
		if ($constraint) {
			$constraints[] = $constraint;
		}

		foreach ($matcher->getSupportedOperators() as $operator) {
			$constraint = $this->computeConstraint($query, $matcher, $operator);
			if ($constraint) {
				$constraints[] = $constraint;
			}
		}

		if (count($constraints) > 1) {
			$logical = $matcher->getDefaultLogicalSeparator();
			$result = $query->$logical($constraints);
		} elseif(!empty($constraints)) {
			// true means there is one constraint only
			$result = current($constraints);
		}
		return $result;
	}

	/**
	 * Computes the search constraint and returns it.
	 *
	 * @param \TYPO3\CMS\Vidi\Persistence\Query $query
	 * @param \TYPO3\CMS\Vidi\Persistence\Matcher $matcher
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\Constraint|NULL
	 */
	protected function computeSearchTermConstraint(\TYPO3\CMS\Vidi\Persistence\Query $query, \TYPO3\CMS\Vidi\Persistence\Matcher $matcher) {

		$result = NULL;

		// Search term case
		if ($matcher->getSearchTerm()) {

			$tcaTableService = \TYPO3\CMS\Vidi\Tca\TcaServiceFactory::getTableService($this->dataType);
			$tcaFieldService = \TYPO3\CMS\Vidi\Tca\TcaServiceFactory::getFieldService($this->dataType);
			$fields = explode(',', $tcaTableService->getSearchFields());

			$constraints = array();
			$likeClause = sprintf('%%%s%%', $matcher->getSearchTerm());
			foreach ($fields as $field) {
				if ($tcaFieldService->hasRelation($field)) {
					$foreignTable = $tcaFieldService->getForeignTable($field);
					$foreignTcaTableService = \TYPO3\CMS\Vidi\Tca\TcaServiceFactory::getTableService($foreignTable);
					$field = $field . '.' . $foreignTcaTableService->getLabelField();
				}
				$constraints[] = $query->like($field, $likeClause);
			}
			$logical = $matcher->getLogicalSeparatorForSearchTerm();
			$result = $query->$logical($constraints);
		}

		return $result;
	}

	/**
	 * Computes the constraint for matches and returns it.
	 *
	 * @param \TYPO3\CMS\Vidi\Persistence\Query $query
	 * @param \TYPO3\CMS\Vidi\Persistence\Matcher $matcher
	 * @param string $operator
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\Qom\Constraint|NULL
	 */
	protected function computeConstraint(\TYPO3\CMS\Vidi\Persistence\Query $query, \TYPO3\CMS\Vidi\Persistence\Matcher $matcher, $operator) {
		$result = NULL;

		$operatorName = ucfirst($operator);
		$getCriteria = sprintf('get%sCriteria', $operatorName);
		$criteria = $matcher->$getCriteria();

		if (!empty($criteria)) {
			$constraints = array();

			$tcaFieldService = \TYPO3\CMS\Vidi\Tca\TcaServiceFactory::getFieldService($this->dataType);
			foreach ($criteria as $criterion) {

				$field = $criterion['propertyName'];
				$operand = $criterion['operand'];
				if ($tcaFieldService->hasRelation($field) && is_numeric($operand)) {
					$field = $field . '.uid';
				} elseif ($tcaFieldService->hasRelation($field)) {
					$foreignTable = $tcaFieldService->getForeignTable($field);
					$foreignTcaTableService = \TYPO3\CMS\Vidi\Tca\TcaServiceFactory::getTableService($foreignTable);
					$field = $field . '.' . $foreignTcaTableService->getLabelField();
				}
				$constraints[] = $query->$operator($field, $criterion['operand']);
			}

			$getLogicalSeparator = sprintf('getLogicalSeparatorFor%s', $operatorName);
			$logical = $matcher->$getLogicalSeparator();
			$result = $query->$logical($constraints);
		}

		return $result;
	}

	/**
	 * Count all Contents given specified matches.
	 *
	 * @param \TYPO3\CMS\Vidi\Persistence\Matcher $matcher
	 * @return int
	 */
	public function countBy(\TYPO3\CMS\Vidi\Persistence\Matcher $matcher) {

		$query = $this->createQuery();

		$constraints = $this->computeConstraints($query, $matcher);

		if ($constraints) {
			$query->matching($constraints);
		}

		return $query->count();
	}

	/**
	 * Removes an object from this repository.
	 *
	 * @param \TYPO3\CMS\Vidi\Domain\Model\Content $content The object to remove
	 * @return boolean
	 */
	public function remove($content) {
		$result = FALSE;
		if ($content) {

			// Build command
			$cmd[$content->getDataType()][$content->getUid()]['delete'] = 1;

			/** @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
			$tce = $this->objectManager->get('TYPO3\CMS\Core\DataHandling\DataHandler');
			$tce->start(array(), $cmd);
			$tce->process_datamap();
			$tce->process_cmdmap();
			$result = TRUE;
		}
		return $result;
	}

	/**
	 * Dispatches magic methods (findBy[Property]())
	 *
	 * @param string $methodName The name of the magic method
	 * @param string $arguments The arguments of the magic method
	 * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedMethodException
	 * @return mixed
	 * @api
	 */
	public function __call($methodName, $arguments) {
		if (substr($methodName, 0, 6) === 'findBy' && strlen($methodName) > 7) {
			$propertyName = strtolower(substr(substr($methodName, 6), 0, 1)) . substr(substr($methodName, 6), 1);
			$result = $this->processMagicCall($propertyName, $arguments[0]);
		} elseif (substr($methodName, 0, 9) === 'findOneBy' && strlen($methodName) > 10) {
			$propertyName = strtolower(substr(substr($methodName, 9), 0, 1)) . substr(substr($methodName, 9), 1);
			$result = $this->processMagicCall($propertyName, $arguments[0], 'one');
		} elseif (substr($methodName, 0, 7) === 'countBy' && strlen($methodName) > 8) {
			$propertyName = strtolower(substr(substr($methodName, 7), 0, 1)) . substr(substr($methodName, 7), 1);
			$result = $this->processMagicCall($propertyName, $arguments[0], 'count');
		} else {
			throw new \TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedMethodException('The method "' . $methodName . '" is not supported by the repository.', 1360838010);
		}
		return $result;
	}

	/**
	 * Returns a query for objects of this repository
	 *
	 * @return \TYPO3\CMS\Vidi\Persistence\Query
	 * @api
	 */
	public function createQuery() {
		/** @var \TYPO3\CMS\Vidi\Persistence\Query $query */
		$query = $this->objectManager->get('TYPO3\CMS\Vidi\Persistence\Query', $this->dataType);

		// Initialize and pass the query settings at this level.
		$this->querySettings = $this->objectManager->get('TYPO3\CMS\Vidi\Persistence\QuerySettings');

		// Default choice for the BE.
		$this->querySettings->setIgnoreEnableFields(TRUE);
		$query->setQuerySettings($this->querySettings);

		return $query;
	}

	/**
	 * Returns a matcher object for this repository
	 *
	 * @return \TYPO3\CMS\Vidi\Persistence\Matcher
	 * @return object
	 */
	public function createMatch() {
		return $this->objectManager->get('TYPO3\CMS\Vidi\Persistence\Matcher', array(), $this->dataType);
	}

	/**
	 * @return boolean
	 */
	public function getRawResult() {
		return $this->rawResult;
	}

	/**
	 * @param boolean $rawResult
	 * @return \TYPO3\CMS\Vidi\Domain\Repository\ContentRepository
	 */
	public function setRawResult($rawResult) {
		$this->rawResult = $rawResult;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getDataType() {
		return $this->dataType;
	}

	/**
	 * Handle the magic call by properly creating a Query object and returning its result.
	 *
	 * @param string $field
	 * @param string $value
	 * @param string $flag
	 * @return array
	 */
	protected function processMagicCall($field, $value, $flag = '') {

		/** @var $matcher \TYPO3\CMS\Vidi\Persistence\Matcher */
		$matcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Vidi\Persistence\Matcher');

		$tcaFieldService = \TYPO3\CMS\Vidi\Tca\TcaServiceFactory::getFieldService($this->dataType);
		if ($tcaFieldService->isGroup($field)) {

			$valueParts = explode('.', $value, 2);
			$field = $field . '.' . $valueParts[0];
			$value = $valueParts[1];
		}

		$matcher->equals($field, $value);

		if ($flag == 'count') {
			$result = $this->countBy($matcher);
		} else {
			$result = $this->findBy($matcher);
		}
		return $flag == 'one' && !empty($result) ? reset($result) : $result;
	}

	/**
	 * Adds an object to this repository.
	 *
	 * @param object $object The object to add
	 * @throws \BadMethodCallException
	 * @return void
	 * @api
	 */
	public function add($object) {
		throw new \BadMethodCallException('Repository does not support the add() method.', 1375805599);
	}

	/**
	 * Returns the total number objects of this repository.
	 *
	 * @return integer The object count
	 * @api
	 */
	public function countAll() {
		// TODO: Implement countAll() method.
	}

	/**
	 * Removes all objects of this repository as if remove() was called for
	 * all of them.
	 *
	 * @return void
	 * @api
	 */
	public function removeAll() {
		// TODO: Implement removeAll() method.
	}

	/**
	 * Finds an object matching the given identifier.
	 *
	 * @param mixed $identifier The identifier of the object to find
	 * @return object The matching object if found, otherwise NULL
	 * @api
	 */
	public function findByIdentifier($identifier) {
		$query = $this->createQuery();

		$result = $query->matching($query->equals('uid', $identifier))
			->execute();

		if (is_array($result)) {
			$result = current($result);
		}

		return $result;
	}

	/**
	 * Sets the property names to order the result by per default.
	 * Expected like this:
	 * array(
	 * 'foo' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
	 * 'bar' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING
	 * )
	 *
	 * @param array $defaultOrderings The property names to order by
	 * @throws \BadMethodCallException
	 * @return void
	 * @api
	 */
	public function setDefaultOrderings(array $defaultOrderings) {
		throw new \BadMethodCallException('Repository does not support the setDefaultOrderings() method.', 1375805598);
	}

	/**
	 * Sets the default query settings to be used in this repository
	 *
	 * @param \TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface $defaultQuerySettings The query settings to be used by default
	 * @throws \BadMethodCallException
	 * @return void
	 * @api
	 */
	public function setDefaultQuerySettings(\TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface $defaultQuerySettings) {
		throw new \BadMethodCallException('Repository does not support the setDefaultQuerySettings() method.', 1375805597);
	}
}

?>