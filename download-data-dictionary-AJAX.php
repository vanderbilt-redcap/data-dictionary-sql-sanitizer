<?php

namespace VUMC\DataDictionarySQLSanitizer;

error_log("---------IN AJAX---------");
$pid = (int)$_REQUEST['pid'];
$dataDictionary = \REDCap::getDataDictionary($pid, 'array', false);

$sanitizedDataDictionary = $module->sanitizeDataDictionary($dataDictionary, $_REQUEST['sqlData']);

echo "success";