<?php
header('Content-Type: text/html; charset=utf-8');

class talvilinnut
{
	public $resultArray = Array();
	public $url = "";
	public $area = "";
    public $source = "";
    public $start = "";
    public $title = "";

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

    public function fileIsOld($filename)
    {
    	// @ because file might not exist
    	$hours = 1; 
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

        print_r ($this->resultArray);

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

    public function getStats()
    {
        return "<p id=\"talvilintutulokset-debug\" style=\"display: none;\">source " . $this->source . ", time " . $this->getExcecutionTime() . " s</p>";
    }
}

$talvilinnut = new talvilinnut();

echo $talvilinnut->getRouteList();

echo $talvilinnut->getStats();



//$talvilinnut->debug();


?>