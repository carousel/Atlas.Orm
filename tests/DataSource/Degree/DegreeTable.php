<?php
/**
 * This table class was generated by Atlas. Changes will be overwritten.
 */
namespace Atlas\Orm\DataSource\Degree;

use Atlas\Orm\Table\AbstractTable;

/**
 * @inheritdoc
 */
class DegreeTable extends AbstractTable
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'degrees';
    }

    /**
     * @inheritdoc
     */
    public function getColNames()
    {
        return [
            'degree_type',
            'degree_subject',
            'title',
        ];
    }

    /**
     * @inheritdoc
     */
    public function getCols()
    {
        return [
            'degree_type' => (object) [
                'name' => 'degree_type',
                'type' => 'char',
                'size' => 2,
                'scale' => null,
                'notnull' => false,
                'default' => null,
                'autoinc' => false,
                'primary' => true,
            ],
            'degree_subject' => (object) [
                'name' => 'degree_subject',
                'type' => 'char',
                'size' => 4,
                'scale' => null,
                'notnull' => false,
                'default' => null,
                'autoinc' => false,
                'primary' => true,
            ],
            'title' => (object) [
                'name' => 'title',
                'type' => 'varchar',
                'size' => 50,
                'scale' => null,
                'notnull' => false,
                'default' => null,
                'autoinc' => false,
                'primary' => false,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKey()
    {
        return [
            'degree_type',
            'degree_subject',
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAutoinc()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getColDefaults()
    {
        return [
            'degree_type' => null,
            'degree_subject' => null,
            'title' => null,
        ];
    }
}
