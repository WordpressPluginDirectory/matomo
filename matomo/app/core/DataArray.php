<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik;

use Exception;
use Piwik\DataTable\Filter\EnrichRecordWithGoalMetricSums;
use Piwik\Tracker\GoalManager;
/**
 * The DataArray is a data structure used to aggregate datasets,
 * ie. sum arrays made of rows made of columns,
 * data from the logs is stored in a DataArray before being converted in a DataTable
 *
 */
class DataArray
{
    protected $data = array();
    protected $dataTwoLevels = array();
    public function __construct($data = array(), $dataArrayByLabel = array())
    {
        $this->data = $data;
        $this->dataTwoLevels = $dataArrayByLabel;
    }
    /**
     * This returns the actual raw data array
     *
     * @return array
     */
    public function &getDataArray()
    {
        return $this->data;
    }
    public function getDataArrayWithTwoLevels()
    {
        return $this->dataTwoLevels;
    }
    public function sumMetricsVisits($label, $row)
    {
        if (!isset($this->data[$label])) {
            $this->data[$label] = static::makeEmptyRow();
        }
        $this->doSumVisitsMetrics($row, $this->data[$label]);
    }
    /**
     * Returns an empty row containing default metrics
     *
     * @return array
     */
    public static function makeEmptyRow()
    {
        return array(\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS => 0, \Piwik\Metrics::INDEX_NB_VISITS => 0, \Piwik\Metrics::INDEX_NB_ACTIONS => 0, \Piwik\Metrics::INDEX_NB_USERS => 0, \Piwik\Metrics::INDEX_MAX_ACTIONS => 0, \Piwik\Metrics::INDEX_SUM_VISIT_LENGTH => 0, \Piwik\Metrics::INDEX_BOUNCE_COUNT => 0, \Piwik\Metrics::INDEX_NB_VISITS_CONVERTED => 0);
    }
    /**
     * Adds the given row $newRowToAdd to the existing  $oldRowToUpdate passed by reference
     * The rows are php arrays Name => value
     *
     * @param array $newRowToAdd
     * @param array $oldRowToUpdate
     * @param bool $onlyMetricsAvailableInActionsTable
     *
     * @return void
     */
    protected function doSumVisitsMetrics($newRowToAdd, &$oldRowToUpdate)
    {
        // Pre 1.2 format: string indexed rows are returned from the DB
        // Left here for Backward compatibility with plugins doing custom SQL queries using these metrics as string
        if (!isset($newRowToAdd[\Piwik\Metrics::INDEX_NB_VISITS])) {
            $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_VISITS] += $newRowToAdd['nb_visits'];
            $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_ACTIONS] += $newRowToAdd['nb_actions'];
            $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS] += $newRowToAdd['nb_uniq_visitors'];
            $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_USERS] += $newRowToAdd['nb_users'];
            $oldRowToUpdate[\Piwik\Metrics::INDEX_MAX_ACTIONS] = (float) max($newRowToAdd['max_actions'], $oldRowToUpdate[\Piwik\Metrics::INDEX_MAX_ACTIONS]);
            $oldRowToUpdate[\Piwik\Metrics::INDEX_SUM_VISIT_LENGTH] += $newRowToAdd['sum_visit_length'];
            $oldRowToUpdate[\Piwik\Metrics::INDEX_BOUNCE_COUNT] += $newRowToAdd['bounce_count'];
            $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_VISITS_CONVERTED] += $newRowToAdd['nb_visits_converted'];
            return;
        }
        // Edge case fail safe
        if (!isset($oldRowToUpdate[\Piwik\Metrics::INDEX_NB_VISITS])) {
            return;
        }
        $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_VISITS] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_VISITS];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_ACTIONS] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_ACTIONS];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS];
        // In case the existing Row had no action metrics (eg. Custom Variable XYZ with "visit" scope)
        // but the new Row has action metrics (eg. same Custom Variable XYZ this time with a "page" scope)
        if (!isset($oldRowToUpdate[\Piwik\Metrics::INDEX_MAX_ACTIONS])) {
            $toZero = array(\Piwik\Metrics::INDEX_NB_USERS, \Piwik\Metrics::INDEX_MAX_ACTIONS, \Piwik\Metrics::INDEX_SUM_VISIT_LENGTH, \Piwik\Metrics::INDEX_BOUNCE_COUNT, \Piwik\Metrics::INDEX_NB_VISITS_CONVERTED);
            foreach ($toZero as $metric) {
                $oldRowToUpdate[$metric] = 0;
            }
        }
        $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_USERS] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_USERS];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_MAX_ACTIONS] = (float) max($newRowToAdd[\Piwik\Metrics::INDEX_MAX_ACTIONS], $oldRowToUpdate[\Piwik\Metrics::INDEX_MAX_ACTIONS]);
        $oldRowToUpdate[\Piwik\Metrics::INDEX_SUM_VISIT_LENGTH] += $newRowToAdd[\Piwik\Metrics::INDEX_SUM_VISIT_LENGTH];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_BOUNCE_COUNT] += $newRowToAdd[\Piwik\Metrics::INDEX_BOUNCE_COUNT];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_VISITS_CONVERTED] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_VISITS_CONVERTED];
    }
    /**
     * Adds the given row $newRowToAdd to the existing  $oldRowToUpdate passed by reference
     * The rows are php arrays Name => value
     *
     * @param array $newRowToAdd
     * @param array $oldRowToUpdate
     * @param bool $onlyMetricsAvailableInActionsTable
     *
     * @return void
     */
    protected function doSumActionsMetrics($newRowToAdd, &$oldRowToUpdate)
    {
        // Pre 1.2 format: string indexed rows are returned from the DB
        // Left here for Backward compatibility with plugins doing custom SQL queries using these metrics as string
        if (!isset($newRowToAdd[\Piwik\Metrics::INDEX_NB_VISITS])) {
            $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_VISITS] += $newRowToAdd['nb_visits'];
            $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_ACTIONS] += $newRowToAdd['nb_actions'];
            $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS] += $newRowToAdd['nb_uniq_visitors'];
            return;
        }
        // Edge case fail safe
        if (!isset($oldRowToUpdate[\Piwik\Metrics::INDEX_NB_VISITS])) {
            return;
        }
        $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_VISITS] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_VISITS];
        if (array_key_exists(\Piwik\Metrics::INDEX_NB_ACTIONS, $newRowToAdd)) {
            $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_ACTIONS] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_ACTIONS];
        }
        if (array_key_exists(\Piwik\Metrics::INDEX_PAGE_NB_HITS, $newRowToAdd)) {
            if (!array_key_exists(\Piwik\Metrics::INDEX_PAGE_NB_HITS, $oldRowToUpdate)) {
                $oldRowToUpdate[\Piwik\Metrics::INDEX_PAGE_NB_HITS] = 0;
            }
            $oldRowToUpdate[\Piwik\Metrics::INDEX_PAGE_NB_HITS] += $newRowToAdd[\Piwik\Metrics::INDEX_PAGE_NB_HITS];
        }
        $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS];
    }
    public function sumMetricsGoals($label, $row)
    {
        $idGoal = $row['idgoal'];
        if (!isset($this->data[$label][\Piwik\Metrics::INDEX_GOALS][$idGoal])) {
            $this->data[$label][\Piwik\Metrics::INDEX_GOALS][$idGoal] = static::makeEmptyGoalRow($idGoal);
        }
        $this->doSumGoalsMetrics($row, $this->data[$label][\Piwik\Metrics::INDEX_GOALS][$idGoal]);
    }
    /**
     * @param $idGoal
     * @return array
     */
    protected static function makeEmptyGoalRow($idGoal)
    {
        if ($idGoal > GoalManager::IDGOAL_ORDER) {
            return array(\Piwik\Metrics::INDEX_GOAL_NB_CONVERSIONS => 0, \Piwik\Metrics::INDEX_GOAL_NB_VISITS_CONVERTED => 0, \Piwik\Metrics::INDEX_GOAL_REVENUE => 0);
        }
        if ($idGoal == GoalManager::IDGOAL_ORDER) {
            return array(\Piwik\Metrics::INDEX_GOAL_NB_CONVERSIONS => 0, \Piwik\Metrics::INDEX_GOAL_NB_VISITS_CONVERTED => 0, \Piwik\Metrics::INDEX_GOAL_REVENUE => 0, \Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL => 0, \Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_TAX => 0, \Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING => 0, \Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT => 0, \Piwik\Metrics::INDEX_GOAL_ECOMMERCE_ITEMS => 0);
        }
        // idGoal == GoalManager::IDGOAL_CART
        return array(\Piwik\Metrics::INDEX_GOAL_NB_CONVERSIONS => 0, \Piwik\Metrics::INDEX_GOAL_NB_VISITS_CONVERTED => 0, \Piwik\Metrics::INDEX_GOAL_REVENUE => 0, \Piwik\Metrics::INDEX_GOAL_ECOMMERCE_ITEMS => 0);
    }
    /**
     *
     * @param $newRowToAdd
     * @param $oldRowToUpdate
     */
    protected function doSumGoalsMetrics($newRowToAdd, &$oldRowToUpdate)
    {
        $oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_NB_CONVERSIONS] += $newRowToAdd[\Piwik\Metrics::INDEX_GOAL_NB_CONVERSIONS];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_NB_VISITS_CONVERTED] += $newRowToAdd[\Piwik\Metrics::INDEX_GOAL_NB_VISITS_CONVERTED];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_REVENUE] += $newRowToAdd[\Piwik\Metrics::INDEX_GOAL_REVENUE];
        // Cart & Order
        if (isset($oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_ITEMS])) {
            $oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_ITEMS] += $newRowToAdd[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_ITEMS];
            // Order only
            if (isset($oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL])) {
                $oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL] += $newRowToAdd[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL];
                $oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_TAX] += $newRowToAdd[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_TAX];
                $oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING] += $newRowToAdd[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING];
                $oldRowToUpdate[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT] += $newRowToAdd[\Piwik\Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT];
            }
        }
    }
    public function sumMetricsActions($label, $row)
    {
        if (!isset($this->data[$label])) {
            $this->data[$label] = static::makeEmptyActionRow();
        }
        $this->doSumActionsMetrics($row, $this->data[$label]);
    }
    protected static function makeEmptyActionRow()
    {
        return array(\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS => 0, \Piwik\Metrics::INDEX_NB_VISITS => 0, \Piwik\Metrics::INDEX_NB_ACTIONS => 0);
    }
    public function sumMetricsEvents($label, $row)
    {
        if (!isset($this->data[$label])) {
            $this->data[$label] = static::makeEmptyEventRow();
        }
        $this->doSumEventsMetrics($row, $this->data[$label], $onlyMetricsAvailableInActionsTable = \true);
    }
    protected static function makeEmptyEventRow()
    {
        return array(\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS => 0, \Piwik\Metrics::INDEX_NB_VISITS => 0, \Piwik\Metrics::INDEX_EVENT_NB_HITS => 0, \Piwik\Metrics::INDEX_EVENT_NB_HITS_WITH_VALUE => 0, \Piwik\Metrics::INDEX_EVENT_SUM_EVENT_VALUE => 0, \Piwik\Metrics::INDEX_EVENT_MIN_EVENT_VALUE => \false, \Piwik\Metrics::INDEX_EVENT_MAX_EVENT_VALUE => 0);
    }
    public const EVENT_VALUE_PRECISION = 2;
    /**
     * @param array $newRowToAdd
     * @param array $oldRowToUpdate
     * @return void
     */
    protected function doSumEventsMetrics($newRowToAdd, &$oldRowToUpdate)
    {
        $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_VISITS] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_VISITS];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS] += $newRowToAdd[\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_EVENT_NB_HITS] += $newRowToAdd[\Piwik\Metrics::INDEX_EVENT_NB_HITS];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_EVENT_NB_HITS_WITH_VALUE] += $newRowToAdd[\Piwik\Metrics::INDEX_EVENT_NB_HITS_WITH_VALUE];
        $newRowToAdd[\Piwik\Metrics::INDEX_EVENT_SUM_EVENT_VALUE] = round($newRowToAdd[\Piwik\Metrics::INDEX_EVENT_SUM_EVENT_VALUE], static::EVENT_VALUE_PRECISION);
        $oldRowToUpdate[\Piwik\Metrics::INDEX_EVENT_SUM_EVENT_VALUE] += $newRowToAdd[\Piwik\Metrics::INDEX_EVENT_SUM_EVENT_VALUE];
        $oldRowToUpdate[\Piwik\Metrics::INDEX_EVENT_MAX_EVENT_VALUE] = round(max(0, $newRowToAdd[\Piwik\Metrics::INDEX_EVENT_MAX_EVENT_VALUE], $oldRowToUpdate[\Piwik\Metrics::INDEX_EVENT_MAX_EVENT_VALUE]), static::EVENT_VALUE_PRECISION);
        // Update minimum only if it is set
        if ($newRowToAdd[\Piwik\Metrics::INDEX_EVENT_MIN_EVENT_VALUE] !== \false && $newRowToAdd[\Piwik\Metrics::INDEX_EVENT_MIN_EVENT_VALUE] !== null) {
            if ($oldRowToUpdate[\Piwik\Metrics::INDEX_EVENT_MIN_EVENT_VALUE] === \false) {
                $oldRowToUpdate[\Piwik\Metrics::INDEX_EVENT_MIN_EVENT_VALUE] = round($newRowToAdd[\Piwik\Metrics::INDEX_EVENT_MIN_EVENT_VALUE], static::EVENT_VALUE_PRECISION);
            } else {
                $oldRowToUpdate[\Piwik\Metrics::INDEX_EVENT_MIN_EVENT_VALUE] = round(min($newRowToAdd[\Piwik\Metrics::INDEX_EVENT_MIN_EVENT_VALUE], $oldRowToUpdate[\Piwik\Metrics::INDEX_EVENT_MIN_EVENT_VALUE]), static::EVENT_VALUE_PRECISION);
            }
        }
    }
    /**
     * Generic function that will sum all columns of the given row, at the specified label's row.
     *
     * @param $label
     * @param $row
     * @throws Exception if the the data row contains non numeric values
     */
    public function sumMetrics($label, $row)
    {
        foreach ($row as $columnName => $columnValue) {
            if (empty($columnValue)) {
                continue;
            }
            if (empty($this->data[$label][$columnName])) {
                $this->data[$label][$columnName] = 0;
            }
            if (!is_numeric($columnValue)) {
                throw new Exception("DataArray->sumMetricsPivot expects rows of numeric values, non numeric found: " . var_export($columnValue, \true) . " for column {$columnName}");
            }
            $this->data[$label][$columnName] += $columnValue;
        }
    }
    public function sumMetricsVisitsPivot($parentLabel, $label, $row)
    {
        if (!isset($this->dataTwoLevels[$parentLabel][$label])) {
            $this->dataTwoLevels[$parentLabel][$label] = static::makeEmptyRow();
        }
        $this->doSumVisitsMetrics($row, $this->dataTwoLevels[$parentLabel][$label]);
    }
    public function sumMetricsGoalsPivot($parentLabel, $label, $row)
    {
        $idGoal = $row['idgoal'];
        if (!isset($this->dataTwoLevels[$parentLabel][$label][\Piwik\Metrics::INDEX_GOALS][$idGoal])) {
            $this->dataTwoLevels[$parentLabel][$label][\Piwik\Metrics::INDEX_GOALS][$idGoal] = static::makeEmptyGoalRow($idGoal);
        }
        $this->doSumGoalsMetrics($row, $this->dataTwoLevels[$parentLabel][$label][\Piwik\Metrics::INDEX_GOALS][$idGoal]);
    }
    public function sumMetricsActionsPivot($parentLabel, $label, $row)
    {
        if (!isset($this->dataTwoLevels[$parentLabel][$label])) {
            $this->dataTwoLevels[$parentLabel][$label] = $this->makeEmptyActionRow();
        }
        $this->doSumActionsMetrics($row, $this->dataTwoLevels[$parentLabel][$label]);
    }
    public function sumMetricsEventsPivot($parentLabel, $label, $row)
    {
        if (!isset($this->dataTwoLevels[$parentLabel][$label])) {
            $this->dataTwoLevels[$parentLabel][$label] = $this->makeEmptyEventRow();
        }
        $this->doSumEventsMetrics($row, $this->dataTwoLevels[$parentLabel][$label]);
    }
    public function setRowColumnPivot($parentLabel, $label, $column, $value)
    {
        $this->dataTwoLevels[$parentLabel][$label][$column] = $value;
    }
    public function enrichMetricsWithConversions()
    {
        $this->enrichWithConversions($this->data);
        foreach ($this->dataTwoLevels as &$metricsBySubLabel) {
            $this->enrichWithConversions($metricsBySubLabel);
        }
    }
    /**
     * Given an array of stats, it will process the sum of goal conversions
     * and sum of revenue and add it in the stats array in two new fields.
     *
     * @param array $data Passed by reference, two new columns
     *              will be added: total conversions, and total revenue, for all goals for this label/row
     */
    protected function enrichWithConversions(&$data)
    {
        foreach ($data as &$values) {
            EnrichRecordWithGoalMetricSums::enrichWithConversions($values);
        }
    }
    /**
     * Returns true if the row looks like an Action metrics row
     *
     * @param $row
     * @return bool
     */
    public static function isRowActions($row)
    {
        return count($row) == count(static::makeEmptyActionRow()) && isset($row[\Piwik\Metrics::INDEX_NB_ACTIONS]);
    }
    /**
     * Converts array to a datatable
     *
     * @return \Piwik\DataTable
     */
    public function asDataTable()
    {
        $dataArray = $this->getDataArray();
        $dataArrayTwoLevels = $this->getDataArrayWithTwoLevels();
        $subtableByLabel = null;
        if (!empty($dataArrayTwoLevels)) {
            $subtableByLabel = array();
            foreach ($dataArrayTwoLevels as $label => $subTable) {
                $subtableByLabel[$label] = \Piwik\DataTable::makeFromIndexedArray($subTable);
            }
        }
        return \Piwik\DataTable::makeFromIndexedArray($dataArray, $subtableByLabel);
    }
}
