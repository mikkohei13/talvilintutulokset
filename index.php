<?php

class talvilinnut
{
    public $basePath = "";
	public $routeArray = Array();
	public $url = "";
	public $area = "";
    public $source = "";
    public $start = "";
    public $title = "";
    public $routesXMLarray = Array();
    public $speciesCounts = Array();
    public $totalLengthMeters = FALSE;
    public $totalRoutesCount = FALSE;
    public $speciesOnRoutes = Array();
    public $individualAverage = FALSE;
    public $speciesAverage = FALSE;
    public $routeListHTML = "";
    public $citingHTML = "
        <p id=\"talvilintutulokset-cite\">
            <span class=\"data\">Data: <a href=\"http://www.luomus.fi/fi/talvilintulaskennat\">Luomus</a>, Helsingin yliopisto.</span>
            <span class=\"service\">Powered by <a href=\"https://github.com/mikkohei13/talvilintutulokset\">talvilintutulokset</a></span>
        </p>
    ";

    public function __construct()
    {
        $this->start = microtime(TRUE);
        $this->basePath = dirname($_SERVER['REQUEST_URI']) . "/";

        // Gets fresh list of routes
        $this->createRouteListApiURL();
        $this->fetchRoutesFromCacheOrApi();
        $this->filterRouteArray();
        $this->routeListHTML = $this->getRouteList(); // this also counts averages


    	if (empty($this->routeArray))
    	{
    		exit("Tältä ajalta ei ole vielä laskentoja.");
    	}
    }

    public function createRouteListApiURL()
    {
        // Year
        if (isset($_GET['year']))
        {
            $year = (int) $_GET['year'];
        }
        else
        {
            $year = date("Y");
        }

        // Census
        if (isset($_GET['census']))
        {
            $census = (int) $_GET['census'];
        }
        else
        {
            $monthDay = ltrim(date("md"), 0);

            if ($monthDay >= 1101 && $monthDay <= 1224)
            {
                $census = 1;
                $this->title = "Syyslaskenta $year";
            }
            elseif ($monthDay >= 1225 || $monthDay <= 220)
            {
                $census = 2;
                $this->title = "Talvilaskenta $year - " . ($year + 1);
            }
            else
            {
                $census = 3;
                $this->title = "Kevätlaskenta $year";
            }
        }

        // Title
        if ($census == 1)
        {
            $this->title = "Syyslaskenta $year";
        }
        elseif ($census == 2)
        {
            $this->title = "Talvilaskenta $year - " . ($year + 1);
        }
        else
        {
            $this->title = "Kevätlaskenta $year";
        }

        $this->url = "http://koivu.luomus.fi/talvilinnut/census.php?year=$year&census=$census&json";
    }

    public function fetchRoutesFromCacheOrApi()
    {
        $filename = "cache/" . sha1($this->url) . ".json";

        if ($this->fileIsOld($filename))
        {
            $json = file_get_contents($this->url);

            if (!empty($json))
            {
                $this->routeArray = json_decode($json, TRUE);
                $this->source = $this->url;

                // Save to cache
                file_put_contents($filename, $json);
//                echo "D1";
            }
            else
            {
                // Get data from cache
                $json = file_get_contents($filename);
                $this->routeArray = json_decode($json, TRUE);
                $this->source = $filename;
//                echo "D2";
            }
        }
        else
        {
            // Get data from cache
            $json = file_get_contents($filename);

            if (!empty($json))
            {
                $this->routeArray = json_decode($json, TRUE);
                $this->source = $filename;
//                echo "D3";
            }
            else
            {
                $json = file_get_contents($this->url);
                $this->routeArray = json_decode($json, TRUE);
                $this->source = $this->url;

                // Save to cache
                file_put_contents($filename, $json);
//                echo "D4"; 
            }
        }

//        echo "debug:" . $this->url; echo "json:" . $json;
    }
    
    public function fileIsOld($filename, $hours = 1)
    {
    	// @ because file might not exist
    	if (time() - @filemtime($filename) > ($hours * 3600))
    	{
    		return TRUE;
    	}
    	else
    	{
    		return FALSE;
    	}
    }

