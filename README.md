# jok-eventor-api
Jönköpings OKs usage of Eventor API - Swedish Orienteering's central IT system

# Tävlingar i Eventor där klubbmedlemmar är anmälda eller har deltagit

Detta projekt är ett PHP-baserat API och en Vue-komponent som hämtar och visar tävlingsdata från Eventor. Syftet är att underlätta för medlemmar att se vilka tävlingar som andra medlemmar är anmälda till inklusive datum för ordinarie/efteranmälan anmälningsstopp. Vi listar även tävlingar som passerats för att enkelt kunna hitta resultat och se vilka tävlingar medlemmar deltagit på.

Projektet är utvecklat av **Jönköpings OK** och används som en komponent i klubbens verksamhetssystem **[Zoezi](https://zoezi.se/)**, som bland annat fungerar som CMS.

## Funktioner

- Hämtar tävlingsdata från Eventor via API i endast ett anrop per timma
- Cachelagring för att minska belastning och öka hastighet
- Visar kommande och genomförda tävlingar
- Visar deadlines för anmälan och efteranmälan
- Automatisk uppdatering i bakgrunden med Cron-jobb
- Vue-komponent för frontend-visning i [Zoezi](https://zoezi.se/)

## Teknikska krav

För att kunna köra detta behöver du
- PHP 7+ server/webbhotell
- Kunna anropa en sida på schema, exempelvisa via cron-jobs (kanske på ditt webbhotell)
- Zoezi för frontend där Vue 2/3 (kompatibel med Vuetify) används (för exakt samma, annars fritt fram använda annan frontend)

## Installation av proxy-api

Eftersom vi dels inte vill belasta Eventor med ett api-anrop per besökare och dels vill få upp hastigheten behöver vi en proxy som cachar resultatet. Dessutom har Zoezi lagt in begränsningar i CORS som gör att du behöver en egen proxy och kan inte anropa Eventor direkt.

1. Hämta API-nyckeln från [https://eventor.orientering.se/OrganisationAdmin/Settings](https://eventor.orientering.se/OrganisationAdmin/Settings). Kolla även Organisation ID på [https://eventor.orientering.se/Organisation/Info](https://eventor.orientering.se/Organisation/Info)
2. Klona detta repo till din server där du kan köra PHP (eller flytta med fpt):
   ```bash
   git clone [https://github.com/ditt-klubbnamn/eventor-tavlingsvisning.git](https://github.com/lilja85/jok-eventor-api.git)
   ```
3. Skapa en konfigurationsfil config.php utanför webbroten. Exempelvis i mappen private på samma nivå som public_html:
   ```php
   <?php
   // config.php
   return [
      'eventor_api_key' => 'DIN_API_NYCKEL_HÄR'
   ];
   ```
4. Uppdatera eventor_proxy.php så att följande rader är korrekt för dig:
   ```php
   <?php
   header("Access-Control-Allow-Origin: https://din-domän.se");
   $config = require __DIR__ . '/../private/config.php'; // Om du lagt config.php i /private samt att proxy-skriptet ligger direkt i rooten på din sida
   $apiKey = $config['eventor_api_key']; // Säkert sätt att ladda in nyckeln
   $organisationId = 000; // Ange din Organisations ID enligt https://eventor.orientering.se/Organisation/Info
   ```
5. Skapa ett cron-jobb som uppdaterar cachen exempelvis en gång varje timma:
```bash
curl -s https://din-domän.se/eventor_proxy.php?update=1 > /dev/null
```

## Installation vue-widget i Zoezi

1. Redigera hemsidan genom trycka på kugghjulet
2. Skapa ny komponent under egna komponenter
3. Kalla den exempelvis Eventor tävlingar, ge den en snygg ikon och lägg in html, javascript och css som du hittar under /zoezi
4. Uppdatera Javascriptet så url går till din proxy
5. Använd din nya komponent på en valfri sida

## Licens
Projektet är licensierat under MIT License, vilket innebär att du fritt får använda, modifiera och distribuera koden – så länge du behåller licenstexten och nämner originalförfattaren (i din egen kod, behöver inte skrivas ut för användarna).

## Bidra
Om din klubb använder detta – kul! Du får gärna bidra med förbättringar via GitHub eller höra av dig med feedback.
