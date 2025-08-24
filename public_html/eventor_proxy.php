<?php
// CORS-header – tillåt frontend från jonkopingsok.nu
header("Access-Control-Allow-Origin: https://www.din-domän.se");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET");


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Hantera preflight OPTIONS-anrop
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inställningar
$config = require __DIR__ . '/../private/config.php'; // Om du lagt config.php i /private samt att proxy-skriptet ligger direkt i rooten på din sida
$apiKey = $config['eventor_api_key']; // Säkert sätt att ladda in nyckeln
$organisationId = 000; // Ange din Organisations ID enligt https://eventor.orientering.se/Organisation/Info
$cacheFile = __DIR__ . '/eventor_combined_cache.json';
$cacheLifetime = 10;

// Datumintervall för vad som skall hämtas
$fromDate = date('Y-m-d', strtotime('-7 days'));
$toDate = date('Y-m-d', strtotime('+30 days'));

// Om ingen update-parameter anges, returnera cache direkt
$shouldUpdate = isset($_GET['update']) && $_GET['update'] == '1';

if (!$shouldUpdate && file_exists($cacheFile)) {
    header('Content-Type: application/json');
    echo file_get_contents($cacheFile);
    log_debug("Cache returnerad utan uppdatering.");
    exit;
}

// Om update=1 anges, kontrollera cachetid
if ($shouldUpdate && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
    header('Content-Type: application/json');
    echo file_get_contents($cacheFile);
    log_debug("Cache fortfarande färsk, ingen uppdatering.");
    exit;
}

// Hämta entries med eventdata inkluderat
$entriesUrl = "https://eventor.orientering.se/api/entries?organisationIds=$organisationId&fromEventDate=$fromDate&toEventDate=$toDate&includeEventElement=true";
$entriesXml = api_call($entriesUrl, $apiKey);
if (!$entriesXml) exit_with_error("Kunde inte hämta entries");

$scriptStart = microtime(true);

$entries = new SimpleXMLElement($entriesXml);
$events = [];

foreach ($entries->Entry as $entry) {
    $event = $entry->Event;
    $eventId = (string)$event->EventId;

    if (!isset($events[$eventId])) {
        $name = (string)$event->Name;
        $startDate = (string)$event->StartDate->Date;

        // Hämta alla EntryBreak-datum
        $entryBreaks = $event->xpath('EntryBreak');
        $ordinaryDeadline = null;
        $lateDeadline = null;

        $fallbackToDate = null;

        // Hitta sista entrybreak som har både from och to samt ta en sekund innan from för ordinarie
        foreach (array_reverse($entryBreaks) as $break) {
            $hasFrom = isset($break->ValidFromDate->Date) && isset($break->ValidFromDate->Clock);
            $hasTo = isset($break->ValidToDate->Date) && isset($break->ValidToDate->Clock);

            if ($hasTo) {
                // Spara första ValidToDate som fallback
                $fallbackDate = (string)$break->ValidToDate->Date;
                $fallbackTime = (string)$break->ValidToDate->Clock;
                $fallbackToDate = strtotime("$fallbackDate $fallbackTime");
            }

            if ($hasFrom && $hasTo) {
                
                // Efteranmälningsstopp
                $lateDate = (string)$break->ValidToDate->Date;
                $lateTime = (string)$break->ValidToDate->Clock;
                $lateDeadline = date('Y-m-d H:i', strtotime("$lateDate $lateTime"));

                // Ordinarie anmälningsstopp = en sekund innan efteranmälan börjar
                $fromDate = (string)$break->ValidFromDate->Date;
                $fromTime = (string)$break->ValidFromDate->Clock;
                $ordinaryDeadline = date('Y-m-d H:i', strtotime("$fromDate $fromTime") - 1);

                break; // vi har hittat rätt – avsluta loopen
            }
        }
        
        // Om ingen fullständig EntryBreak hittades, använd fallback
        if (!$ordinaryDeadline && $fallbackToDate) {
            $ordinaryDeadline = date('Y-m-d H:i', $fallbackToDate - 1);
        }

        //$entryUrl = "https://eventor.orientering.se/api/event/$eventId";
        //$entryXml = api_call($entryUrl, $apiKey);
        //log_debug("Entry XML:\n" . $entryXml);

        $events[$eventId] = [
            'eventId' => $eventId,
            'name' => $name,
            'startDate' => $startDate,
            'ordinaryDeadline' => $ordinaryDeadline,
            'lateDeadline' => $lateDeadline,
            'participants' => 0
        ];
    }

    $events[$eventId]['participants']++;
}

