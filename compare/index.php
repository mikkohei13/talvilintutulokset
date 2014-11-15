<?php

class comparison
{
    public $start = "";
    public $title = "";
    public $citingHTML = "
        <p id=\"talvilintutulokset-cite\">
            <span class=\"data\">Data: <a href=\"http://www.luomus.fi/fi/talvilintulaskennat\">LUOMUS</a>, Helsingin yliopisto.</span>
            <span class=\"service\">Powered by <a href=\"https://github.com/mikkohei13/talvilintutulokset\">talvilintutulokset</a></span>
        </p>
    ";
    public $censusData = Array();

    public function __construct()
    {
        $this->start = microtime(TRUE);

        $area = (int) $_GET['area'];

        $censuses = $_GET['censuses'];
        $censusesArray = explode(",", $censuses);

        // TODO: data security
        // TODO: get JSON from subdirectory

        foreach ($censusesArray as $censusKey => $censusID)
        {
            $censusIDparts = explode("-", $censusID);
            $json = file_get_contents("http://tringa.fi/tools/talvilintutulokset-DEV/?area=$area&stats&json&year=" . $censusIDparts[0] . "&census=" . $censusIDparts[1]);
            $this->censusData[$censusID] = json_decode($json, TRUE);
        }
    }


    public function getExcecutionTime()
    {
        $end = microtime(TRUE);
        $time = $end - $this->start;
        return round($time, 3);
    }

    public function getExecutionStats()
    {
        return "<p id=\"talvilintutulokset-debug\" style=\"display: block;\">time " . $this->getExcecutionTime() . " s</p>";
    }

    public function echoComparisonGraph()
    {
        include "vernacular_names.php";

        echo "
            <style>
            .census
            {
                float: left;
            }
            pre
            {
                clear: both;
            }
            .average
            {
                font-weight: bold;
            }
            .highest
            {
                background-color: #cfc;
            }
            .higher-average
            {
                color: green;
            }
            .lower-average
            {
                color: red;
            }
            </style>
        ";

        echo "<table>";
        // Goes through each species
        foreach ($vernNames as $abbr => $name)
        {
            // Census results
            echo "<tr>";
            echo "<td>$name</td>\n";
            $c = 0;
            $averageBase = 0;
            $temp = Array();
            $highest = FALSE;
            $highestIndex = 999;

            // Goes through each census, gets the species
            foreach ($this->censusData as $censusID => $censusData)
            {
                if (isset($censusData['speciesCounts'][$name]))
                {
                    $per10km = @$censusData['speciesCounts'][$name] / ($censusData['totalLengthMeters'] / 10000);
                    $temp[$c] = round($per10km, 2);
                    if ($per10km >= $highest)
                    {
                        $highest = $per10km;
                        $highestIndex = $c;
                    }
                }
                else
                {
                    $per10km = 0;
                    $temp[$c] = "&nbsp;";
                }

                $averageBase = $averageBase + $per10km;
                $c++;
            }

            // Calculate average
            $average = round(($averageBase / $c), 2);

            // Builds HTML-table row, cell by cell
            foreach ($temp as $cKey => $cValue)
            {
                $class = "";
                if ($cKey == $highestIndex)
                {
                    $class .= "highest ";
                }
                if ($cValue > $average)
                {
                    $class .= "higher-average ";
                }
                elseif ($cValue < $average)
                {
                    $class .= "lower-average ";
                }
                echo "<td class=\"$class\">$cValue</td>\n";
            }

            echo "<td class=\"average\">$average</td>\n";
            echo "</tr>\n\n";
        }
        echo "</table>\n\n\n";

//        echo "<pre>"; print_r ($this->censusData); // debug

    }

}

// -------------------------------------------------------------------------

$comparison = new comparison();

header('Content-Type: text/html; charset=utf-8');
$comparison->echoComparisonGraph();

echo $comparison->getExecutionStats();


?>