<?php
header('Content-Type: text/html; charset=utf-8');

class talvilinnut
{
    public $basePath = "/tools/talvilintutulokset/";
	public $resultArray = Array();
	public $url = "";
	public $area = "";
    public $source = "";
    public $start = "";
    public $title = "";
    public $routesXMLarray = Array();
    public $speciesCounts = Array();

    public function __construct()
    {
        $this->start = microtime(TRUE);
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
                echo "<p>from Hatikka\n";
            }
            else
            {
                // Get data from cache
                $xml = simplexml_load_file($filename);
                $this->routesXMLarray[$DocumentID] = $xml;
                echo "<p>from Cache\n";
            }
        }
//        echo "s";
    }

    public function countStats()
    {
        $species = "";
        $count = 0;

        // Goes through all routes
        foreach ($this->routesXMLarray as $routeXML)
        {
            $dataset = $routeXML->DataSet;

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
                            $sp = (string) $atomized->MeasurementOrFactAtomised->LowerValue;
                        }
                        elseif ($atomized->MeasurementOrFactAtomised->Parameter == "Yksilömäärä")
                        {
                            $count = (int) $atomized->MeasurementOrFactAtomised->LowerValue;
                        }
                        

                    }

                    // Sum
                    echo "RESULT: " . $sp . ": " . $count . " <br />\n";
                    $this->speciesCounts[$sp] = $this->speciesCounts[$sp] + $count;

//                    $speciesSimple = $species->MeasurementsOrFacts->MeasurementOrFact;


                }
            }

            /*
            // Remove all data exept units-elements
            foreach ($routeData['DataSet'] as $element => $elementArray)
            {
                if ("Units" != $element)
                {
                   unset($routeData['DataSet'][$element]);
                }
            }

            // One route
            print_r($routeData); // debug

            // There can be 1-2 subelements of the units-element: one for basic species, possibly one for extra species...
            foreach ($routeData['DataSet']['Units'] as $units_s => $units)
            {
//              print_r($units); // debug
                // Tässä phyhum näkyy
                if (empty($units) || ! is_array($units))
                {
                    continue;
                }

                // ...which can contain several unit-elements
                foreach ($units['Unit'] as $observationNumber => $observationArray)
                {

                    print_r($observationArray); // debug

                    $simpleObsArray = $observationArray['MeasurementsOrFacts']['MeasurementOrFact'];

                    // pick species and count
                    foreach ($simpleObsArray as $index => $atomized)
                    {
                        $atomizedSimple = $atomized['MeasurementOrFactAtomised'];

                        if ("InformalNameString" == @$atomizedSimple['Parameter'])
                        {
                            $species = @$atomizedSimple['LowerValue'];
                        }
                        elseif ("Yksilömäärä" == @$atomizedSimple['Parameter'])
                        {
                            $count = @$atomizedSimple['LowerValue'];
                        }
                    }

                    // sum
                    @$this->speciesCounts[$species] = $this->speciesCounts[$species] + $count;
                }

            }
            */
        }

        arsort($this->speciesCounts);
        print_r(@$this->speciesCounts);
    }

    public function echoStatsGraph()
    {
        $list = "";
        $i = 1;
        echo "<h4>Kokonaisyksilömäärät</h4>
            <style>
            #stats-list p
            {
                -webkit-columns: 15em 3;
                -moz-columns: 15em 3;
                columns: 15em 3;
            }
            #stats-list .number
            {
                display: inline-block;
                width: 1.5em;
            }
            #stats-list em
            {
                display: inline-block;
                width: 10em;
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
            $list .= "<span><span class=\"number\">$i.</span> <em>$species</em> <span class=\"count\">$count</span></span><br />";
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
        ";
    }

}

$talvilinnut = new talvilinnut();


if (isset($_GET['stats']))
{
    $talvilinnut->getRouteFullData();

    echo $talvilinnut->countStats();
//    $talvilinnut->echoStatsGraph();
    echo $talvilinnut->getExecutionStats();
}
else
{
    echo $talvilinnut->getRouteList();
    echo $talvilinnut->getExecutionStats();
}



//$talvilinnut->debug();


?>