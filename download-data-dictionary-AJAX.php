<?php

namespace VUMC\DataDictionarySQLSanitizer;

$pid = (int)$_REQUEST['pid'];
$dataDictionary = \REDCap::getDataDictionary($pid, 'array', false);

$sanitizedDataDictionary = $module->sanitizeDataDictionary($dataDictionary, json_decode($_REQUEST['sqlData'], true));

// Check if the data is valid
if (empty($sanitizedDataDictionary)) {
    die("No data available to download.");
}

// Map original headers to new headers
$headerMapping = $module->getHeaders();

// Set the file name for download
$fileName = "Sanitized_".$module->getProject($pid)->getTitle()."_".date("Y-m-d_h-i",time()).".csv";

// Set HTTP headers to trigger file download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1
header('Pragma: no-cache'); // HTTP 1.0
header('Expires: 0'); // Proxies

// Open output stream to write directly to the browser
$output = fopen('php://output', 'w');

if (!$output) {
    die("Failed to open output stream.");
}

// Add new headers
$newHeaders = array_values($headerMapping); // Get the new header values
fputcsv($output, $newHeaders); // Write the new headers to the CSV

// Write the data rows with the same header order
foreach ($sanitizedDataDictionary as $fields) {
    // Reorder the fields to match the header order
    $row = [];
    foreach (array_keys($headerMapping) as $key) {
        $row[] = $fields[$key] ?? ''; // Use the value if it exists, otherwise empty string
    }
    fputcsv($output, $row); // Write the row to the CSV
}

// Close the output stream
fclose($output);
exit;
