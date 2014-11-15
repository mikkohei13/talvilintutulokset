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
        // Species
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

            $average = round(($averageBase / $c), 2);

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
/*
        echo "<div class=\"census\">";
        // Title
        echo "<p>Laji</p>";

        // Species
        foreach ($vernNames as $abbr => $name)
        {
            // TODO: separate name list, without synonyms
            if ("break" == $name)
            {
                break;
            }

            echo "<p>$name</p>";
        }

        echo "<p>Reittien määrä</p>";
        echo "<p>Reittien yhteispituus</p>";
        echo "<p>Lajeja keskim.</p>";
        echo "<p>Yksilöitä keskim.</p>";
        echo "</div>";

        // Census results
        foreach ($this->censusData as $censusID => $censusData)
        {
            echo "<div class=\"census\">";
            // Title
            echo "<p>" . $censusID . "</p>";

            // Species
            foreach ($vernNames as $abbr => $name)
            {
                // TODO: separate name list, without synonyms
                if ("break" == $name)
                {
                    break;
                }

                if (isset($censusData['speciesCounts'][$name]))
                {
                    $count = $censusData['speciesCounts'][$name];
                }
                else
                {
                    $count = 0;
                }
                echo "<p>
                " . round(($count / ($censusData['totalLengthMeters'] / 10000)), 1) . "
                &nbsp;</p>";
            }
            echo "<p>" . $censusData['totalRoutesCount'] . "</p>";
            echo "<p>" . round(($censusData['totalLengthMeters'] / 1000), 0) . " km &nbsp;</p>";
            echo "<p>" . round($censusData['speciesAverage'], 0) . "</p>";
            echo "<p>" . round($censusData['individualAverage'], 0) . "</p>";
            echo "</div>";
        }
*/
        echo "<pre>";
//        print_r ($this->censusData);

    }

}

// -------------------------------------------------------------------------

$comparison = new comparison();

header('Content-Type: text/html; charset=utf-8');
$comparison->echoComparisonGraph();

echo $comparison->getExecutionStats();


?>