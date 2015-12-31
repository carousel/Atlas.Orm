<?php
namespace Atlas\Orm\Mapper;

use Atlas\Orm\Exception;
use Atlas\Orm\Relationship\ManyToMany;
use Atlas\Orm\Relationship\ManyToOne;
use Atlas\Orm\Relationship\OneToMany;
use Atlas\Orm\Relationship\OneToOne;
use Atlas\Orm\Relationship\Relationships;
use Atlas\Orm\Table\IdentityMap;
use Atlas\Orm\Table\RowInterface;
use Atlas\Orm\Table\TableInterface;
use Aura\Sql\ConnectionLocator;
use Aura\SqlQuery\QueryFactory;

/**
 *
 * A data source mapper that returns Record and RecordSet objects.
 *
 * @package Atlas.Atlas
 *
 */
abstract class AbstractMapper implements MapperInterface
{
    /**
     *
     * A database connection locator.
     *
     * @var ConnectionLocator
     *
     */
    protected $connectionLocator;

    /**
     *
     * A factory to create query statements.
     *
     * @var QueryFactory
     *
     */
    protected $queryFactory;

    /**
     *
     * A read connection drawn from the connection locator.
     *
     * @var ExtendedPdoInterface
     *
     */
    protected $readConnection;

    /**
     *
     * A write connection drawn from the connection locator.
     *
     * @var ExtendedPdoInterface
     *
     */
    protected $writeConnection;

    protected $table;

    protected $identityMap;

    protected $tableClass;

    protected $mapperClass;

    protected $relationships;

    protected $plugin;

    public function __construct(
        ConnectionLocator $connectionLocator,
        QueryFactory $queryFactory,
        IdentityMap $identityMap,
        TableInterface $table,
        PluginInterface $plugin,
        Relationships $relationships
    ) {
        $this->connectionLocator = $connectionLocator;
        $this->queryFactory = $queryFactory;
        $this->identityMap = $identityMap;
        $this->table = $table;
        $this->plugin = $plugin;
        $this->relationships = $relationships;

        $this->tableClass = get_class($this->table);
        $this->mapperClass = get_class($this);

        $this->setRelated();
    }

    static public function getTableClass()
    {
        static $tableClass;
        if (! $tableClass) {
            $tableClass = substr(get_called_class(), 0, -6) . 'Table';
        }
        return $tableClass;
    }

    public function getTable()
    {
        return $this->table;
    }

    /**
     *
     * Returns the database read connection.
     *
     * @return ExtendedPdoInterface
     *
     */
    public function getReadConnection()
    {
        if (! $this->readConnection) {
            $this->readConnection = $this->connectionLocator->getRead();
        }
        return $this->readConnection;
    }

    /**
     *
     * Returns the database write connection.
     *
     * @return ExtendedPdoInterface
     *
     */
    public function getWriteConnection()
    {
        if (! $this->writeConnection) {
            $this->writeConnection = $this->connectionLocator->getWrite();
        }
        return $this->writeConnection;
    }

    public function fetchRecord($primaryVal, array $with = [])
    {
        $primaryIdentity = $this->table->getPrimaryIdentity($primaryVal);

        $row = $this->identityMap->getRowByPrimary(
            $this->tableClass,
            $primaryIdentity
        );

        if ($row) {
            return $this->newRecordFromRow($row, $with);
        }

        return $this->fetchRecordBy($primaryIdentity, $with);
    }

    public function fetchRecordBy(array $colsVals = [], array $with = [])
    {
        $cols = $this
            ->select($colsVals)
            ->cols($this->table->getColNames())
            ->fetchOne();

        if (! $cols) {
            return false;
        }

        $row = $this->table->getIdentifiedOrSelectedRow($cols);
        return $this->newRecordFromRow($row, $with);
    }

    public function fetchRecordSet(array $primaryVals, array $with = [])
    {
        $rows = $this->table->identifyOrSelectRows($primaryVals, [$this, 'select']);
        if (! $rows) {
            return [];
        }
        return $this->newRecordSetFromRows($rows, $with);
    }

