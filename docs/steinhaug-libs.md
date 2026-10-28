
# Project overview

Her er litt info rundt prosjektet.

## Apache webserver info

ServerName https://steinhaug.steinhaug_libs.local/
DocumentRoot "public_html/"

## Project info

assets/                 ## github pages
_config.yml             ## github pages
mysqli_credentials.inc  ## SQL credentials
docs/                   ## to be continued...
logs/                   ## apache webserver logs + PHP error log
src/
phpcs.ruleset.xml       ## my personal coding style
public_html/            ## test files

## Javascript test file explained

https://steinhaug.steinhaug_libs.local/javascript_file.php
Skal levere headers identisk med
https://steinhaug.steinhaug_libs.local/javascript_file.js

### javascript_file.php skal ha følgende funksjonalitet.

- 1 Om det foreligger en cache så serveres cachefilen, gzip og med etag.
- 2 Når filen er prosessert så opprettes cache fil, leveres som steg 1
- 3 Javascript kan minifiseres, deler eller hele filen.

### Hordan trigger man er en rebuild of cache ? 

Når filen lastes med CTRL+F5 skal den rebuildes.

// ctrl+f5
// removes cache, makes the library have to rebuild again
Pragma: no-cache
Cache-Control: no-cache

// f5
// normal behaviour
Cache-Control: max-age=0

## Stylesheet test file explained

https://steinhaug.steinhaug_libs.local/styles_file.php
Skal levere headers identisk med
https://steinhaug.steinhaug_libs.local/styles_file.css

### styles_file.php skal ha følgende funksjonalitet.

- 1 Om det foreligger en cache så serveres cachefilen, gzip og med etag.
- 2 Når filen er prosessert så opprettes cache fil, leveres som steg 1
- 3 stylesheet kan minifiseres, deler eller hele filen.

### Hordan trigger man er en rebuild of cache ? 

Når filen lastes med CTRL+F5 skal den rebuildes.

// ctrl+f5
// removes cache, makes the library have to rebuild again
Pragma: no-cache
Cache-Control: no-cache

// f5
// normal behaviour
Cache-Control: max-age=0

## OPPGAVER

Når man henter inn stylesheets så er det nødvendig å bygge om src for bilder og andre filer, siden den relative path endrer seg ved å levere CSS fra ny plassering.
Funksjonalitet som automatisk korrigerer dette er ønskelig.

Jeg mener at `matthiasmullie/minify` har funksjonalitet for dette innebygget allerede, jeg har bare ikke kikket på denne.

Kan biblioteket gjøres kortere i "init" biten, og må jeg ha content_type på `$swlib->end_ob('text/css')`.

Jeg servere også HTML sider på samme måte, men da uten cachefunksjonen så filen alltid er fresh. (index.php filen kjører denne).

### 2 libs, burde være 1

src/steinhaug/libs/v1.php
src/steinhaug/libs.php

Disse 2 burde slåes sammen til 1 og samme fil, rett og slett opprydding.