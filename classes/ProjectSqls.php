<?php

namespace VUMC\DataDictionarySQLSanitizer;

class ProjectSqls
{
    public $pid;       // The project ID
    public $constant;  // The constant string (e.g., PID-HARMONIST1)
    public $projectTitle;     // The original PID project title
    public $sqlVariables;      // Array of SQLs associated with the PID

    /**
    * Constructor to initialize the object.
    *
    * @param string $pid
    * @param string $constant
    * @param string $projectTitle
    * @param array $sqlVariables
    */
    public function __construct($pid, $constant, $projectTitle, $sqlVariables = [])
    {
        $this->pid = $pid;
        $this->constant = $constant;
        $this->projectTitle = $projectTitle;
        $this->sqlVariables = $sqlVariables;
    }
}
