<?php

namespace VUMC\DataDictionarySQLSanitizer;

$data = htmlentities($_REQUEST,ENT_QUOTES);
error_reporting($data);