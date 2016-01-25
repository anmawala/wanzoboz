<?php

define('BOT_TOKEN', '153950695:AAFxpRS358JiOMB4FdMdF6qZ4xv66WTz9bY');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}
function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}
function avvisi($chat_id) {
  $finale = "Questi sono gli <b>ultimi 5 avvisi</b> presenti su Campusnet:\n";
  $content = file_get_contents("http://medtriennaliasl4.campusnet.unito.it/do/avvisi.pl/Search");
  preg_match_all('/(?:hits=[\d]*">)(.*?)(?:<)/',$content,$avvisi);
  preg_match_all('/(?:A HREF=")(.*?)(?:;sort)/',$content,$links);
  for ($i=0; $i < 5 ; $i++) {
    $link = "http://medtriennaliasl4.campusnet.unito.it".$links[1][$i];
    $avviso = utf8_encode($avvisi[1][$i]);
    $finale .= "\n".($i+1)."."."\t"."<a href=\"$link\">$avviso</a>";
  }
  apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $finale, "parse_mode" => "HTML"));
  return $finale;
}
function appelli($chat_id) {
  $finale = "Queste sono le <b>ultime 5 ADE</b> aggiunte su Campusnet:\n";
  $finale_inline = "<b>ultime 5 ADE</b> aggiunte:\n";
  $content = file_get_contents("http://medtriennaliasl4.campusnet.unito.it/do/appelli.pl/Search?since=&days=10&sort=TIME&format=&cookie=NEWS");
  preg_match_all('/(?:hits=[\d]*"><B>)(.*?)(?:<\/B>)/',$content,$appelli);
  preg_match_all('/(?:px">Luogo: )(.*?)(?:<\/div><\/TD>)/',$content,$sedi);
  preg_match_all('/(?:TOP><A HREF=")(.*?)(?:;sort)/',$content,$links);
  preg_match_all('/(?:OWRAP>)(.*?)(?:<BR>)/',$content,$date);
  preg_match_all('/(?:ore<\/I>:)(.*?)(?:<BR>)/',$content,$orari);
  for ($i=0; $i < 5 ; $i++) {
    $link = 'http://medtriennaliasl4.campusnet.unito.it'.$links[1][$i];
    $appello = utf8_encode($appelli[1][$i]);
    $appello = str_replace("a.a. 15/16 - ", "", $appello);
    $sede = utf8_encode($sedi[1][$i]);
    if(preg_match('/HREF/', $sede)==1) {
      preg_match_all('/(?:>)(.*?)(?:<)/', $sede, $sede);
      $sede = utf8_encode($sede[1][0]);
    }
    $data = $date[1][$i];
    $data = strip_tags($data);
    $orario = $orari[1][$i];
    $finale .= "\n".($i+1)."."."\t"."<a href=\"$link\">$appello</a>"."\n"."Orario: ".$orario."\t\t"."Data: ".$data."\n"."Sede: ".$sede."\n\n";
    $finale_inline .= ($i+1)."."."\t"."$appello"."\n";
  }
  apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $finale, "parse_mode" => "HTML"));
  return $finale_inline;
}
function elencoComandi($chat_id, $comandi) {
  $elenco = "I comandi attualmente disponibili (non case sensitive) sono:\n
-\t*$comandi[0]*\t_Mostra gli ultimi 5 avvisi di Campusnet_
-\t*$comandi[5]*\t_Mostra le ultime 5 ADE aggiunte su Campusnet_
-\t*$comandi[1]*\t_relativa al 2015/2016_
-\t*$comandi[2]*\t_relativa al 2015/2016_
-\t*$comandi[3]*\t_Invia gli orari di lezione dei 3 anni (I semestre)_
-\t*$comandi[6]*\t_Invia il calendario esami da Febbraio a Dicembre 2016 DM270/04_
-\t*$comandi[4]*\t_Apre una tastiera con i comandi (_ *Chiudi*_ per chiuderla)_

  /help\t_Riepiloga i comandi disponibili_
  ";
  apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $elenco, "parse_mode" => "Markdown"));
}
function tastieraComandi($chat_id, $comandi) {
  apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Ecco la tastierina dei comandi", "reply_markup" => array("keyboard" => array(array("Avvisi", "ADE"), array("Orari", "Piano studi"), array("Calendario esami"), array("Programmazione didattica"), array("/help")),"resize_keyboard" => true, "one_time_keyboard" => false)));
}
function processMessage($message) {
  // process incoming message
  $comandi = array("Avvisi", "Programmazione didattica", "Piano studi", "Orari", "Tastiera", "ADE", "Calendario esami");
  $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];
  if (isset($message['text']))
  {
    // incoming text message
    $text = $message['text'];
    if($chat_id!=22699108) apiRequest("sendMessage", array('chat_id' => 22699108, "text" => $message["from"]["first_name"]." ".$message["from"]["last_name"]." @".$message["from"]["username"]." ".$text));
    if (preg_match('/[A,a]vvisi/',$text)==1) {
      avvisi($chat_id);
    }
    else if (preg_match('/ADE/',$text)==1) {
      appelli($chat_id);
    }
    else if (preg_match('/\/start/',$text)==1) {
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Ciao ".$message["from"]["first_name"].". Benvenuto/a nel BOT (non ufficiale) del Corso di Laurea di Infermieristica To2", "parse_mode" => "Markdown"));
      elencoComandi($chat_id, $comandi);
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "_Digita o premi e invia al BOT il comando desiderato_","parse_mode" => "Markdown"));
      tastieraComandi($chat_id, $comandi);
    }
    else if (preg_match('/\/help/',$text)==1) {
      elencoComandi($chat_id, $comandi);
    }
    else if (preg_match('/[P,p]rogrammazione [D,d]idattica/',$text)==1) {
      apiRequest("sendDocument", array('chat_id' => $chat_id, "document" => "BQADAgADcQMAAmRcWgEwELLnffwxAAEC"));
    }
    else if (preg_match('/[P,p]iano [S,s]tudi/',$text)==1) {
      apiRequest("sendDocument", array('chat_id' => $chat_id, "document" => "BQADAgADcgMAAmRcWgEcV4sMywABpd8C"));
    }
    else if (preg_match('/[C,c]alendario [E,e]sami/',$text)==1) {
      apiRequest("sendDocument", array('chat_id' => $chat_id, "document" => "BQADAgADkwMAAmRcWgGAGHRrXWC4BQI"));
    }
    else if (preg_match('/[O,o]rari/',$text)==1) {
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "1° anno I semestre"));
      apiRequest("sendDocument", array('chat_id' => $chat_id, "document" => "BQADAgADcwMAAmRcWgEjPbNmeZg69wI"));
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "2° anno I semestre"));
      apiRequest("sendDocument", array('chat_id' => $chat_id, "document" => "BQADAgADdAMAAmRcWgGfmubLVh-RlAI"));
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "3° anno I semestre"));
      apiRequest("sendDocument", array('chat_id' => $chat_id, "document" => "BQADAgADdQMAAmRcWgFpF1HAl582aAI"));
    }
    else if (preg_match('/[T,t]astiera/',$text)==1) {
      tastieraComandi($chat_id, $comandi);
    }
    else if (preg_match('/[C,c]hiudi/',$text)==1) {
      apiRequest("sendMessage", array("chat_id" => $chat_id, "text" => "Chiusura tastiera", "reply_markup" => array("hide_keyboard" => true)));
      }
    else if (preg_match('/data/',$text)==1) {
        apiRequest("sendMessage", array("chat_id" => array(22699108,22699108), "text" => "CIAO"));
    }
    else {
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Mi spiace, nessun azione disponibile con questo comando."));
    }
  }
  else {
    apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Mi spiace, leggo solo i messaggi di testo'));
  }
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
  processMessage($update["message"]);
}

