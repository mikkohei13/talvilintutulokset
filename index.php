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
        $this->speciesCounts = $this->convertNames($this->speciesCounts);
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

    public function convertName($from)
    {
        $fullNames['Gavia stellata'] = "Kaakkuri";
        $fullNames['Gavia arctica'] = "Kuikka";
        $fullNames['Tachybaptus ruficollis'] = "Pikku-uikku";
        $fullNames['Podiceps cristatus'] = "Silkkiuikku";
        $fullNames['Podiceps grisegena'] = "Härkälintu";
        $fullNames['Podiceps auritus'] = "Mustakurkku-uikku";
        $fullNames['Phalacrocorax carbo'] = "Merimetso";
        $fullNames['Botaurus stellaris'] = "Kaulushaikara";
        $fullNames['Ardea cinerea'] = "Harmaahaikara";
        $fullNames['Cygnus olor'] = "Kyhmyjoutsen";
        $fullNames['Cygnus cygnus'] = "Laulujoutsen";
        $fullNames['Anser fabalis'] = "Metsähanhi";
        $fullNames['Anser erythropus'] = "Kiljuhanhi";
        $fullNames['Anser anser'] = "Merihanhi";
        $fullNames['Anser caerulescens'] = "Lumihanhi";
        $fullNames['Branta canadensis'] = "Kanadanhanhi";
        $fullNames['Branta leucopsis'] = "Valkoposkihanhi";
        $fullNames['Tadorna tadorna'] = "Ristisorsa";
        $fullNames['Anas penelope'] = "Haapana";
        $fullNames['Anas strepera'] = "Harmaasorsa";
        $fullNames['Anas crecca'] = "Tavi";
        $fullNames['Anas platyrhynchos'] = "Sinisorsa";
        $fullNames['Anas acuta'] = "Jouhisorsa";
        $fullNames['Anas querquedula'] = "Heinätavi";
        $fullNames['Anas clypeata'] = "Lapasorsa";
        $fullNames['Aythya ferina'] = "Punasotka";
        $fullNames['Aythya fuligula'] = "Tukkasotka";
        $fullNames['Aythya marila'] = "Lapasotka";
        $fullNames['Somateria mollissima'] = "Haahka";
        $fullNames['Clangula hyemalis'] = "Alli";
        $fullNames['Melanitta nigra'] = "Mustalintu";
        $fullNames['Melanitta fusca'] = "Pilkkasiipi";
        $fullNames['Bucephala clangula'] = "Telkkä";
        $fullNames['Mergellus albellus'] = "Uivelo";
        $fullNames['Mergus serrator'] = "Tukkakoskelo";
        $fullNames['Mergus merganser'] = "Isokoskelo";
        $fullNames['Pernis apivorus'] = "Mehiläishaukka";
        $fullNames['Milvus migrans'] = "Haarahaukka";
        $fullNames['Haliaeetus albicilla'] = "Merikotka";
        $fullNames['Circus aeruginosus'] = "Ruskosuohaukka";
        $fullNames['Circus cyaneus'] = "Sinisuohaukka";
        $fullNames['Circus pygargus'] = "Niittysuohaukka";
        $fullNames['Circus macrourus'] = "Arosuohaukka";
        $fullNames['Accipiter gentilis'] = "Kanahaukka";
        $fullNames['Accipiter nisus'] = "Varpushaukka";
        $fullNames['Buteo buteo'] = "Hiirihaukka";
        $fullNames['Buteo lagopus'] = "Piekana";
        $fullNames['Aquila clanga'] = "Kiljukotka";
        $fullNames['Aquila chrysaetos'] = "Maakotka";
        $fullNames['Pandion haliaetus'] = "Sääksi (kalasääski)";
        $fullNames['Falco tinnunculus'] = "Tuulihaukka";
        $fullNames['Falco vespertinus'] = "Punajalkahaukka";
        $fullNames['Falco columbarius'] = "Ampuhaukka";
        $fullNames['Falco subbuteo'] = "Nuolihaukka";
        $fullNames['Falco rusticolus'] = "Tunturihaukka";
        $fullNames['Falco peregrinus'] = "Muuttohaukka";
        $fullNames['Tetrastes bonasia'] = "Pyy";
        $fullNames['Lagopus lagopus'] = "Riekko";
        $fullNames['Lagopus muta'] = "Kiiruna";
        $fullNames['Lyrurus tetrix'] = "Teeri";
        $fullNames['Tetrao urogallus'] = "Metso";
        $fullNames['Perdix perdix'] = "Peltopyy";
        $fullNames['Coturnix coturnix'] = "Viiriäinen";
        $fullNames['Phasianus colchicus'] = "Fasaani";
        $fullNames['Rallus aquaticus'] = "Luhtakana";
        $fullNames['Porzana porzana'] = "Luhtahuitti";
        $fullNames['Porzana parva'] = "Pikkuhuitti";
        $fullNames['Crex crex'] = "Ruisrääkkä";
        $fullNames['Gallinula chloropus'] = "Liejukana";
        $fullNames['Fulica atra'] = "Nokikana";
        $fullNames['Grus grus'] = "Kurki";
        $fullNames['Haematopus ostralegus'] = "Meriharakka";
        $fullNames['Charadrius dubius'] = "Pikkutylli";
        $fullNames['Charadrius hiaticula'] = "Tylli";
        $fullNames['Charadrius morinellus'] = "Keräkurmitsa";
        $fullNames['Pluvialis apricaria'] = "Kapustarinta";
        $fullNames['Vanellus vanellus'] = "Töyhtöhyyppä";
        $fullNames['Calidris minuta'] = "Pikkusirri";
        $fullNames['Calidris temminckii'] = "Lapinsirri";
        $fullNames['Calidris maritima'] = "Merisirri";
        $fullNames['Calidris alpina'] = "Suosirri";
        $fullNames['Limicola falcinellus'] = "Jänkäsirriäinen";
        $fullNames['Philomachus pugnax'] = "Suokukko";
        $fullNames['Lymnocryptes minimus'] = "Jänkäkurppa";
        $fullNames['Gallinago gallinago'] = "Taivaanvuohi";
        $fullNames['Gallinago media'] = "Heinäkurppa";
        $fullNames['Scolopax rusticola'] = "Lehtokurppa";
        $fullNames['Limosa limosa'] = "Mustapyrstökuiri";
        $fullNames['Limosa lapponica'] = "Punakuiri";
        $fullNames['Numenius phaeopus'] = "Pikkukuovi";
        $fullNames['Numenius arquata'] = "Kuovi";
        $fullNames['Tringa erythropus'] = "Mustaviklo";
        $fullNames['Tringa totanus'] = "Punajalkaviklo";
        $fullNames['Tringa stagnatilis'] = "Lampiviklo";
        $fullNames['Tringa nebularia'] = "Valkoviklo";
        $fullNames['Tringa ochropus'] = "Metsäviklo";
        $fullNames['Tringa glareola'] = "Liro";
        $fullNames['Xenus cinereus'] = "Rantakurvi";
        $fullNames['Actitis hypoleucos'] = "Rantasipi";
        $fullNames['Arenaria interpres'] = "Karikukko";
        $fullNames['Phalaropus lobatus'] = "Vesipääsky";
        $fullNames['Stercorarius parasiticus'] = "Merikihu";
        $fullNames['Stercorarius longicaudus'] = "Tunturikihu";
        $fullNames['Larus minutus'] = "Pikkulokki";
        $fullNames['Larus ridibundus'] = "Naurulokki";
        $fullNames['Larus canus'] = "Kalalokki";
        $fullNames['Larus fuscus'] = "Selkälokki";
        $fullNames['Larus argentatus'] = "Harmaalokki";
        $fullNames['Larus marinus'] = "Merilokki";
        $fullNames['Sterna caspia'] = "Räyskä";
        $fullNames['Sterna hirundo'] = "Kalatiira";
        $fullNames['Sterna paradisaea'] = "Lapintiira";
        $fullNames['Sterna albifrons'] = "Pikkutiira";
        $fullNames['Chlidonias niger'] = "Mustatiira";
        $fullNames['Uria aalge'] = "Etelänkiisla";
        $fullNames['Alca torda'] = "Ruokki";
        $fullNames['Cepphus grylle'] = "Riskilä";
        $fullNames['Columba livia'] = "Kesykyyhky";
        $fullNames['Columba oenas'] = "Uuttukyyhky";
        $fullNames['Columba palumbus'] = "Sepelkyyhky";
        $fullNames['Streptopelia decaocto'] = "Turkinkyyhky";
        $fullNames['Streptopelia turtur'] = "Turturikyyhky";
        $fullNames['Cuculus canorus'] = "Käki";
        $fullNames['Bubo bubo'] = "Huuhkaja";
        $fullNames['Nyctea scandiaca'] = "Tunturipöllö";
        $fullNames['Surnia ulula'] = "Hiiripöllö";
        $fullNames['Glaucidium passerinum'] = "Varpuspöllö";
        $fullNames['Strix aluco'] = "Lehtopöllö";
        $fullNames['Strix uralensis'] = "Viirupöllö";
        $fullNames['Strix nebulosa'] = "Lapinpöllö";
        $fullNames['Asio otus'] = "Sarvipöllö";
        $fullNames['Asio flammeus'] = "Suopöllö";
        $fullNames['Aegolius funereus'] = "Helmipöllö";
        $fullNames['Caprimulgus europaeus'] = "Kehrääjä";
        $fullNames['Apus apus'] = "Tervapääsky";
        $fullNames['Alcedo atthis'] = "Kuningaskalastaja";
        $fullNames['Jynx torquilla'] = "Käenpiika";
        $fullNames['Picus canus'] = "Harmaapäätikka";
        $fullNames['Dryocopus martius'] = "Palokärki";
        $fullNames['Dendrocopos major'] = "Käpytikka";
        $fullNames['Dendrocopos leucotos'] = "Valkoselkätikka";
        $fullNames['Dendrocopos minor'] = "Pikkutikka";
        $fullNames['Picoides tridactylus'] = "Pohjantikka";
        $fullNames['Lullula arborea'] = "Kangaskiuru";
        $fullNames['Alauda arvensis'] = "Kiuru";
        $fullNames['Eremophila alpestris'] = "Tunturikiuru";
        $fullNames['Riparia riparia'] = "Törmäpääsky";
        $fullNames['Hirundo rustica'] = "Haarapääsky";
        $fullNames['Delichon urbicum'] = "Räystäspääsky";
        $fullNames['Anthus campestris'] = "Nummikirvinen";
        $fullNames['Anthus trivialis'] = "Metsäkirvinen";
        $fullNames['Anthus pratensis'] = "Niittykirvinen";
        $fullNames['Anthus cervinus'] = "Lapinkirvinen";
        $fullNames['Anthus petrosus'] = "Luotokirvinen";
        $fullNames['Motacilla flava'] = "Keltavästäräkki";
        $fullNames['Motacilla citreola'] = "Sitruunavästäräkki";
        $fullNames['Motacilla cinerea'] = "Virtavästäräkki";
        $fullNames['Motacilla alba'] = "Västäräkki";
        $fullNames['Bombycilla garrulus'] = "Tilhi";
        $fullNames['Cinclus cinclus'] = "Koskikara";
        $fullNames['Troglodytes troglodytes'] = "Peukaloinen";
        $fullNames['Prunella modularis'] = "Rautiainen";
        $fullNames['Erithacus rubecula'] = "Punarinta";
        $fullNames['Luscinia luscinia'] = "Satakieli";
        $fullNames['Luscinia svecica'] = "Sinirinta";
        $fullNames['Luscinia cyanura'] = "Sinipyrstö";
        $fullNames['Phoenicurus ochruros'] = "Mustaleppälintu";
        $fullNames['Phoenicurus phoenicurus'] = "Leppälintu";
        $fullNames['Saxicola rubetra'] = "Pensastasku";
        $fullNames['Saxicola torquatus'] = "Mustapäätasku";
        $fullNames['Oenanthe oenanthe'] = "Kivitasku";
        $fullNames['Turdus torquatus'] = "Sepelrastas";
        $fullNames['Turdus merula'] = "Mustarastas";
        $fullNames['Turdus pilaris'] = "Räkättirastas";
        $fullNames['Turdus philomelos'] = "Laulurastas";
        $fullNames['Turdus iliacus'] = "Punakylkirastas";
        $fullNames['Turdus viscivorus'] = "Kulorastas";
        $fullNames['Locustella naevia'] = "Pensassirkkalintu";
        $fullNames['Locustella fluviatilis'] = "Viitasirkkalintu";
        $fullNames['Locustella luscinioides'] = "Ruokosirkkalintu";
        $fullNames['Acrocephalus schoenobaenus'] = "Ruokokerttunen";
        $fullNames['Acrocephalus dumetorum'] = "Viitakerttunen";
        $fullNames['Acrocephalus palustris'] = "Luhtakerttunen";
        $fullNames['Acrocephalus scirpaceus'] = "Rytikerttunen";
        $fullNames['Acrocephalus arundinaceus'] = "Rastaskerttunen";
        $fullNames['Iduna caligata'] = "Pikkukultarinta";
        $fullNames['Hippolais icterina'] = "Kultarinta";
        $fullNames['Sylvia nisoria'] = "Kirjokerttu";
        $fullNames['Sylvia curruca'] = "Hernekerttu";
        $fullNames['Sylvia communis'] = "Pensaskerttu";
        $fullNames['Sylvia borin'] = "Lehtokerttu";
        $fullNames['Sylvia atricapilla'] = "Mustapääkerttu";
        $fullNames['Phylloscopus trochiloides'] = "Idänuunilintu";
        $fullNames['Phylloscopus borealis'] = "Lapinuunilintu";
        $fullNames['Phylloscopus sibilatrix'] = "Sirittäjä";
        $fullNames['Phylloscopus collybita'] = "Tiltaltti";
        $fullNames['Phylloscopus trochilus'] = "Pajulintu";
        $fullNames['Regulus regulus'] = "Hippiäinen";
        $fullNames['Muscicapa striata'] = "Harmaasieppo";
        $fullNames['Ficedula parva'] = "Pikkusieppo";
        $fullNames['Ficedula hypoleuca'] = "Kirjosieppo";
        $fullNames['Panurus biarmicus'] = "Viiksitimali";
        $fullNames['Aegithalos caudatus'] = "Pyrstötiainen";
        $fullNames['Parus montanus'] = "Hömötiainen";
        $fullNames['Parus cinctus'] = "Lapintiainen";
        $fullNames['Parus cristatus'] = "Töyhtötiainen";
        $fullNames['Parus ater'] = "Kuusitiainen";
        $fullNames['Parus caeruleus'] = "Sinitiainen";
        $fullNames['Parus cyanus'] = "Valkopäätiainen";
        $fullNames['Parus major'] = "Talitiainen";
        $fullNames['Sitta europaea'] = "Pähkinänakkeli";
        $fullNames['Certhia familiaris'] = "Puukiipijä";
        $fullNames['Remiz pendulinus'] = "Pussitiainen";
        $fullNames['Oriolus oriolus'] = "Kuhankeittäjä";
        $fullNames['Lanius collurio'] = "Pikkulepinkäinen";
        $fullNames['Lanius excubitor'] = "Isolepinkäinen";
        $fullNames['Garrulus glandarius'] = "Närhi";
        $fullNames['Perisoreus infaustus'] = "Kuukkeli";
        $fullNames['Pica pica'] = "Harakka";
        $fullNames['Nucifraga caryocatactes'] = "Pähkinähakki";
        $fullNames['Corvus monedula'] = "Naakka";
        $fullNames['Corvus frugilegus'] = "Mustavaris";
        $fullNames['Corvus corone'] = "Varis";
        $fullNames['Corvus corax'] = "Korppi";
        $fullNames['Sturnus vulgaris'] = "Kottarainen";
        $fullNames['Passer domesticus'] = "Varpunen";
        $fullNames['Passer montanus'] = "Pikkuvarpunen";
        $fullNames['Fringilla coelebs'] = "Peippo";
        $fullNames['Fringilla montifringilla'] = "Järripeippo";
        $fullNames['Serinus serinus'] = "Keltahemppo";
        $fullNames['Carduelis chloris'] = "Viherpeippo";
        $fullNames['Carduelis carduelis'] = "Tikli";
        $fullNames['Carduelis spinus'] = "Vihervarpunen";
        $fullNames['Carduelis cannabina'] = "Hemppo";
        $fullNames['Carduelis flavirostris'] = "Vuorihemppo";
        $fullNames['Carduelis flammea'] = "Urpiainen";
        $fullNames['Carduelis hornemanni'] = "Tundraurpiainen";
        $fullNames['Loxia leucoptera'] = "Kirjosiipikäpylintu";
        $fullNames['Loxia curvirostra'] = "Pikkukäpylintu";
        $fullNames['Loxia pytyopsittacus'] = "Isokäpylintu";
        $fullNames['Carpodacus erythrinus'] = "Punavarpunen";
        $fullNames['Pinicola enucleator'] = "Taviokuurna";
        $fullNames['Pyrrhula pyrrhula'] = "Punatulkku";
        $fullNames['Coccothraustes coccothraustes'] = "Nokkavarpunen";
        $fullNames['Calcarius lapponicus'] = "Lapinsirkku";
        $fullNames['Plectrophenax nivalis'] = "Pulmunen";
        $fullNames['Emberiza citrinella'] = "Keltasirkku";
        $fullNames['Emberiza hortulana'] = "Peltosirkku";
        $fullNames['Emberiza rustica'] = "Pohjansirkku";
        $fullNames['Emberiza pusilla'] = "Pikkusirkku";
        $fullNames['Emberiza aureola'] = "Kultasirkku";
        $fullNames['Emberiza schoeniclus'] = "Pajusirkku";
        $fullNames['Gavste'] = "Kaakkuri";
        $fullNames['Gavarc'] = "Kuikka";
        $fullNames['Tacruf'] = "Pikku-uikku";
        $fullNames['Podcri'] = "Silkkiuikku";
        $fullNames['Podgri'] = "Härkälintu";
        $fullNames['Podaur'] = "Mustakurkku-uikku";
        $fullNames['Phacar'] = "Merimetso";
        $fullNames['Botste'] = "Kaulushaikara";
        $fullNames['Ardcin'] = "Harmaahaikara";
        $fullNames['Cygolo'] = "Kyhmyjoutsen";
        $fullNames['Cygcyg'] = "Laulujoutsen";
        $fullNames['Ansfab'] = "Metsähanhi";
        $fullNames['Ansery'] = "Kiljuhanhi";
        $fullNames['Ansans'] = "Merihanhi";
        $fullNames['Anscae'] = "Lumihanhi";
        $fullNames['Bracan'] = "Kanadanhanhi";
        $fullNames['Braleu'] = "Valkoposkihanhi";
        $fullNames['Tadtad'] = "Ristisorsa";
        $fullNames['Anapen'] = "Haapana";
        $fullNames['Anastr'] = "Harmaasorsa";
        $fullNames['Anacre'] = "Tavi";
        $fullNames['Anapla'] = "Sinisorsa";
        $fullNames['Anaacu'] = "Jouhisorsa";
        $fullNames['Anaque'] = "Heinätavi";
        $fullNames['Anacly'] = "Lapasorsa";
        $fullNames['Aytfer'] = "Punasotka";
        $fullNames['Aytful'] = "Tukkasotka";
        $fullNames['Aytmar'] = "Lapasotka";
        $fullNames['Sommol'] = "Haahka";
        $fullNames['Clahye'] = "Alli";
        $fullNames['Melnig'] = "Mustalintu";
        $fullNames['Melfus'] = "Pilkkasiipi";
        $fullNames['Buccla'] = "Telkkä";
        $fullNames['Meralb'] = "Uivelo";
        $fullNames['Merser'] = "Tukkakoskelo";
        $fullNames['Mermer'] = "Isokoskelo";
        $fullNames['Perapi'] = "Mehiläishaukka";
        $fullNames['Milmig'] = "Haarahaukka";
        $fullNames['Halalb'] = "Merikotka";
        $fullNames['Ciraer'] = "Ruskosuohaukka";
        $fullNames['Circya'] = "Sinisuohaukka";
        $fullNames['Cirpyg'] = "Niittysuohaukka";
        $fullNames['Cirmac'] = "Arosuohaukka";
        $fullNames['Accgen'] = "Kanahaukka";
        $fullNames['Accnis'] = "Varpushaukka";
        $fullNames['Butbut'] = "Hiirihaukka";
        $fullNames['Butlag'] = "Piekana";
        $fullNames['Aqucla'] = "Kiljukotka";
        $fullNames['Aquchr'] = "Maakotka";
        $fullNames['Panhal'] = "Sääksi (kalasääski)";
        $fullNames['Faltin'] = "Tuulihaukka";
        $fullNames['Falves'] = "Punajalkahaukka";
        $fullNames['Falcol'] = "Ampuhaukka";
        $fullNames['Falsub'] = "Nuolihaukka";
        $fullNames['Falrus'] = "Tunturihaukka";
        $fullNames['Falper'] = "Muuttohaukka";
        $fullNames['Tetbon'] = "Pyy";
        $fullNames['Laglag'] = "Riekko";
        $fullNames['Lagmut'] = "Kiiruna";
        $fullNames['Lyrtet'] = "Teeri";
        $fullNames['Teturo'] = "Metso";
        $fullNames['Perper'] = "Peltopyy";
        $fullNames['Cotcot'] = "Viiriäinen";
        $fullNames['Phacol'] = "Fasaani";
        $fullNames['Ralaqu'] = "Luhtakana";
        $fullNames['Porpor'] = "Luhtahuitti";
        $fullNames['Porpar'] = "Pikkuhuitti";
        $fullNames['Crecre'] = "Ruisrääkkä";
        $fullNames['Galchl'] = "Liejukana";
        $fullNames['Fulatr'] = "Nokikana";
        $fullNames['Grugru'] = "Kurki";
        $fullNames['Haeost'] = "Meriharakka";
        $fullNames['Chadub'] = "Pikkutylli";
        $fullNames['Chahia'] = "Tylli";
        $fullNames['Chamor'] = "Keräkurmitsa";
        $fullNames['Pluapr'] = "Kapustarinta";
        $fullNames['Vanvan'] = "Töyhtöhyyppä";
        $fullNames['Caluta'] = "Pikkusirri";
        $fullNames['Caltem'] = "Lapinsirri";
        $fullNames['Calmar'] = "Merisirri";
        $fullNames['Calalp'] = "Suosirri";
        $fullNames['Limfal'] = "Jänkäsirriäinen";
        $fullNames['Phipug'] = "Suokukko";
        $fullNames['Lymmin'] = "Jänkäkurppa";
        $fullNames['Galgal'] = "Taivaanvuohi";
        $fullNames['Galmed'] = "Heinäkurppa";
        $fullNames['Scorus'] = "Lehtokurppa";
        $fullNames['Limlim'] = "Mustapyrstökuiri";
        $fullNames['Limlap'] = "Punakuiri";
        $fullNames['Numpha'] = "Pikkukuovi";
        $fullNames['Numarq'] = "Kuovi";
        $fullNames['Triery'] = "Mustaviklo";
        $fullNames['Tritot'] = "Punajalkaviklo";
        $fullNames['Trista'] = "Lampiviklo";
        $fullNames['Trineb'] = "Valkoviklo";
        $fullNames['Trioch'] = "Metsäviklo";
        $fullNames['Trigla'] = "Liro";
        $fullNames['Xencin'] = "Rantakurvi";
        $fullNames['Acthyp'] = "Rantasipi";
        $fullNames['Areint'] = "Karikukko";
        $fullNames['Phalob'] = "Vesipääsky";
        $fullNames['Stecus'] = "Merikihu";
        $fullNames['Stelon'] = "Tunturikihu";
        $fullNames['Larmin'] = "Pikkulokki";
        $fullNames['Larrid'] = "Naurulokki";
        $fullNames['Larcan'] = "Kalalokki";
        $fullNames['Larfus'] = "Selkälokki";
        $fullNames['Lararg'] = "Harmaalokki";
        $fullNames['Larmar'] = "Merilokki";
        $fullNames['Stecas'] = "Räyskä";
        $fullNames['Stehir'] = "Kalatiira";
        $fullNames['Steaea'] = "Lapintiira";
        $fullNames['Stealb'] = "Pikkutiira";
        $fullNames['Chlnig'] = "Mustatiira";
        $fullNames['Uriaal'] = "Etelänkiisla";
        $fullNames['Alctor'] = "Ruokki";
        $fullNames['Cepgry'] = "Riskilä";
        $fullNames['Colliv'] = "Kesykyyhky";
        $fullNames['Coloen'] = "Uuttukyyhky";
        $fullNames['Colpal'] = "Sepelkyyhky";
        $fullNames['Strdec'] = "Turkinkyyhky";
        $fullNames['Strtur'] = "Turturikyyhky";
        $fullNames['Cuccan'] = "Käki";
        $fullNames['Bubbub'] = "Huuhkaja";
        $fullNames['Nycsca'] = "Tunturipöllö";
        $fullNames['Surulu'] = "Hiiripöllö";
        $fullNames['Glapas'] = "Varpuspöllö";
        $fullNames['Stralu'] = "Lehtopöllö";
        $fullNames['Strura'] = "Viirupöllö";
        $fullNames['Strneb'] = "Lapinpöllö";
        $fullNames['Asiotu'] = "Sarvipöllö";
        $fullNames['Asifla'] = "Suopöllö";
        $fullNames['Aegfun'] = "Helmipöllö";
        $fullNames['Capeur'] = "Kehrääjä";
        $fullNames['Apuapu'] = "Tervapääsky";
        $fullNames['Alcatt'] = "Kuningaskalastaja";
        $fullNames['Jyntor'] = "Käenpiika";
        $fullNames['Piccan'] = "Harmaapäätikka";
        $fullNames['Drymar'] = "Palokärki";
        $fullNames['Denmaj'] = "Käpytikka";
        $fullNames['Denleu'] = "Valkoselkätikka";
        $fullNames['Denmin'] = "Pikkutikka";
        $fullNames['Pictri'] = "Pohjantikka";
        $fullNames['Lularb'] = "Kangaskiuru";
        $fullNames['Alaarv'] = "Kiuru";
        $fullNames['Erealp'] = "Tunturikiuru";
        $fullNames['Riprip'] = "Törmäpääsky";
        $fullNames['Hirrus'] = "Haarapääsky";
        $fullNames['Delurb'] = "Räystäspääsky";
        $fullNames['Antcam'] = "Nummikirvinen";
        $fullNames['Anttri'] = "Metsäkirvinen";
        $fullNames['Antpra'] = "Niittykirvinen";
        $fullNames['Antcer'] = "Lapinkirvinen";
        $fullNames['Antpet'] = "Luotokirvinen";
        $fullNames['Motfla'] = "Keltavästäräkki";
        $fullNames['Motcit'] = "Sitruunavästäräkki";
        $fullNames['Motcin'] = "Virtavästäräkki";
        $fullNames['Motalb'] = "Västäräkki";
        $fullNames['Bomgar'] = "Tilhi";
        $fullNames['Cincin'] = "Koskikara";
        $fullNames['Trotro'] = "Peukaloinen";
        $fullNames['Prumod'] = "Rautiainen";
        $fullNames['Erirub'] = "Punarinta";
        $fullNames['Luslus'] = "Satakieli";
        $fullNames['Lussve'] = "Sinirinta";
        $fullNames['Luscya'] = "Sinipyrstö";
        $fullNames['Phooch'] = "Mustaleppälintu";
        $fullNames['Phopho'] = "Leppälintu";
        $fullNames['Saxrub'] = "Pensastasku";
        $fullNames['Saxtor'] = "Mustapäätasku";
        $fullNames['Oenoen'] = "Kivitasku";
        $fullNames['Turtor'] = "Sepelrastas";
        $fullNames['Turmer'] = "Mustarastas";
        $fullNames['Turpil'] = "Räkättirastas";
        $fullNames['Turphi'] = "Laulurastas";
        $fullNames['Turili'] = "Punakylkirastas";
        $fullNames['Turvis'] = "Kulorastas";
        $fullNames['Locnae'] = "Pensassirkkalintu";
        $fullNames['Locflu'] = "Viitasirkkalintu";
        $fullNames['Loclus'] = "Ruokosirkkalintu";
        $fullNames['Acrsch'] = "Ruokokerttunen";
        $fullNames['Acrdum'] = "Viitakerttunen";
        $fullNames['Acrris'] = "Luhtakerttunen";
        $fullNames['Acrsci'] = "Rytikerttunen";
        $fullNames['Acraru'] = "Rastaskerttunen";
        $fullNames['Iducal'] = "Pikkukultarinta";
        $fullNames['Hipict'] = "Kultarinta";
        $fullNames['Sylnis'] = "Kirjokerttu";
        $fullNames['Sylcur'] = "Hernekerttu";
        $fullNames['Sylcom'] = "Pensaskerttu";
        $fullNames['Sylbor'] = "Lehtokerttu";
        $fullNames['Sylatr'] = "Mustapääkerttu";
        $fullNames['Phydes'] = "Idänuunilintu";
        $fullNames['Phybor'] = "Lapinuunilintu";
        $fullNames['Physib'] = "Sirittäjä";
        $fullNames['Phycol'] = "Tiltaltti";
        $fullNames['Phylus'] = "Pajulintu";
        $fullNames['Regreg'] = "Hippiäinen";
        $fullNames['Musstr'] = "Harmaasieppo";
        $fullNames['Ficpar'] = "Pikkusieppo";
        $fullNames['Fichyp'] = "Kirjosieppo";
        $fullNames['Panbia'] = "Viiksitimali";
        $fullNames['Aegcau'] = "Pyrstötiainen";
        $fullNames['Parmon'] = "Hömötiainen";
        $fullNames['Parcin'] = "Lapintiainen";
        $fullNames['Parcri'] = "Töyhtötiainen";
        $fullNames['Parate'] = "Kuusitiainen";
        $fullNames['Parcae'] = "Sinitiainen";
        $fullNames['Parcya'] = "Valkopäätiainen";
        $fullNames['Parmaj'] = "Talitiainen";
        $fullNames['Siteur'] = "Pähkinänakkeli";
        $fullNames['Cerfam'] = "Puukiipijä";
        $fullNames['Rempen'] = "Pussitiainen";
        $fullNames['Oriori'] = "Kuhankeittäjä";
        $fullNames['Lancol'] = "Pikkulepinkäinen";
        $fullNames['Lanexc'] = "Isolepinkäinen";
        $fullNames['Gargla'] = "Närhi";
        $fullNames['Perinf'] = "Kuukkeli";
        $fullNames['Picpic'] = "Harakka";
        $fullNames['Nuccar'] = "Pähkinähakki";
        $fullNames['Cormon'] = "Naakka";
        $fullNames['Corfru'] = "Mustavaris";
        $fullNames['Cornix'] = "Varis";
        $fullNames['Corrax'] = "Korppi";
        $fullNames['Stuvul'] = "Kottarainen";
        $fullNames['Pasdom'] = "Varpunen";
        $fullNames['Pasmon'] = "Pikkuvarpunen";
        $fullNames['Fricoe'] = "Peippo";
        $fullNames['Frimon'] = "Järripeippo";
        $fullNames['Serser'] = "Keltahemppo";
        $fullNames['Carchl'] = "Viherpeippo";
        $fullNames['Carcar'] = "Tikli";
        $fullNames['Carspi'] = "Vihervarpunen";
        $fullNames['Carcan'] = "Hemppo";
        $fullNames['Carris'] = "Vuorihemppo";
        $fullNames['Carmea'] = "Urpiainen";
        $fullNames['Carhor'] = "Tundraurpiainen";
        $fullNames['Loxleu'] = "Kirjosiipikäpylintu";
        $fullNames['Loxcur'] = "Pikkukäpylintu";
        $fullNames['Loxpyt'] = "Isokäpylintu";
        $fullNames['Carery'] = "Punavarpunen";
        $fullNames['Pinenu'] = "Taviokuurna";
        $fullNames['Pyrpyr'] = "Punatulkku";
        $fullNames['Coccoc'] = "Nokkavarpunen";
        $fullNames['Callap'] = "Lapinsirkku";
        $fullNames['Pleniv'] = "Pulmunen";
        $fullNames['Embcit'] = "Keltasirkku";
        $fullNames['Embhor'] = "Peltosirkku";
        $fullNames['Embrus'] = "Pohjansirkku";
        $fullNames['Embpus'] = "Pikkusirkku";
        $fullNames['Embaur'] = "Kultasirkku";
        $fullNames['Embsch'] = "Pajusirkku";

        if (isset($fullNames[$from]))
        {
            $ret = $fullNames[$from];
        }
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
            print_r ($array2);
        }
        return $array2;
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