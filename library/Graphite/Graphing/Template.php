<?php

namespace Icinga\Module\Graphite\Graphing;

use Icinga\Module\Graphite\Util\MacroTemplate;

class Template
{
    /**
     * All curves to show in a chart by name with Graphite Web metric filters and Graphite functions
     *
     * [$curve => [$metricFilter, $function], ...]
     *
     * @var MacroTemplate[][]
     */
    protected $curves = [];

    /**
     * Additional URL parameters for rendering via Graphite Web
     *
     * [$key => $value, ...]
     *
     * @var MacroTemplate[]
     */
    protected $urlParams = [];

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Get all charts based on this template and applicable to the metrics
     * from the given data source restricted by the given filter
     *
     * @param   MetricsDataSource   $dataSource
     * @param   string[]            $filter
     *
     * @return  Chart[]
     */
    public function getCharts(MetricsDataSource $dataSource, array $filter)
    {
        $metrics = [];
        foreach ($this->curves as $curveName => $curve) {
            $query = $dataSource->select()->from($curve[0]);
            foreach ($filter as $key => $value) {
                $query->where($key, $value);
            }

            foreach ($query->fetchColumn() as $metric) {
                $vars = $curve[0]->reverseResolve($metric);
                if ($vars !== false) {
                    $metrics[$curveName][$metric] = $vars;
                }
            }
        }

        if (empty($metrics)) {
            return [];
        }

        $intersectingVariables = [];
        foreach ($metrics as $curveName1 => $_) {
            foreach ($metrics as $curveName2 => $_) {
                if ($curveName1 !== $curveName2 && ! isset($intersectingVariables[$curveName2][$curveName1])) {
                    $vars = array_intersect(
                        $this->curves[$curveName1][0]->getMacros(),
                        $this->curves[$curveName2][0]->getMacros()
                    );
                    if (! empty($vars)) {
                        $intersectingVariables[$curveName1][$curveName2] = $vars;
                    }
                }
            }
        }

        $iterState = [];
        foreach ($metrics as $curveName => $metric) {
            $iterState[$curveName] = [0, array_keys($metric)];
        }

        $metricsCombinations = [];
        $currentMetrics = [];
        do {
            foreach ($metrics as $curveName => $metric) {
                $currentMetrics[$curveName] = $iterState[$curveName][1][ $iterState[$curveName][0] ];
            }

            $acceptCombination = true;
            foreach ($intersectingVariables as $curveName1 => $intersectingWith) {
                foreach ($intersectingWith as $curveName2 => $vars) {
                    foreach ($vars as $key) {
                        if ($metrics[$curveName1][ $currentMetrics[$curveName1] ][$key]
                            !== $metrics[$curveName2][ $currentMetrics[$curveName2] ][$key]) {
                            $acceptCombination = false;
                            break 3;
                        }
                    }
                }
            }

            if ($acceptCombination) {
                $metricsCombinations[] = $currentMetrics;
            }

            $overflow = true;
            foreach ($iterState as $curveName => & $iterSubState) {
                if (isset($iterSubState[1][ ++$iterSubState[0] ])) {
                    $overflow = false;
                    break;
                } else {
                    $iterSubState[0] = 0;
                }
            }

            unset($iterSubState);
        } while (! $overflow);

        $charts = [];
        foreach ($metricsCombinations as $metricsCombination) {
            $charts[] = new Chart($dataSource->getClient(), $this, $metricsCombination);
        }

        return $charts;
    }

    /**
     * Get curves to show in a chart by name with Graphite Web metric filters and Graphite functions
     *
     * @return MacroTemplate[][]
     */
    public function getCurves()
    {
        return $this->curves;
    }

    /**
     * Set curves to show in a chart by name with Graphite Web metric filters and Graphite functions
     *
     * @param MacroTemplate[][] $curves
     *
     * @return $this
     */
    public function setCurves(array $curves)
    {
        $this->curves = $curves;

        return $this;
    }

    /**
     * Get additional URL parameters for Graphite Web
     *
     * @return MacroTemplate[]
     */
    public function getUrlParams()
    {
        return $this->urlParams;
    }

    /**
     * Set additional URL parameters for Graphite Web
     *
     * @param MacroTemplate[]  $urlParams
     *
     * @return $this
     */
    public function setUrlParams(array $urlParams)
    {
        $this->urlParams = $urlParams;

        return $this;
    }
}