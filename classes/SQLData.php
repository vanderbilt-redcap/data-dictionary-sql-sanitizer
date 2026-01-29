<?php

namespace VUMC\DataDictionarySQLSanitizer;

class SQLData
{
    public $pid;       // The project ID
    public $constant;  // The constant string (e.g., PID-HARMONIST1)
    public $title;     // The original PID project title
    public $sqls;      // Array of SQLs associated with the PID

    /**
    * Constructor to initialize the object.
    *
    * @param string $pid
    * @param string $constant
    * @param string $title
    * @param array $sqls
    */
    public function __construct($pid, $constant, $title, $sqls = [])
    {
        $this->pid = $pid;
        $this->constant = $constant;
        $this->title = $title;
        $this->sqls = $sqls;
    }
}
