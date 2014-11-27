<?php

class comparison
{
    public $start = "";
    public $title = "";
    public $citingHTML = "
        <p id=\"talvilintutulokset-cite\">
            <span class=\"data\">Data: <a href=\"http://www.luomus.fi/fi/talvilintulaskennat\">Luomus</a>, Helsingin yliopisto.</span>
            <span class=\"service\">Powered by <a href=\"https://github.com/mikkohei13/talvilintutulokset\">talvilintutulokset</a></span>
        </p>
    ";
    public $censusData = Array();
    public $cacheFilename;


    public function __construct()
    {
        $this->start = microtime(TRUE);
        $this->readCache();

        // Get and check vars
        $area = (int) $_GET['area'];

        $censuses = $_GET['censuses'];
        if (preg_match('/^[0-9,\-]+$/i', $censuses) === 0)
        {
            exit("Virheellinen arvo census-muuttujassa; vain numeroita, tavuviivoja (-) ja pilkkuja (,)");
        }
        $censusesArray = explode(",", $censuses);

        // TODO: get JSON from subdirectory?
        foreach ($censusesArray as $censusKey => $censusID)
        {
            $censusIDparts = explode("-", $censusID);
            $json = file_get_contents("http://tringa.fi/tools/talvilintutulokset-DEV/?area=$area&stats&json&year=" . $censusIDparts[0] . "&census=" . $censusIDparts[1]);
            $this->censusData[$censusID] = json_decode($json, TRUE);
        }
    }

    public function readCache()
    {
        $this->cacheFilename = "cache/" . md5($_SERVER['REQUEST_URI']);
        if (! $this->fileIsOld($this->cacheFilename))
        {
            $cacheFile = file_get_contents($this->cacheFilename);
            echo $cacheFile;
            echo $this->getExecutionStats("cache");
            exit("");
        }
    }

    // TODO: move to utils?
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

    public function getExcecutionTime()
    {
        $end = microtime(TRUE);
        $time = $end - $this->start;
        return round($time, 3);
    }

    public function getExecutionStats($source = "api")
    {
        return "<p id=\"talvilintutulokset-debug\" style=\"display: block;\">source: $source, time " . $this->getExcecutionTime() . " s</p>";
    }

    public function getComparisonTable()
    {
        include "vernacular_names.php";
        $html = "";

        // Table start & header row
        $html .= "<table id=\"talvilinnut-comparison-table\">";
        $html .= "<tr class=\"table-header\">";
        $html .= "<th class=\"name\">Laji</th>";
        foreach ($this->censusData as $censusID => $censusData)
        {
            $html .= "<th>$censusID</th>";
        }
        $html .= "<th class=\"average\">ka.</th>";
        $html .= "</tr>";

        // Goes through each species
        foreach ($vernNames as $abbr => $name)
        {
            // Census results
            $html .= "<tr>";
            $html .= "<td class=\"name\">$name</td>\n";


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
                $html .= "<td class=\"$class\">$cValue</td>\n";
            }

            $html .= "<td class=\"average\">$average</td>\n";
            $html .= "</tr>\n\n";
        }
        $html .= "</table>\n\n\n";

        // Write cache
        $this->writeCache($html);
        return $html;

//        echo "<pre>"; print_r ($this->censusData); // debug

    }

    public function writeCache($data)
    {
        file_put_contents($this->cacheFilename, $data);
    }

    public function startHTML()
    {
        header('Content-Type: text/html; charset=utf-8');
        echo "
        <link rel=\"stylesheet\" href=\"../styles.css\" type='text/css' media='all' />
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

$comparison = new comparison();

$comparison->startHTML();

echo $comparison->getComparisonTable();
echo $comparison->getExecutionStats();

$comparison->endHTML();


?>