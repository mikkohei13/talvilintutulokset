
# Sovellus talvilintulaskentojen tulosten näyttämiseen

Hakee datan Luomuksen verkkopalvelusta:
* Luettelo laskennoista JSON-muodossa, esim. http://koivu.luomus.fi/talvilinnut/census.php?year=2014&census=1
* Yksittäisen laskennat tulokset XML-muodossa, esim. http://hatikka.fi/?page=view&id=1134228&source=2&xsl=false

## Käyttö:

Lintuyhdistyksen aluekoodi annetaan sovellukselle area-paramterina. Jos area-parametria ei anna, näyttää sovellus datan koko Suomen alueelta. Stasts-parametrin lisääminen näyttää laji- ja yksilömäärätilaston.

* Kaikki reitit: http://tringa.fi/tools/talvilintutulokset/
* Kaikki tilastot: http://tringa.fi/tools/talvilintutulokset/?stats (HIDAS!)
* Vain alueen 21 (= Tringa) reitit: http://tringa.fi/tools/talvilintutulokset/?area=21
* Vain alueen 21 (= Tringa) tilastot: http://tringa.fi/tools/talvilintutulokset/?area=21&stats

### Aluekoodit:

Turun Lintutieteellinen Yhdistys	11
Porin Lintutieteellinen Yhdistys	12
Rauman Seudun Lintuharrastajat	13
Helsingin Seudun Lintutieteellinen Yhdistys - Tringa	21
Keski- ja Pohjois-Uudenmaan Lintuharrastajat - Apus	22
Porvoon Seudun Lintuyhdistys	23
Lohjan Lintutieteellinen Yhdistys - Hakki	24
Kymenlaakson Lintutieteellinen Yhdistys	31
Etelä-Karjalan Lintutieteellinen Yhdistys	32
Lounais-Hämeen Lintuharrastajat	41
Kanta-Hämeen Lintutieteellinen Yhdistys	42
Päijät-Hämeen Lintutieteellinen Yhdistys	43
Pirkanmaan Lintutieteellinen Yhdistys
Etelä-Savon Lintuharrastajat - Oriolus	51
Pohjois-Savon Lintuyhdistys - Kuikka	54
Pohjois-Karjalan Lintutieteellinen Yhdistys	57
Keski-Suomen Lintutieteellinen Yhdistys	61
Suomenselän Lintutieteellinen Yhdistys	71
Suupohjan Lintutietieteellinen Yhdistys	72
Keski-Pohjanmaan Lintutieteellinen Yhdistys	74
Pohjois-Pohjanmaan Lintutieteellinen Yhdistys	81
Kainuun Lintutieteellinen Yhdistys	82
Kuusamon lintukerho	83
Kemi-Tornion Lintuharrastajat - Xenus	91
Lapin Lintutieteellinen Yhdistys	92

### Tulokset saa sivuille esim...

jQuerylla:

	jQuery.get( "http://tringa.fi/tools/talvilintutulokset/?area=21", function( data ) {
	  jQuery( "#talvilintulaskennat" ).html( data );
	});

iframella:

	<iframe src="http://tringa.fi/tools/talvilintutulokset/?area=21" style="width: 800px; height: 500px;"></iframe>



