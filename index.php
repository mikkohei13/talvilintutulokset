<?php
header('Content-Type: text/html; charset=utf-8');

class talvilinnut
{
	public $resultArray = Array();
	public $url = "http://koivu.luomus.fi/talvilinnut/census.php?year=2014&census=1&json";
	public $area = "";

    public function __construct()
    {    	
    	$this->fetchData();

    	if (empty($this->resultArray))
    	{
    		exit("Tältä ajalta ei ole vielä laskentoja.");
    	}
    }

    public function fetchData()
    {
		//echo $this->url;

		$filename = "cache/" . sha1($this->url) . ".json";

		if ($this->fileIsOld($filename))
		{
			$json = file_get_contents($this->url);
			$this->resultArray = json_decode($json, TRUE);

			// Save to cache
			file_put_contents($filename, $json);
		}
		else
		{
			// Get data from file
			$json = file_get_contents($filename);
			$this->resultArray = json_decode($json, TRUE);
		}
    }

    public function fileIsOld($filename)
    {
    	// @ because file might not exist
    	$hours = 12; 
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
    	foreach ($this->resultArray as $itemNumber => $routeData)
    	{
    		if (3 != $routeData['areaID'])
    		{
    			unset($this->resultArray[$itemNumber]);
    		}
    	}
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
    	$html = "";
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
}

$talvilinnut = new talvilinnut();

$talvilinnut->filterData();

echo $talvilinnut->getRouteList();

//$talvilinnut->debug();


?>