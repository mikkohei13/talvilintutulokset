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

        $censuses = $_GET['censuses'];
        $censusesArray = explode(",", $censuses);

        // TODO: data security
        // TODO: area selection
        // TODO: get JSON from subdirectory

        foreach ($censusesArray as $censusKey => $censusID)
        {
            $censusIDparts = explode("-", $censusID);
            $json = file_get_contents("http://tringa.fi/tools/talvilintutulokset-DEV/?area=21&stats&json&year=" . $censusIDparts[0] . "&census=" . $censusIDparts[1]);
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
            </style>
        ";
        $tempVernNames = $vernNames;
        echo "<table>";
        foreach ($vernNames as $abbr => $name)
        {
            // Census results
            echo "<tr>";
            echo "<td>$name</td>";
            foreach ($this->censusData as $censusID => $censusData)
            {
                echo "<td>" . @$censusData['speciesCounts'][$name] . "</td>";
                /*
                $tempName = array_shift($tempVernNames);
                echo "<td>$tempName</td>";
                */
            }
            echo "</tr>";
        }
        echo "</table>";
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