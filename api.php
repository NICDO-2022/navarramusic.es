<?php
date_default_timezone_set('Europe/Madrid');

define("__DEBUG__", false || isset($_GET['debug']));

if (__DEBUG__) {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
}

function nc($var)
{
  return isset($var) && !empty(trim($var));
}

function ncd($var, $def)
{
  return isset($var) && !empty(trim($var)) ? $var : $def;
}

function encode($str)
{
  return utf8_encode(trim($str));
}

require_once('wp-config.php');
require_once('wp-load.php');

function ntrim($str)
{
  return trim(preg_replace('/\s\s+/', ' ', $str));
}

function nicdo_parse($evento)
{
  $row = $evento['es'];

  return [
    'id' => (int)$row->id,
    'title' => [
      'es' => nc($row->title) ? $row->title : '',
    ],
    'date' => $row->date,
    'end' => $row->end,
    'description' => [
      'es' => nc($row->description)
        ? str_replace(
            ['&nbsp;', '\r', '\n', '\t'],
            '',
            strip_tags(ntrim($row->description), '<p><a><ul><li>')
          )
        : '',
    ],
    'images' => [
      'small' => isset($row->logo) && !empty($row->logo) ? isset($row->logo) : '',
      'big' => isset($row->logo) && !empty($row->logo) ? isset($row->logo) : '',
    ],
    'tickets' => '',
    'extra' => []
  ];
}

$sql = 'SELECT
      p1.ID as id,
      p1.post_title as title,
      p1.post_content as description,
      UNIX_TIMESTAMP(STR_TO_DATE(m1.meta_value, "%%Y-%%m-%%d %%h:%%i %%p")) AS date,
      UNIX_TIMESTAMP(STR_TO_DATE(m2.meta_value, "%%Y-%%m-%%d %%h:%%i %%p")) AS end,
      m1.meta_value AS start_datetime,
      m2.meta_value AS end_datetime,
      m3.meta_value AS more_info,
      p2.guid AS logo
      FROM wpva_posts AS p1
      LEFT JOIN wpva_postmeta AS m1 ON p1.id = m1.post_id AND m1.meta_key = "mec_start_datetime"
      LEFT JOIN wpva_postmeta AS m2 ON p1.id = m2.post_id AND m2.meta_key = "mec_end_datetime"
      LEFT JOIN wpva_postmeta AS m3 ON p1.id = m3.post_id AND m3.meta_key = "mec_more_info"
      LEFT JOIN wpva_postmeta AS m4 ON p1.id = m4.post_id AND m4.meta_key = "logo_evento_ok"
      LEFT JOIN wpva_posts AS p2 ON m4.meta_value = p2.id
      WHERE p1.post_type = "mec-events"
      AND m1.meta_value IS NOT NULL
      HAVING
      date > %1$d OR end > %1$d';
$sql = sprintf($sql, mktime(0,0,0,date('n'),1, date('Y')));
$results = $wpdb->get_results($sql);
$eventos = [];
foreach($results as $evento) {
  if (!isset($eventos[$evento->date])) {
    $eventos[$evento->date] = [
      'es' => [],
      'eu' => [],
    ];
  }

  $eventos[$evento->date]['es'] = $evento;
}
$total = count($eventos);

$espectaculos = [];
foreach ($eventos as $evento) {
  $espectaculos[] = nicdo_parse($evento);
}

$json = json_encode($espectaculos);
$error = json_last_error_msg();
$limit = isset($_GET['limit']) && !empty($_GET['limit']) && ctype_digit((string)$_GET['limit'])
  ? (int) $_GET['limit']
  : 50;
$offset = isset($_GET['offset']) && !empty($_GET['offset']) && ctype_digit((string)$_GET['offset'])
  ? (int) $_GET['offset']
  : 0;
$chunks = array_chunk($espectaculos, $limit);

header('Access-Control-Allow-Origin: *');
header("Content-type: application/json; charset=utf-8");
echo json_encode([
  'data' => isset($chunks[$offset])
    ? $chunks[$offset]
    : [],
  'error' => $error,
  'pagination' => [
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
  ],
]);
exit();