    public function filterRouteArray()
    {
        if (isset($_GET["area"]))
        {
            $areaDirty = $_GET["area"];
        	foreach ($this->routeArray as $itemNumber => $routeData)
        	{
        		if ($areaDirty != $routeData['areaID'])
        		{
        			unset($this->routeArray[$itemNumber]);
        		}
        	}
        }

        // Sort by date desc
        usort($this->routeArray, function($a, $b) {
            return $b['date'] - $a['date'];
        });

//        print_r ($this->routeArray);

	}

    public function debug()
    {
    	echo "<pre>";
    	print_r($this->routeArray);
	}

	public function formatDate($date)
	{
		$date2 = ltrim(substr($date, 6, 2), 0) . "." . ltrim(substr($date, 4, 2), 0) . "." . substr($date, 0, 4);
		return $date2;
	}

    public function getRouteList()
    {
        $routeCount = 0;
        $individualAverageHelper = 0;
        $speciesAverageHelper = 0;
        $html = "";

        $html .= "<h4>" . $this->title . "</h4>";

    	foreach ($this->routeArray as $itemNumber => $routeData)
    	{
            // Multibyte ucfirst municipality name

            $muni = trim($routeData['municipality']);
            $muni = mb_strtolower($muni, 'UTF-8');
            $muni = mb_strtoupper(mb_substr($muni, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($muni, 1, (strlen($muni) - 1), 'UTF-8');

    		$html .= "
    		<p>
    		<span class=\"date\">" . $this->formatDate($routeData['date']) . "</span>
    		<span class=\"locality\">" . $muni . ", " . trim($routeData['grid']) .  ":</span>
    		<span class=\"speciesCount\">" . $routeData['speciesCount'] . " lajia,</span>
    		<span class=\"individualCount\">" . $routeData['individualCount'] . " yksilöä</span> 
    		<span class=\"team\"><span>(</span>" . $routeData['team'] . "<span>)</span></span>
            <a title=\"Lisätietoja Hatikassa\" href=\"http://hatikka.fi/?page=view&source=2&id=" . $routeData['documentID'] . "\">havainnot</a>
            ";

            if (isset($_GET["area"]))
            {
                $area = (int) $_GET["area"];

                // TODO: move elsewhere?
                $year = "";
                $census = "";
                if (isset($_GET['year']))
                {
                    $year = "&year=" . (int) $_GET['year'];
                }
                // Census
                if (isset($_GET['census']))
                {
                    $census = "&census=" . (int) $_GET['census'];
                }

                $html .= "<a href=\"?area=" . $area . "&document_id=" . $routeData['documentID'] . "$year$census\">tiheydet</a></p>\n";
            }

            $html .= "</p>\n";

            $routeCount++;
            $individualAverageHelper = $individualAverageHelper + $routeData['individualCount'];
            $speciesAverageHelper = $speciesAverageHelper + $routeData['speciesCount'];
    	}

        // Stop if no routes
        if (0 == $routeCount)
        {
            return;
        }

        $this->totalRoutesCount = $routeCount;
        $this->individualAverage = $individualAverageHelper / $routeCount;
        $this->speciesAverage = $speciesAverageHelper / $routeCount;

    	return $html;
	}

    public function getExcecutionTime()
    {
        $end = microtime(TRUE);
        $time = $end - $this->start;
        return round($time, 3);
    }

    public function getExecutionStats()
    {
        return "<p id=\"talvilintutulokset-debug\" style=\"display: none;\">source " . $this->source . ", time " . $this->getExcecutionTime() . " s</p>";
    }

    public function getEveryRouteData()
    {
        foreach ($this->routeArray as $itemNumber => $routeData)
        {
            $this->fetchSingleRouteDataFromCacheOrApi($routeData);
        }
//        echo "s";
    }

    public function fetchSingleRouteDataFromCacheOrApi($routeData)
    {
        $DocumentID = $routeData['documentID'];
        $filename = "cache/documentID_" . $DocumentID . ".xml";

        if ($this->fileIsOld($filename, 168))
        {
            $xml = simplexml_load_file("http://hatikka.fi/?page=view&source=2&xsl=false&id=" . $DocumentID);
            $this->routesXMLarray[$DocumentID] = $xml;

            // Save to cache
            $xml->asXml($filename);
//                file_put_contents($filename, $xml);
//                echo "<p>from Hatikka\n";
        }
        else
        {
            // Get data from cache
            $xml = simplexml_load_file($filename);
            $this->routesXMLarray[$DocumentID] = $xml;
//                echo "<p>from Cache\n";
        }
    }

    public function getStatsJSON()
    {
        $array['speciesCounts'] = $this->speciesCounts;
        $array['speciesOnRoutes'] = $this->speciesOnRoutes;

        $array['totalRoutesCount'] = $this->totalRoutesCount;
        $array['totalLengthMeters'] = $this->totalLengthMeters;
        $array['speciesAverage'] = $this->speciesAverage;
        $array['individualAverage'] = $this->individualAverage;

        return json_encode($array);
    }

    /*
    Uses data in global variable $this->routesXMLarray in FMNH2008-XML-format to calculate statistics for route(s).
    Saves data into global variables.
    */
    public function countEveryRouteStats()
    {
        $species = "";
        $count = 0;
        $i = 0;
        $totalLengthMeters = 0;

        // Goes through all routes
        foreach ($this->routesXMLarray as $routeXML)
        {
            $results = $this->parseSingleRouteXML($routeXML);

            // Add each species' count to global variable
            foreach ($results['speciesCounts'] as $sp => $temp)
            {
                @$this->speciesCounts[$sp] = $this->speciesCounts[$sp] + $results['speciesCounts'][$sp];
                @$this->speciesOnRoutes[$sp] = $this->speciesOnRoutes[$sp] + $results['speciesOnRoutes'][$sp];
            }

            $totalLengthMeters = $totalLengthMeters + $results['lengthMeters'];

            // Route count
            $i++;
        }

        // Sorting
        arsort($this->speciesCounts);

        // Harmonizing names
        $this->speciesCounts = $this->convertNames($this->speciesCounts);
        $this->speciesOnRoutes = $this->convertNames($this->speciesOnRoutes);

        // Saves stats
        $this->totalLengthMeters = $totalLengthMeters;
        $this->totalRoutesCount = $i;
    }

    public function parseSingleRouteXML($routeXML)
    {
       $dataset = $routeXML->DataSet;

        // Finds and saves route length
        foreach ($dataset->Gathering->SiteMeasurementsOrFacts->SiteMeasurementOrFact as $siteFact)
        {
            if ($siteFact->MeasurementOrFactAtomised->Parameter == "ReitinPituus")
            {
                $results['lengthMeters'] = (int) $siteFact->MeasurementOrFactAtomised->LowerValue;
            }
        }

        foreach ($dataset->Units as $unit)
        {
            // Basic and extra species from different units
            foreach ($unit as $species)
            {
                $measurement = $species->MeasurementsOrFacts->MeasurementOrFact;
                $sp = "";
                $count = "";

                // Species' information
                foreach ($measurement as $key => $atomized)
                {
                    // Species name
                    if ($atomized->MeasurementOrFactAtomised->Parameter == "InformalNameString")
                    {
                        // Harmonizes casing
                        $sp = ucfirst(strtolower((string) $atomized->MeasurementOrFactAtomised->LowerValue));
                    }
                    // Individual count
                    elseif ($atomized->MeasurementOrFactAtomised->Parameter == "Yksilömäärä")
                    {
                        $count = (int) $atomized->MeasurementOrFactAtomised->LowerValue;
                    }
                }

                // Finally saves sums to a variable
                @$speciesCounts[$sp] = $speciesCounts[$sp] + $count;
                @$speciesOnRoutes[$sp] = $speciesOnRoutes[$sp] + 1;
            }
        }

        $results['speciesCounts'] = $speciesCounts;
        $results['speciesOnRoutes'] = $speciesOnRoutes;
        return $results;
    }

    public function echoStatsGraph()
    {
        $totalLength10kms = $this->totalLengthMeters / 10000;
        $list = "";
        $i = 1;


        echo "<h4>Kokonaisyksilömäärät</h4>
            <style>
            #stats-table span
            {
                color: #aaa;
            }
            </style>
        ";
        ?>
        <canvas id="myChart" width="400" height="400"></canvas>
        <script src="<?php echo $this->basePath; ?>vendor/Chart.min.js"></script>
        <script>
        var options =
            {
                animateRotate: false,
            };
        var data = [
        <?php
        foreach ($this->speciesCounts as $species => $count)
        {
            echo "
            {
                label: \"$species\",
                value: $count,
                color:\"#f8dd38\",
                highlight: \"#d5f16d\" 
            },
            ";
            $list .= "
            <tr>
                <td class=\"number\">$i.</td> 
                <td class=\"species\">$species</td> 
                <td class=\"count\">$count 
                    <span>yksilöä</span> 
                </td>
                <td class=\"count10km\">". round(($count / $totalLength10kms), 1) . " 
                    <span>/ 10 km</span> 
                </td>
                <td class=\"routes\">" . $this->speciesOnRoutes[$species] . "
                    <span>reitillä</span> 
                </td> 
                <td class=\"routes100\">" . round(($this->speciesOnRoutes[$species] / $this->totalRoutesCount * 100), 1) . "
                    <span>%</span> 
                </td>
            </tr>
            ";
            $i++;
        }
        ?>
        ];
        // Get the context of the canvas element we want to select
        var ctx = document.getElementById("myChart").getContext("2d");
        // For a pie chart
        var myChart = new Chart(ctx).Doughnut(data,options);
        </script>
        <?php
        echo "
        <table id=\"stats-table\" class=\"sortable\">$list</table>
        <p id=\"stats-length\">"
        . $this->totalRoutesCount . " reittiä, joiden yhteispituus on <span>"
        . round(($this->totalLengthMeters / 1000), 1) . "</span> km ("
        . round(($this->totalLengthMeters / 1000 / $this->totalRoutesCount), 1) . " km / reitti).
        Reitillä keskimäärin "
        . round($this->speciesAverage, 1) . " lajia ja "
        . round($this->individualAverage, 0) . " yksilöä.
        </p>
        ";
    }

    public function convertName($from)
    {
        require "names.php";

        // Remove dots
        $from = str_replace(".", "", $from);

        // abbreviations ans scientific names
        if (isset($fullNames[$from]))
        {
            $ret = $fullNames[$from];
        }
        // abbreviations with whitespace
        elseif (isset($fullNames[str_replace(" ", "", $from)]))
        {
            $ret = $fullNames[str_replace(" ", "", $from)];
        }
        // else name is returned as is
        else
        {
            $ret = $from;
        }
        return $ret;
    }

    public function convertNames($array)
    {
        $array2 = Array();
        foreach ($array as $name => $count)
        {
            $convertedName = $this->convertName($name);
            // If exists, add to sum
            if (isset($array2[$convertedName]))
            {
                $array2[$convertedName] = $array2[$convertedName] + $count;
            }
            else
            {
                $array2[$convertedName] = $count;
            }
//            echo "$name $count $convertedName \n";
//            print_r ($array2);
        }
        return $array2;
    } 

    public function getCitingHTML()
    {
        return $this->citingHTML;
    }

    /*

    */
    public function getSingleRouteHTML()
    {
        if (empty($_GET['area']))
        {
            exit("Alue pitää valita! (area-parametri)");
        }
        $documentID = (int) $_GET['document_id'];
        $area = (int) $_GET['area'];

//        echo $documentID; // debug

        // Single route stats
        $singleRouteResults = $this->parseSingleRouteXML($this->routesXMLarray[$documentID]);

        // Delete non-information
        unset($singleRouteResults['speciesOnRoutes']);

        // Harmonizing names
        $singleRouteResults['speciesCounts'] = $this->convertNames($singleRouteResults['speciesCounts']);

/*
        // DEBUG
        print_r ($singleRouteResults);

        echo "\n<p>---------------------------------------------------------</p>\n";

        // All route stats from set area
        print_r ($this->speciesCounts);
        print_r ($this->speciesOnRoutes);
        print_r ($this->totalLengthMeters);
        echo "\n";
        print_r ($this->totalRoutesCount);
*/


        echo "
            <style>
            .pos
            {
                background-color: #cfc;
            }
            .neg
            {
                background-color: #fcc;
            }
            </style>
            <link rel=\"stylesheet\" href=\"styles.css\" type=\"text/css\" media=\"all\">
        ";

        echo "<h4>Lajien runsaus laskennassa $documentID, yksilöä / 10 km:</hr>";
        echo "<table id=\"talvilinnut-comparison-table\">";
        echo "<tr>
            <th>Laji</th>
            <th>Reitillä</th>
            <th>Alueella</th>
        </tr>";
        foreach ($singleRouteResults['speciesCounts'] as $sp => $localCount)
        {
            echo "<tr>";
            echo "<td class=\"name\">$sp</td>";

            $local = round($localCount / ($singleRouteResults['lengthMeters'] / 10000), 2);
            $area = round($this->speciesCounts[$sp] / ($this->totalLengthMeters / 10000), 2);

            $class = "";
            if ($local >= 2 * $area)
            {
                $class = "higher-average";
            }
            elseif ($local <= 0.5 * $area)
            {
                $class = "lower-average";
            }

            echo "<td class=\"$class\">$local</td>";
            echo "<td>$area</td>";
            echo "</tr>";
        }
        echo "</table>";

/*
        $i = 0;
        $c = 0;
        // Goes through all species from given area
        foreach ($this->speciesCounts as $species => $count)
        {
            $localAverage = round(($count / ($this->totalLengthMeters / 10000)), 1);

            $areaAverage = round(($areaStats['speciesCounts'][$species] / ($areaStats['totalLengthMeters'] / 10000)), 1);

            if ($localAverage < $areaAverage)
            {
                $class = "neg";
            }
            else
            {
                $class = "pos";
            }

            echo "<p>
                $species:
                $count yksilöä, eli 
                <span class=\"$class\">
                " . $localAverage . " yksilöä / 10 km</span>. 
                (ka. " . $areaAverage . " yksilöä / 10 km)
            ";

            $i++;
            $c = $c + $count;
        }

        // TODO: check why average stats are incorrect
        echo "<p>
        Reitin pituus: " . ($this->totalLengthMeters / 1000) . " km <br />
        Lajeja: " . $this->speciesAverage . " <br />
        Yksilöitä: " . $this->individualAverage . " <br />
        </p>";

        echo "<p>
        Lajeja: " . $i . " <br />
        Yksilöitä: " . $c . " <br />
        </p>";
        */
    }

    public function startHTML()
    {
        header('Content-Type: text/html; charset=utf-8');
        echo "
        <link rel=\"stylesheet\" href=\"styles.css\" type='text/css' media='all' />
        <div id=\"talvilintutulokset-main\">
        ";
    }

    public function endHTML()
    {
        echo "
        </div>
        ";
    }

}

// -------------------------------------------------------------------------

$talvilinnut = new talvilinnut();


// Stats for multiple routes
if (isset($_GET['stats']))
{
    // Count stats
    $talvilinnut->getEveryRouteData();
    $talvilinnut->countEveryRouteStats();

    // Return as JSON
    if (isset($_GET['json']))
    {
        header('Content-Type: application/json; charset=utf-8');
        echo $talvilinnut->getStatsJSON();
    }
    // Return as HTML
    else
    {
        $talvilinnut->startHTML();
        $talvilinnut->echoStatsGraph();
        echo $talvilinnut->getCitingHTML();
        echo $talvilinnut->getExecutionStats();
        $talvilinnut->endHTML();
    }
}
// Single route stats compared to multiple routes stats
elseif (isset($_GET['document_id']))
{
    // Count stats
    $talvilinnut->getEveryRouteData(); // TODO: is this needed here?
    $talvilinnut->countEveryRouteStats();

    $talvilinnut->startHTML();
    echo $talvilinnut->getSingleRouteHTML();
    $talvilinnut->endHTML();
}
// Stats for area or whole Finland, returned as HTML
else
{
    $talvilinnut->startHTML();
    echo $talvilinnut->routeListHTML;
    echo $talvilinnut->getCitingHTML();
    $talvilinnut->endHTML();
}



//$talvilinnut->debug();


?>