<?php

namespace VUMC\DataDictionarySQLSanitizer;

include_once(__DIR__ . "/classes/ProjectSqls.php");

use Exception;
use REDCap;
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use Twig\TwigFunction;

class DataDictionarySQLSanitizer extends AbstractExternalModule
{
    public function redcap_module_link_check_display($pid, $link)
    {
        #Only available for super users / admins
        if (!$this->isSuperUser()) {
            return false;
        }

        return parent::redcap_module_link_check_display($pid, $link);
    }
    public function initialize()
    {
        $this->initializeTwig();
        $this->loadTwigExtensions();
    }

    public function loadTwigExtensions(): void
    {
        $function = new TwigFunction('redcap_get_instrument_names', [\REDCap::class, 'getInstrumentNames']);
        $this->getTwig()->addFunction($function);

        $this->getTwig()->addGlobal('APP_PATH_WEBROOT_ALL', APP_PATH_WEBROOT_ALL);
    }

    public function sanitizeSQL($sql): string
    {
        // Remove HTML spans
        $sql = preg_replace('/<span class=\'highlight\'>(.*?)<\/span>/', '$1', $sql);

        // Normalize all whitespace (spaces, tabs, newlines)
        $sql = preg_replace('/\s+/', ' ', $sql);

        // Trim edges and convert to lowercase
        return strtolower(trim($sql));
    }

    public function getPatterns(): array
    {
        return [
            '/project_id\s*=\s*(\d+)/',   // Matches "project_id = 1234" or "project_id=1234"
            '/\[data-table:(.*?)\]/'      // Matches "[data-table:1234]"
        ];
    }

    public function getHeaders():array
    {
        return [
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
    }

    /**
     * This method processes the SQL fields within a project's data dictionary and extracts relevant information,
     * such as PIDs (Project IDs) referenced in those SQL statements. It maps each PID to its associated SQL fields
     * and highlights occurrences of the PID within the SQL strings. Additionally, it generates a list of constants
     * for each PID and returns a structured array containing all this information.
     *
     * Key Steps:
     * 1. Retrieve the project's data dictionary using the REDCap API (`REDCap::getDataDictionary`).
     * 2. Identify fields of type `SQL` and extract their `select_choices_or_calculations` values.
     * 3. Parse and extract unique PIDs from the SQL strings using the helper method `replacePids`.
     * 4. Highlight occurrences of each PID in the SQL string for better visibility in the output.
     * 5. Map each PID to its associated SQL fields and structure the data.
     * 6. Generate a constant value for each PID based on project settings and include additional metadata (e.g., project title).
     * 7. Return a structured array containing the total count of SQL fields, the unique PIDs, and their associated data.
     */
    public function sanitizeSQLFields($pid): array
    {
        $total = 0;
        $dataDictionary = REDCap::getDataDictionary($pid, 'array', false);
        if (is_array($dataDictionary) && !empty($dataDictionary)) {
            $allUniquePids = [];
            $pidSqlMap = []; // Array to store SQLs for each PID

            foreach ($dataDictionary as $row) {
                if (strtolower($row['field_type']) == "sql" && !empty($row['select_choices_or_calculations'])) {
                    $total++;

                    // Extract PIDs from the SQL
                    $uniquePids = $this->replacePids($row['select_choices_or_calculations']);

                    // Map SQLs to each PID
                    foreach ($uniquePids as $pid) {
                        if (!isset($pidSqlMap[$pid])) {
                            $pidSqlMap[$pid] = []; // Initialize array for the PID
                        }

                        // Highlight the pid in the SQL string
                        $highlightedSql = preg_replace(
                            '/\bproject_id\s*=\s*' . preg_quote($pid, '/') . '\b/',
                            '<span class=\'highlight\'>project_id = ' . $pid . '</span>',
                            $row['select_choices_or_calculations']
                        );

                        $pidSqlMap[$pid][$row['field_name']] = $highlightedSql;
                    }

                    // Keep track of all unique PIDs
                    $allUniquePids = array_unique(array_merge($allUniquePids, $uniquePids)); // Merge and deduplicate
                }
            }

            $constantArray = [];
            foreach ($allUniquePids as $index => $pid) {
                $constant = $this->getProjectSetting("pid-constant");
                $constantValue = !empty($constant) ? $constant . ($index + 1) : "";

                $constantArray[] = new ProjectSqls(
                    $pid,
                    $constantValue,
                    $this->getProject($pid)->getTitle(),
                    $pidSqlMap[$pid] ?? []
                );
            }
            $constantArray['total'] = $total;

            return $constantArray;
        }
        return [];
    }

    public function arrayKeyExistsReturnValue($array, $keys) : ?string
    {
        if (!is_array($keys)) {
            return null;
        }

        foreach ($keys as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return null;
            }
        }
        return $array;
    }

