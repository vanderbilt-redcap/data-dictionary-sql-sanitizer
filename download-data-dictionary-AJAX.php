<?php

namespace VUMC\DataDictionarySQLSanitizer;

error_log("---------IN AJAX---------");
$pid = (int)$_REQUEST['pid'];
$dataDictionary = \REDCap::getDataDictionary($pid, 'array', false);

$sanitizedDataDictionary = $module->sanitizeDataDictionary($dataDictionary, json_decode($_REQUEST['sqlData'], true));

// Check if the data is valid
if (empty($sanitizedDataDictionary)) {
    die("No data available to download.");
}

// Map original headers to new headers
$headerMapping = [
    'field_name' => 'Variable / Field Name',
    'form_name' => 'Form Name',
    'section_header' => 'Section Header',
    'field_type' => 'Field Type',
    'field_label' => 'Field Label',
    'select_choices_or_calculations' => 'Choices, Calculations, OR Slider Labels',
    'field_note' => 'Field Note',
    'text_validation_type_or_show_slider_number' => 'Text Validation Type OR Show Slider Number',
    'text_validation_min' => 'Text Validation Min',
    'text_validation_max' => 'Text Validation Max',
    'identifier' => 'Identifier?',
    'branching_logic' => 'Branching Logic (Show field only if...)',
    'required_field' => 'Required Field?',
    'custom_alignment' => 'Custom Alignment',
    'question_number' => 'Question Number (surveys only)',
    'matrix_group_name' => 'Matrix Group Name',
    'matrix_ranking' => 'Matrix Ranking?',
    'field_annotation' => 'Field Annotation'
];

// Set the file name for download
$fileName = "Sanitized_".$this->getProject($pid)->getTitle()."_".date("Y-m-d_h-i",time()).".csv";

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

// Write the new headers
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
