<?php

namespace VUMC\DataDictionarySQLSanitizer;

include_once(__DIR__ . "/classes/SQLData.php");

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

    public function sanitizeSQLFields($pid): ?array
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

                $constantArray[] = new SQLData(
                    $pid,
                    $constantValue,
                    $this->getProject($pid)->getTitle(),
                    $pidSqlMap[$pid] ?? []
                );
            }
            $constantArray['total'] = $total;

            return $constantArray;
        }
        return null;
    }

    public function arrayKeyExistsReturnValue($array, $keys) {
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

    public function sanitizeDataDictionary(array $dataDictionary, array $sqlData): array
    {
        // Get the patterns for replacement
        $patterns = $this->getPatterns();

        // Loop through each field in the data dictionary
        foreach ($dataDictionary as $fieldName => &$row) {
            // Check if the field has SQL in 'select_choices_or_calculations'
            if (strtolower($row['field_type']) === "sql" && isset($row['select_choices_or_calculations']) && !empty($row['select_choices_or_calculations'])) {
                $sanitizedSql = $this->sanitizeSQL($row['select_choices_or_calculations']);

                foreach ($sqlData as $sqlIndex => $sqlEntry) {
                    if ($sqlIndex === "total") {
                        continue; // Skip the "total" field in `$sqlData`
                    }

                    if (isset($sqlEntry['sqls']) && is_array($sqlEntry['sqls'])) {
                        foreach ($sqlEntry['sqls'] as $sqlKey => $sqlValue) {
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