    /**
     * This method processes a project's data dictionary to sanitize SQL fields and replace specific placeholders
     * with constants derived from `projectSqls`. It ensures SQL statements within the data dictionary are properly
     * sanitized and updated with relevant dynamic values for further usage or display.
     *
     * Key Steps:
     * 1. Retrieve predefined patterns for placeholder replacement using the `getPatterns` method.
     * 2. Iterate through each field in the `dataDictionary`, focusing on fields of type `SQL`.
     * 3. Sanitize the raw SQL string using the `sanitizeSQL` method.
     * 4. Match and replace placeholders in SQL strings with constants provided in the `projectSqls` array:
     *    - Replace numeric PIDs with their corresponding constants.
     *    - Replace "project_id" placeholders with a special `[data-table:{constant}]` format.
     *    - Preserve SQL integrity while applying replacements using `preg_replace_callback`.
     * 5. Update the `select_choices_or_calculations` property of the `dataDictionary` with the sanitized and replaced SQL string.
     * 6. Return the modified `dataDictionary` for further processing or usage.
     *
     */
    public function sanitizeDataDictionary(array $dataDictionary, array $projectSqls): array
    {
        // Get the patterns for replacement
        $patterns = $this->getPatterns();

        // Loop through each field in the data dictionary
        foreach ($dataDictionary as $fieldName => &$row) {
            // Check if the field has SQL in 'select_choices_or_calculations'
            if (strtolower($row['field_type']) === "sql" && isset($row['select_choices_or_calculations']) && !empty($row['select_choices_or_calculations'])) {
                $sanitizedSql = $this->sanitizeSQL($row['select_choices_or_calculations']);

                foreach ($projectSqls as $sqlIndex => $sqlEntry) {
                    if ($sqlIndex === "total") {
                        continue; // Skip the "total" field in `$projectSqls`
                    }

                    if (isset($sqlEntry['sqlVariables']) && is_array($sqlEntry['sqlVariables'])) {
                        foreach ($sqlEntry['sqlVariables'] as $sqlKey => $sqlValue) {
                            if ($sqlKey == $row['field_name']) {
                                // Replacement logic
                                $replacement = "'" . $sqlEntry['constant'] . "'";
                                $row['select_choices_or_calculations'] = preg_replace_callback(
                                    $patterns,
                                    function ($matches) use ($sqlEntry, $replacement) {
                                        if (preg_match('/^\d+$/', $matches[1]) && (int)$matches[1] === (int)$sqlEntry['pid']) {
                                            return str_replace($matches[1], $replacement, $matches[0]);
                                        }
                                        if ($matches[1] === "project_id") {
                                            return "[data-table:{$replacement}]";
                                        }
                                        return $matches[0];
                                    },
                                    $sanitizedSql
                                );
                                break; // Exit loop once a match is found

                            }
                        }
                    }
                }
            }
        }
        return $dataDictionary;
    }

    private function replacePids($sql): array
    {
        $uniquePids = [];

        foreach ($this->getPatterns() as $pattern) {
            preg_match_all($pattern, $sql, $matches);
            $pidMatches = $this->arrayKeyExistsReturnValue($matches, [1, 0]);

            if (!empty($matches[1])) {
                $uniquePids = array_unique($matches[1]); // Extract unique PIDs
            }
        }

        return $uniquePids;
    }
}
