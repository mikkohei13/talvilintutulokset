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

    public function __construct()
    {
        $this->start = microtime(TRUE);
        $this->basePath = dirname($_SERVER['REQUEST_URI']) . "/";

        $this->createURL();
        $this->fetchData();
        $this->filterData();

    	if (empty($this->resultArray))
    	{
    		exit("Tältä ajalta ei ole vielä laskentoja.");
    	}
    }

    public function createURL()
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

    public function fetchData()
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

    public function filterData()
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

    public function getRouteTable()
    {
    	$html = "<table id=\"talvilinnut-table\">";
    	foreach ($this->resultArray as $itemNumber => $routeData)
    	{
    		$html .= "
    		<tr>
    		<td class=\"municipality\"><a href=\"http://hatikka.fi/?page=view&source=2&id=" . $routeData['documentID'] . "\">" . $routeData['municipality'] . "</a></td>
    		<td class=\"team\">" . $routeData['team'] . "</td>
    		<td class=\"date\">" . $this->formatDate($routeData['date']) . "</td>
    		<td class=\"speciesCount\">" . $routeData['speciesCount'] . " <span>lajia</span></td>
    		<td class=\"individualCount\">" . $routeData['individualCount'] . " <span>yksilöä</span></td>
    		</tr>
    		";
    	}
    	$html .= "</table>";
    	return $html;
	}

    public function getRouteList()
    {
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
    	}
    	$html .= "";
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

    public function getRouteFullData()
    {
        foreach ($this->resultArray as $itemNumber => $routeData)
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
//        echo "s";
    }

    public function getStatsJSON()
    {
        $array['speciesCounts'] = $this->speciesCounts;
        $array['totalLengthMeters'] = $this->totalLengthMeters;

        return json_encode($array);
    }

    public function countStats()
    {
        $species = "";
        $count = 0;
        $totalLengthMeters = 0;       

        // Goes through all routes
        foreach ($this->routesXMLarray as $routeXML)
        {

            $dataset = $routeXML->DataSet;
//            print_r ($dataset);

            foreach ($dataset->Gathering->SiteMeasurementsOrFacts->SiteMeasurementOrFact as $siteFact)
            {
//                print_r ($siteFact);
//                echo "-----------------------------\n";
                if ($siteFact->MeasurementOrFactAtomised->Parameter == "ReitinPituus")
                {
                    $totalLengthMeters = $totalLengthMeters + $siteFact->MeasurementOrFactAtomised->LowerValue;
                }
            }

            foreach ($dataset->Units as $unit)
            {
//                $dataset = $routeXML->DataSet;

                foreach ($unit as $species)
                {
                    $measurement = $species->MeasurementsOrFacts->MeasurementOrFact;
                    $sp = "";
                    $count = "";

                    foreach ($measurement as $key => $atomized)
                    {
//                        print_r($atomized);
//                        echo "\n--atomized END--\n";
                        if ($atomized->MeasurementOrFactAtomised->Parameter == "InformalNameString")
                        {
//                            echo "HIT parameter: ". $atomized->MeasurementOrFactAtomised->Parameter . " \n";
                            $sp = ucfirst(strtolower((string) $atomized->MeasurementOrFactAtomised->LowerValue));
                        }
                        elseif ($atomized->MeasurementOrFactAtomised->Parameter == "Yksilömäärä")
                        {
                            $count = (int) $atomized->MeasurementOrFactAtomised->LowerValue;
                        }
                        

                    }

                    // Sum
//                    echo "RESULT: " . $sp . ": " . $count . " <br />\n";
                    @$this->speciesCounts[$sp] = $this->speciesCounts[$sp] + $count;

//                    $speciesSimple = $species->MeasurementsOrFacts->MeasurementOrFact;


                }
            }
        }

        arsort($this->speciesCounts);
        $this->speciesCounts = $this->convertNames($this->speciesCounts);
//        print_r(@$this->speciesCounts);

        $this->totalLengthMeters = $totalLengthMeters;
    }

    public function echoStatsGraph()
    {
        $totalLength10kms = $this->totalLengthMeters / 10000;
        $list = "";
        $i = 1;


        echo "<h4>Kokonaisyksilömäärät</h4>
            <style>
            #stats-list p
            {
                -webkit-columns: 20em 2;
                -moz-columns: 20em 2;
                columns: 20em 2;
            }
            #stats-list .number
            {
                display: inline-block;
                width: 2em;
            }
            #stats-list em
            {
                display: inline-block;
                width: 10em;
            }
            #stats-list .count
            {
                display: inline-block;
                width: 4em;
            }
            #stats-list .count10km span
            {
                color: #999;
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
            $list .= "<span><span class=\"number\">$i.</span> <em>$species</em> <span class=\"count\">$count</span> <span class=\"count10km\">". round(($count / $totalLength10kms), 1) . " <span>/ 10 km</span></span></span><br />";
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
        <div id=\"stats-list\">
        <p>$list</p>
        </div>
        <p id=\"stats-length\">Reittien pituus yhteensä <span>" . round(($this->totalLengthMeters / 1000), 1) . "</span> km</p>
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
    $talvilinnut->getRouteFullData();
    $talvilinnut->countStats();

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
    echo $talvilinnut->getRouteList();
    echo $talvilinnut->getCiting();
    echo $talvilinnut->getExecutionStats();
}



//$talvilinnut->debug();


?>