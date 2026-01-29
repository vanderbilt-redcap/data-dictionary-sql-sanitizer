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

                        $pidSqlMap[$pid][] = $highlightedSql;
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

    private function replacePids($sql): array
    {
        $uniquePids = [];

        $patterns = [
            '/project_id\s*=\s*(\d+)/',   // Matches "project_id = 1234" or "project_id=1234"
            '/\[data-table:(.*?)\]/'    // Matches "[data-table:project_id]"
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $sql, $matches);
            $pidMatches = $this->arrayKeyExistsReturnValue($matches, [1, 0]);

            if (!empty($matches[1])) {
                $uniquePids = array_unique($matches[1]); // Extract unique PIDs
            }
        }

        return $uniquePids;
    }
}
