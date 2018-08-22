<?php

namespace Dynamic\BlockMigration\Tools;

/**
 * Class LinkableManipulator
 * @package Dynamic\BlockMigration\Tools
 */
class LinkableManipulator
{
    /**
     * @var
     */
    private $records;

    /**
     * LinkableManipulator constructor.
     * @param $records
     */
    public function __construct($records)
    {
        $this->setRecords($records);
    }

    /**
     * @param $records
     * @return $this
     */
    public function setRecords($records)
    {
        $this->records = $records;

        return $this;
    }
}