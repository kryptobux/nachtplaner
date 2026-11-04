<?php
// Reicht die 7Timer-ASTRO-Vorhersage (Seeing, Transparenz) an den Nachtplaner weiter,
// weil 7Timer keine Browser-Abrufe von fremden Seiten erlaubt. Nur Breite und Länge,
// auf 0,01° gerundet; Antworten werden 3 Stunden je Ort zwischengespeichert.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
if ($lat === false || $lat === null || $lon === false || $lon === null || abs($lat) > 90 || abs($lon) > 180) {
  http_response_code(400);
  echo '{"fehler":"lat und lon fehlen oder liegen ausserhalb des Bereichs"}';
  exit;
}
$lat = round($lat, 2);
$lon = round($lon, 2);
$dir = __DIR__ . '/cache7';
if (!is_dir($dir)) @mkdir($dir, 0755);
$datei = sprintf('%s/%.2f_%.2f.json', $dir, $lat, $lon);
if (is_file($datei) && time() - filemtime($datei) < 3 * 3600) {
  readfile($datei);
  exit;
}
$url = sprintf('https://www.7timer.info/bin/astro.php?lon=%.2f&lat=%.2f&ac=0&unit=metric&output=json&tzshift=0', $lon, $lat);
$daten = false;
if (function_exists('curl_init')) {
  $c = curl_init($url);
  curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'nachtplaner/0.2']);
  $daten = curl_exec($c);
  if (curl_getinfo($c, CURLINFO_HTTP_CODE) !== 200) $daten = false;
  curl_close($c);
} else {
  $ctx = stream_context_create(['http' => ['timeout' => 20, 'header' => "User-Agent: nachtplaner/0.2\r\n"]]);
  $daten = @file_get_contents($url, false, $ctx);
}
$json = $daten === false ? null : json_decode($daten, true);
if (!is_array($json) || !isset($json['dataseries'])) {
  http_response_code(502);
  echo '{"fehler":"7Timer nicht erreichbar"}';
  exit;
}
$aus = json_encode(['init' => $json['init'], 'dataseries' => array_map(function ($d) {
  return ['timepoint' => $d['timepoint'], 'seeing' => $d['seeing'], 'transparency' => $d['transparency']];
}, $json['dataseries'])]);
@file_put_contents($datei, $aus);
echo $aus;