if (isset($update["message"]["document"])) {
 $file_id = $update["message"]["document"]["file_id"];
 apiRequest("sendMessage", array('chat_id' => 22699108, "text" => $file_id));
}

if (isset($update["inline_query"]["query"])) {
  $id = $update["inline_query"]["id"];
  $query = $update["inline_query"]["query"];
  $chat_id = $update["inline_query"]["from"]["id"];
  if ($query=="avvisi") {
    $avvisi = avvisi("0");
    apiRequest('answerInlineQuery', array('inline_query_id' => $id, 'results' => array(array("type" => "article","id" => "0","title" => "Mostra gli ultimi 5 avvisi pubblicati su Campusnet","message_text" => $avvisi, "parse_mode" => "HTML", "cache_time" => 0))));
  }
  else if ($query=="ade") {
    $ade = appelli("0");
    apiRequest('answerInlineQuery', array('inline_query_id' => $id, 'results' => array(array("type" => "article","id" => "0","title" => "Mostra le ultime 5 ADE pubblicate su Campusnet","message_text" => $ade, "parse_mode" => "HTML", "cache_time" => 0))));
  }
  else if ($query=="presenta") {
    apiRequest('answerInlineQuery', array('inline_query_id' => $id, 'results' => array(array("type" => "article","id" => "0","title" => "Presenta il bot","message_text" => "*CLITo2Bot* è il bot, creato da uno studente in via non ufficiale, che permette di utilizzare alcuni strumenti utili come la lettura di avvisi o la consultazione di documenti didattici presenti su Campusnet. Per iniziare ad utilizzare questo Bot, premi [QUI](telegram.me/CLITo2Bot?start=link).", "parse_mode" => "Markdown", "cache_time" => 0))));
  }
}

?>
