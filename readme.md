
# Sovellus talvilintulaskentojen tulosten näyttämiseen

Hakee datan Luomuksen verkkopalvelusta:
* Luettelo laskennoista JSON-muodossa, esim. http://koivu.luomus.fi/talvilinnut/census.php?year=2014&census=1
* Yksittäisen laskennat tulokset XML-muodossa, esim. http://hatikka.fi/?page=view&id=1134228&source=2&xsl=false

## Käyttö:
* Kaikki reitit: http://tringa.fi/tools/talvilintutulokset/
* Vain alueen 3 (= Tringa) reitit: http://tringa.fi/tools/talvilintutulokset/?area=3
* Vain alueen 3 (= Tringa) tilastot: http://tringa.fi/tools/talvilintutulokset/?area=3&stats

### Tulokset saa sivuilla vaikka jQuerylla:

	jQuery.get( "http://tringa.fi/tools/talvilintutulokset/?area=3", function( data ) {
	  jQuery( "#talvilintulaskennat" ).html( data );
	});