    public function fetchRecordSetBy(array $colsVals = [], array $with = [])
    {
        $data = $this
            ->select($colsVals)
            ->cols($this->table->getColNames())
            ->fetchAll();

        if (! $data) {
            return [];
        }

        $rows = [];
        foreach ($data as $cols) {
            $rows[] = $this->table->getIdentifiedOrSelectedRow($cols);
        }

        return $this->newRecordSetFromRows($rows, $with);
    }

    public function select(array $colsVals = [])
    {
        $select = $this->newSelect();
        $table = $this->table->getName();
        $select->from($table);
        $select->colsVals($table, $colsVals);
        return $select;
    }

    /**
     *
     * Inserts the Row for a Record.
     *
     * @param RecordInterface $record Insert the Row for this Record.
     *
     * @return bool
     *
     */
    public function insert(RecordInterface $record)
    {
        $this->plugin->beforeInsert($this, $record);

        $insert = $this->newInsert($record);
        $connection = $this->getWriteConnection();
        $pdoStatement = $connection->perform(
            $insert->getStatement(),
            $insert->getBindValues()
        );

        if (! $pdoStatement->rowCount()) {
            throw Exception::unexpectedRowCountAffected(0);
        }

        if ($this->table->getAutoinc()) {
            $primary = $this->table->getPrimaryKey();
            $record->$primary = $connection->lastInsertId($primary);
        }

        $this->plugin->afterInsert($this, $record, $insert, $pdoStatement);

        // mark as saved and retain in identity map
        $row = $record->getRow();
        $row->setStatus($row::IS_INSERTED);
        $this->identityMap->setRow($row, $row->getArrayCopy());
        return true;
    }

    /**
     *
     * Updates the Row for a Record.
     *
     * @param RecordInterface $record Update the Row for this Record.
     *
     * @return bool
     *
     */
    public function update(RecordInterface $record)
    {
        $this->plugin->beforeUpdate($this, $record);

        $update = $this->newUpdate($record);
        if (! $update->hasCols()) {
            return false;
        }

        $connection = $this->getWriteConnection();
        $pdoStatement = $connection->perform(
            $update->getStatement(),
            $update->getBindValues()
        );

        $rowCount = $pdoStatement->rowCount();
        if ($rowCount != 1) {
            throw Exception::unexpectedRowCountAffected($rowCount);
        }

        $this->plugin->afterUpdate($this, $record, $update, $pdoStatement);

        // mark as saved and retain updated identity-map data
        $row = $record->getRow();
        $row->setStatus($row::IS_UPDATED);
        $this->identityMap->setInitial($row);
        return true;
    }

    /**
     *
     * Deletes the Row for a Record.
     *
     * @param RecordInterface $record Delete the Row for this Record.
     *
     * @return bool
     *
     */
    public function delete(RecordInterface $record)
    {
        $this->plugin->beforeDelete($this, $record);

        $delete = $this->newDelete($record);
        $connection = $this->getWriteConnection();
        $pdoStatement = $connection->perform(
            $delete->getStatement(),
            $delete->getBindValues()
        );

        $rowCount = $pdoStatement->rowCount();
        if (! $rowCount) {
            return false;
        }

        if ($rowCount != 1) {
            throw Exception::unexpectedRowCountAffected($rowCount);
        }

        $this->plugin->afterDelete($this, $record, $delete, $pdoStatement);

        // mark as deleted, no need to update identity map
        $row = $record->getRow();
        $row->setStatus($row::IS_DELETED);
        return true;
    }

    public function newRecord(array $cols = [])
    {
        $row = $this->table->newRow($cols);
        $record = $this->newRecordFromRow($row);
        $this->plugin->modifyNewRecord($record);
        return $record;
    }

    public function getSelectedRecord(array $cols, array $with = [])
    {
        $row = $this->table->getIdentifiedOrSelectedRow($cols);
        return $this->newRecordFromRow($row, $with);
    }

    protected function getRecordClass(RowInterface $row)
    {
        static $recordClass;
        if (! $recordClass) {
            $recordClass = substr(get_class($this), 0, -6) . 'Record';
            $recordClass = class_exists($recordClass)
                ? $recordClass
                : Record::CLASS;
        }
        return $recordClass;
    }

