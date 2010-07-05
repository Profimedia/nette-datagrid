<?php

namespace DataGrid\DataSources\Doctrine;

use Doctrine\ORM\QueryBuilder;

/**
 * Query Builder based data source
 * @author Michael Moravec
 * @author Štěpán Svoboda
 */
class QueryBuilder extends Mapped
{

	/** @var Doctrine\ORM\QueryBuilder */
	private $qb;

	/**
	 * @param QueryBuilder $qb
	 */
	public function __construct(QueryBuilder $qb)
	{
		$this->qb = $qb;
	}

	public function filter($column, $value, $type = self::EQUAL, $chainType = NULL)
	{
		$nextParamId = count($this->qb->getParameters()) + 1;

		if (is_array($type)) {
			if ($chainType !== self::CHAIN_AND && $chainType !== self::CHAIN_OR) {
				throw new \InvalidArgumentException('Invalid chain operation type.');
			}
			$conds = array();
			$paramUsed = FALSE;
			foreach ($type as $t) {
				$this->validateFilterOperation($t);
				if ($t === self::IS_NULL || $t === self::IS_NOT_NULL) {
					$conds[] = "$column $t";
				} else {
					$conds[] = "$column $t ?$nextParamId";
					$paramUsed = TRUE;
				}
			}

			if ($chainType === self::CHAIN_AND) {
				foreach ($conds as $cond) {
					$this->qb->andWhere($cond);
				}
			} elseif ($chainType === self::CHAIN_OR) {
				$this->qb->andWhere(new Expr\Orx($conds));
			}

			$paramUsed && $this->qb->setParameter($nextParamId++, $value);
		} else {
			$this->validateFilterOperation($type);

			if ($type === self::IS_NULL || $type === self::IS_NOT_NULL) {
				$this->qb->andWhere("$column $type");
			} else {
				$this->qb->andWhere("$column $type ?$nextParamId")->setParameter($nextParamId, $value);
			}
		}
	}

	public function sort($column, $order = self::ASCENDING)
	{
		$this->qb->addOrderBy($column, $order === self::ASCENDING ? 'ASC' : 'DESC');
	}

	public function reduce($count, $start = 0)
	{
		if ($count < 1 || $start < 0 || $start >= count($this)) {
			throw new \OutOfRangeException;
		}
		$this->qb->setMaxResults($count)->setFirstResult($start);
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->qb->getQuery()->getScalarResult());
	}

	public function count()
	{
		$query = clone $this->qb->getQuery();

		$query->setHint(Doctrine\ORM\Query::HINT_CUSTOM_TREE_WALKERS, array(__NAMESPACE__ . '\Utils\CountingASTWalker'));
		$query->setMaxResults(NULL)->setFirstResult(NULL);

		return (int) $query->getSingleScalarResult();
	}
}