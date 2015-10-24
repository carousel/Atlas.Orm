<?php
namespace Atlas\Mapper;

use Atlas\Exception;
use Atlas\Relation\BelongsTo;
use Atlas\Relation\HasMany;
use Atlas\Relation\HasManyThrough;
use Atlas\Relation\HasOne;
use Atlas\Table\AbstractRow;
use Atlas\Table\AbstractRowSet;
use Atlas\Table\AbstractTable;
use Atlas\Table\TableSelect;

/**
 *
 * A data source mapper that returns Record and RecordSet objects.
 *
 * @package Atlas.Atlas
 *
 */
abstract class AbstractMapper
{
    protected $table;

    protected $mapperRelations;

    protected $recordFactory;

    public function __construct(
        AbstractTable $table,
        AbstractRecordFactory $recordFactory,
        MapperRelations $mapperRelations
    ) {
        $this->table = $table;
        $this->recordFactory = $recordFactory;
        $this->mapperRelations = $mapperRelations;
        $this->setMapperRelations();
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getRecordFactory()
    {
        return $this->recordFactory;
    }

    public function getMapperRelations()
    {
        return $this->mapperRelations;
    }

    protected function setMapperRelations()
    {
    }

    // row can be array or Row object
    public function newRecord($row = [])
    {
        if (is_array($row)) {
            $row = $this->getTable()->newRow($row);
        }

        return $this->recordFactory->newRecord($row, $this->mapperRelations->getFields());
    }

    // rowSet can be array of Rows, or RowSet object
    public function newRecordSetFromRows($rows)
    {
        return $this->recordFactory->newRecordSetFromRows($rows, $this->mapperRelations->getFields());
    }

    public function newRecordSet(array $records = [])
    {
        return $this->recordFactory->newRecordSet($records);
    }

    public function fetchRecord($primaryVal, array $with = [])
    {
        $row = $this->table->fetchRow($primaryVal);
        return $this->convertRow($row, $with);
    }

    public function fetchRecordBy(array $colsVals = [], array $with = [])
    {
        $row = $this->table->fetchRowBy($colsVals);
        return $this->convertRow($row, $with);
    }

    public function convertRow($row, array $with)
    {
        if (! $row) {
            return false;
        }

        $record = $this->recordFactory->newRecord($row, $this->mapperRelations->getFields());
        $this->mapperRelations->stitchIntoRecord($record, $with);
        return $record;
    }

    public function fetchRecordSet(array $primaryVals, array $with = array())
    {
        $rowSet = $this->table->fetchRowSet($primaryVals);
        return $this->convertRowSet($rowSet, $with);
    }

    public function fetchRecordSetBy(array $colsVals = [], array $with = array())
    {
        $rowSet = $this->table->fetchRowSetBy($colsVals);
        return $this->convertRowSet($rowSet, $with);
    }

    public function convertRowSet($rowSet, array $with)
    {
        if (! $rowSet) {
            return array();
        }

        $recordSet = $this->newRecordSetFromRows($rowSet);
        $this->mapperRelations->stitchIntoRecordSet($recordSet, $with);
        return $recordSet;
    }

    protected function newMapperSelect(TableSelect $tableSelect)
    {
        return new MapperSelect($this, $tableSelect);
    }

    public function select(array $colsVals = [])
    {
        $tableSelect = $this->getTable()->select($colsVals);
        return $this->newMapperSelect($tableSelect);
    }

    public function insert(AbstractRecord $record)
    {
        $this->recordFactory->assertRecordClass($record);
        return $this->getTable()->insert($record->getRow());
    }

    public function update(AbstractRecord $record)
    {
        $this->recordFactory->assertRecordClass($record);
        return $this->getTable()->update($record->getRow());
    }

    public function delete(AbstractRecord $record)
    {
        $this->recordFactory->assertRecordClass($record);
        return $this->getTable()->delete($record->getRow());
    }

    protected function hasOne($name, $foreignMapperClass)
    {
        return $this->mapperRelations->set(
            $name,
            HasOne::CLASS,
            $foreignMapperClass
        );
    }

    protected function hasMany($name, $foreignMapperClass)
    {
        return $this->mapperRelations->set(
            $name,
            HasMany::CLASS,
            $foreignMapperClass
        );
    }

    protected function belongsTo($name, $foreignMapperClass)
    {
        $this->mapperRelations->set(
            $name,
            BelongsTo::CLASS,
            $foreignMapperClass
        );
    }

    protected function hasManyThrough($name, $foreignMapperClass, $throughName)
    {
        return $this->mapperRelations->set(
            $name,
            HasManyThrough::CLASS,
            $foreignMapperClass,
            $throughName
        );
    }
}