    protected function getRecordSetClass()
    {
        static $recordSetClass;
        if (! $recordSetClass) {
            $recordSetClass = substr(get_class($this), 0, -6) . 'RecordSet';
            $recordSetClass = class_exists($recordSetClass)
                ? $recordSetClass
                : RecordSet::CLASS;
        }
        return $recordSetClass;
    }

    protected function newRecordFromRow(RowInterface $row, array $with = [])
    {
        $recordClass = $this->getRecordClass($row);
        $record = new $recordClass(
            $this->mapperClass,
            $row,
            $this->newRelated()
        );
        $this->relationships->stitchIntoRecord($record, $with);
        return $record;
    }

    protected function newRelated()
    {
        return new Related($this->relationships->getFields());
    }

    public function newRecordSet(array $records = [], array $with = [])
    {
        $recordSetClass = $this->getRecordSetClass();
        $recordSet = new $recordSetClass($records);
        $this->relationships->stitchIntoRecordSet($recordSet, $with);
        return $recordSet;
    }

    protected function newRecordSetFromRows(array $rows, array $with = [])
    {
        $records = [];
        foreach ($rows as $row) {
            $records[] = $this->newRecordFromRow($row);
        }
        return $this->newRecordSet($records, $with);
    }

    public function getSelectedRecordSet(array $data, array $with = [])
    {
        $records = [];
        foreach ($data as $cols) {
            $records[] = $this->getSelectedRecord($cols);
        }
        $recordSet = $this->newRecordSet($records);
        $this->relationships->stitchIntoRecordSet($recordSet, $with);
        return $recordSet;
    }

    protected function newSelect()
    {
        return new Select(
            $this->queryFactory->newSelect(),
            $this->getReadConnection(),
            $this->table->getColNames(),
            [$this, 'getSelectedRecord'],
            [$this, 'getSelectedRecordSet']
        );
    }

    protected function newInsert(RecordInterface $record)
    {
        $insert = $this->queryFactory->newInsert();
        $insert->into($this->table->getName());

        $row = $record->getRow();
        $cols = $row->getArrayCopy();
        if ($this->table->getAutoinc()) {
            unset($cols[$this->table->getPrimaryKey()]);
        }
        $insert->cols($cols);

        $this->plugin->modifyInsert($this, $record, $insert);
        return $insert;
    }

    protected function newUpdate(RecordInterface $record)
    {
        $update = $this->queryFactory->newUpdate();
        $update->table($this->table->getName());

        $row = $record->getRow();
        $cols = $row->getArrayDiff($this->identityMap->getInitial($row));
        unset($cols[$this->table->getPrimaryKey()]);
        $update->cols($cols);

        $primaryCol = $this->table->getPrimaryKey();
        $update->where("{$primaryCol} = ?", $row->getPrimary()->getVal());

        $this->plugin->modifyUpdate($this, $record, $update);
        return $update;
    }

    protected function newDelete(RecordInterface $record)
    {
        $delete = $this->queryFactory->newDelete();
        $delete->from($this->table->getName());

        $row = $record->getRow();
        $primaryCol = $this->table->getPrimaryKey();
        $delete->where("{$primaryCol} = ?", $row->getPrimary()->getVal());

        $this->plugin->modifyDelete($this, $record, $delete);
        return $delete;
    }

    protected function setRelated()
    {
    }

    protected function oneToOne($name, $foreignMapperClass)
    {
        return $this->relationships->set(
            get_class($this),
            $name,
            OneToOne::CLASS,
            $foreignMapperClass
        );
    }

    protected function oneToMany($name, $foreignMapperClass)
    {
        return $this->relationships->set(
            get_class($this),
            $name,
            OneToMany::CLASS,
            $foreignMapperClass
        );
    }

    protected function manyToOne($name, $foreignMapperClass)
    {
        return $this->relationships->set(
            get_class($this),
            $name,
            ManyToOne::CLASS,
            $foreignMapperClass
        );
    }

    protected function manyToMany($name, $foreignMapperClass, $throughName)
    {
        return $this->relationships->set(
            get_class($this),
            $name,
            ManyToMany::CLASS,
            $foreignMapperClass,
            $throughName
        );
    }
}
