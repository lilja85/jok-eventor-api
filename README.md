# jok-eventor-api
Jönköpings OKs usage of Eventor API - Swedish Orienteering's central IT system

# Tävlingar i Eventor där klubbmedlemmar är anmälda eller har deltagit

Detta projekt är ett PHP-baserat API och en Vue-komponent som hämtar och visar tävlingsdata från Eventor. Syftet är att underlätta för medlemmar att se vilka tävlingar som är andra medlemmar är anmälda till inklusive datum man behöver anmäla sig. Vi listar även tävlingar som passerats för att enkelt kunna hitta resultat och se vilka tävlingar medlemmar deltagit på.

Projektet är utvecklat av **Jönköpings OK** och används som en komponent i klubbens verksamhetssystem **[Zoezi](https://zoezi.se/)**, som bland annat fungerar som CMS.

## Funktioner

- Hämtar tävlingsdata från Eventor via API i endast ett anrop
- Visar kommande och genomförda tävlingar
- Visar deadlines för anmälan och efteranmälan
- Cachelagring för att minska belastning och öka hastighet
- Cron-jobb för automatisk uppdatering i bakgrunden
- Vue-komponent för frontend-visning i [Zoezi](https://zoezi.se/)

## Teknik

- **Backend**: PHP 7+, Eventor API
- **Frontend**: Vue 2/3 (kompatibel med Vuetify)
- **CMS**: Zoezi (via inbäddad komponent)
- **Cache**: JSON-fil med tidsstyrd uppdatering

## Installation proxy-api

Eftersom vi dels inte vill belasta Eventor med ett api-anrop per besökare och dels vill få upp hastigheten behöver vi en proxy som cachar resultatet. Dessutom har Zoezi lagt in begränsningar i CORS som gör att du behöver en egen proxy och kan inte anropa Eventor direkt.

1. Hämta API-nyckeln från [https://eventor.orientering.se/OrganisationAdmin/Settings](https://eventor.orientering.se/OrganisationAdmin/Settings)
2. Klona detta repo till din server där du kan köra PHP (eller flytta med fpt):
   ```bash
   git clone [https://github.com/ditt-klubbnamn/eventor-tavlingsvisning.git](https://github.com/lilja85/jok-eventor-api.git)
   ```
3. Skapa en konfigurationsfil config.php utanför webbroten. Exempelvis i mappen private på samma nivå som public_html:
   ```php
   // config.php
   return [
      'eventor_api_key' => 'DIN_API_NYCKEL_HÄR'
   ];
   ```
4. Uppdatera eventuellt eventor_proxy.php (om du inte lagt den i private på samma nivå som public_html) så att den läser nyckeln från config.php:
   ```php
   $config = require '/sökväg/till/config.php';
   $apiKey = $config['eventor_api_key'];
   ```
5. Skapa ett cron-jobb som uppdaterar cachen exempelvis en gång varje timma:
```bash
curl -s https://din-domän.se/eventor_proxy.php?update=1 > /dev/null
```

## Installation vue-widget i Zoezi

1. Redigera hemsidan genom trycka på kugghjulet
2. Skapa ny komponent under egna komponenter
3. Kalla den exempelvis Eventor tävlingar, ge den en snygg ikon och lägg in html, javascript och css som fu hittar under /zoezi
4. Uppdatera Javascriptet så url går till din proxy
5. Använd din nya komponent på en valfri sida

## Licens
Projektet är licensierat under MIT License, vilket innebär att du fritt får använda, modifiera och distribuera koden – så länge du behåller licenstexten och nämner originalförfattaren (i din egen kod, behöver inte skrivas ut för användarna).

## Bidra
Om din klubb använder detta – kul! Du får gärna bidra med förbättringar via GitHub eller höra av dig med feedback.
