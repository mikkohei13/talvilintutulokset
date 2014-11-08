<?php
header('Content-Type: text/html; charset=utf-8');

class talvilinnut
{
    public $basePath = "";
	public $resultArray = Array();
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

    public function __construct()
    {
        $this->start = microtime(TRUE);
        $this->basePath = dirname($_SERVER['REQUEST_URI']) . "/";

        // Gets fresh list of routes
        $this->createRouteListApiURL();
        $this->fetchDataFromCacheOrApi();
        $this->filterRouteList();

    	if (empty($this->resultArray))
    	{
    		exit("Tältä ajalta ei ole vielä laskentoja.");
    	}
    }

    public function createRouteListApiURL()
    {
        $year = date("Y");
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
        $this->url = "http://koivu.luomus.fi/talvilinnut/census.php?year=$year&census=$census&json";
    }

    public function fetchDataFromCacheOrApi()
    {
		//echo $this->url;

		$filename = "cache/" . sha1($this->url) . ".json";

		if ($this->fileIsOld($filename))
		{
			$json = file_get_contents($this->url);
			$this->resultArray = json_decode($json, TRUE);
            $this->source = $this->url;

			// Save to cache
			file_put_contents($filename, $json);
		}
		else
		{
			// Get data from cache
			$json = file_get_contents($filename);
			$this->resultArray = json_decode($json, TRUE);
            $this->source = $filename;
		}
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

    public function filterRouteList()
    {
        if (isset($_GET["area"]))
        {
            $areaDirty = $_GET["area"];
        	foreach ($this->resultArray as $itemNumber => $routeData)
        	{
        		if ($areaDirty != $routeData['areaID'])
        		{
        			unset($this->resultArray[$itemNumber]);
        		}
        	}
        }

        // Sort by date desc
        usort($this->resultArray, function($a, $b) {
            return $b['date'] - $a['date'];
        });

//        print_r ($this->resultArray);

	}

    public function debug()
    {
    	echo "<pre>";
    	print_r($this->resultArray);
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

    	$html = "<h4>" . $this->title . ":</h4>";
    	foreach ($this->resultArray as $itemNumber => $routeData)
    	{
    		$html .= "
    		<p>
    		<span class=\"date\">" . $this->formatDate($routeData['date']) . "</span>
    		<span class=\"locality\"><a title=\"Lisätietoja Hatikassa\" href=\"http://hatikka.fi/?page=view&source=2&id=" . $routeData['documentID'] . "\">" . trim($routeData['municipality']) . ", " . trim($routeData['grid']) .  "</a>:</span>
    		<span class=\"speciesCount\">" . $routeData['speciesCount'] . " lajia,</span>
    		<span class=\"individualCount\">" . $routeData['individualCount'] . " yksilöä</span>
    		<span class=\"team\"><span>(</span>" . $routeData['team'] . "<span>)</span></span>
    		</p>\n
    		";
            $routeCount++;
            $individualAverageHelper = $individualAverageHelper + $routeData['individualCount'];
            $speciesAverageHelper = $speciesAverageHelper + $routeData['speciesCount'];
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

    public function getCiting()
    {
        return "<p id=\"talvilintutulokset-cite\">
        <span class=\"data\">Data: <a href=\"http://www.luomus.fi/fi/talvilintulaskennat\">LUOMUS</a>, Helsingin yliopisto.</span>
        <span class=\"service\">Powered by <a href=\"https://github.com/mikkohei13/talvilintutulokset\">talvilintutulokset</a></span>
        </p>";
    }

    public function getExecutionStats()
    {
        return "<p id=\"talvilintutulokset-debug\" style=\"display: none;\">source " . $this->source . ", time " . $this->getExcecutionTime() . " s</p>";
    }

    public function getEveryRouteData()
    {
        foreach ($this->resultArray as $itemNumber => $routeData)
        {
            $this->getSingleRouteData($routeData);
        }
//        echo "s";
    }

    public function getSingleRouteData($routeData)
    {
        $DocumentID = $routeData['documentID'];
        $filename = "cache/documentID_" . $DocumentID . ".xml";

        if ($this->fileIsOld($filename, 24))
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
            $dataset = $routeXML->DataSet;

            // Finds and saves route length
            foreach ($dataset->Gathering->SiteMeasurementsOrFacts->SiteMeasurementOrFact as $siteFact)
            {
                if ($siteFact->MeasurementOrFactAtomised->Parameter == "ReitinPituus")
                {
                    $totalLengthMeters = $totalLengthMeters + $siteFact->MeasurementOrFactAtomised->LowerValue;
                }
            }

            // Finds and saves route length
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

                    // Finally saves sums to a varibale
                    @$this->speciesCounts[$sp] = $this->speciesCounts[$sp] + $count;
                    @$this->speciesOnRoutes[$sp] = $this->speciesOnRoutes[$sp] + 1;
                }
            }
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

    public function getSingleRouteStats()
    {

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
                <td class=\"species\"><em>$species</em></td>
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

}

$talvilinnut = new talvilinnut();


if (isset($_GET['stats']))
{
    $talvilinnut->getEveryRouteData();
    $talvilinnut->countEveryRouteStats();
    $talvilinnut->getRouteList(); // this also counts averages

    if (isset($_GET['json']))
    {
        echo $talvilinnut->getStatsJSON();
    }
    else
    {
        $talvilinnut->echoStatsGraph();
        echo $talvilinnut->getCiting();
        echo $talvilinnut->getExecutionStats();
    }
} 
else
{
    echo $talvilinnut->getRouteList(); // this also counts averages
    echo $talvilinnut->getCiting();
    echo $talvilinnut->getExecutionStats();
}



//$talvilinnut->debug();


?>