// Bygg resultat
$now = date('Y-m-d');
$result = [];

foreach ($events as $event) {
    if (strtotime($event['startDate']) > strtotime($now)) {
        $info = [
            'ordinaryDeadline' => $event['ordinaryDeadline'],
            'lateDeadline' => $event['lateDeadline']
        ];
    } else {
        $info = [
            'resultUrl' => "https://eventor.orientering.se/Events/ResultList?eventId=" . $event['eventId']
        ];
    }
    
    $result[] = [
        'eventId' => $event['eventId'],
        'name' => $event['name'],
        'startDate' => $event['startDate'],
        'participants' => $event['participants'],
        ...$info
    ];
}

// Sortera på startDate stigande
//usort($result, function($a, $b) {
//    return strtotime($a['startDate']) - strtotime($b['startDate']);
//});

$now = strtotime(date('Y-m-d'));

$upcoming = array_filter($result, fn($e) => strtotime($e['startDate']) > $now);
$past = array_filter($result, fn($e) => strtotime($e['startDate']) <= $now);

// Sortera kommande stigande
usort($upcoming, fn($a, $b) => strtotime($a['startDate']) - strtotime($b['startDate']));

// Sortera genomförda fallande
usort($past, fn($a, $b) => strtotime($b['startDate']) - strtotime($a['startDate']));

// Slå ihop igen
$result = array_merge($upcoming, $past);

// Spara och returnera JSON
$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents($cacheFile, $json);
header('Content-Type: application/json');
echo $json;

$scriptEnd = microtime(true);
$duration = round($scriptEnd - $scriptStart, 2);
log_debug("Data hämtad och cache uppdaterad. Bearbetningstid totalt: {$duration} sekunder");

// Hjälpfunktioner
function api_call($url, $apiKey) {
    
    $scriptStart = microtime(true);

    log_debug("Anropar: $url");
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "ApiKey: $apiKey",
        "Accept: application/xml"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $scriptEnd = microtime(true);
    $duration = round($scriptEnd - $scriptStart, 2);
    log_debug("API-anrop tog {$duration} sekunder");
    
    // Spara XML till fil om lyckat
    if ($httpCode === 200) {
        dump_eventor_response($response);
        return $response;
    }

    return false;
}

function log_debug($msg) {
    file_put_contents(__DIR__ . '/eventor_debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

function exit_with_error($msg) {
    log_debug("Fel: $msg");
    http_response_code(500);
    echo json_encode(['error' => $msg]);
    exit;
}

function dump_eventor_response($xml) {
    $dumpDir = __DIR__ . '/eventor_dumps';
    if (!is_dir($dumpDir)) {
        mkdir($dumpDir);
    }

    // Rensa gamla filer (> 1 dag gamla)
    foreach (glob($dumpDir . '/*.xml') as $file) {
        if (filemtime($file) < time() - 86400) {
            unlink($file);
        }
    }

    // Spara ny dump med tidsstämpel
    $filename = $dumpDir . '/eventor_' . date('Ymd_His') . '.xml';
    $prettyXml = format_xml_pretty($xml);
    file_put_contents($filename, $prettyXml);
    log_debug("Eventor-svar dumpat till: $filename");
}

function format_xml_pretty($xmlString) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;

    // Ladda XML och hantera fel
    if (@$dom->loadXML($xmlString)) {
        return $dom->saveXML();
    } else {
        log_debug("Kunde inte format-parsea XML.");
        return $xmlString; // fallback till original
    }
}
