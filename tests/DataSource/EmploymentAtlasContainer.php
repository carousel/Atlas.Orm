<?php
namespace Atlas\Orm\DataSource;

use Atlas\Orm\AbstractAtlasContainer;

class EmploymentAtlasContainer extends AbstractAtlasContainer
{
    protected function init()
    {
        $this->setMappers(
            Employee\EmployeeMapper::CLASS
        );
    }
}
