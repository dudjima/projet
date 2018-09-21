<?php
require_once 'config.php';																													//On integre le fichier de parametres

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);																						//Create TCP/IP sream socket
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);																					//reuseable port
socket_bind($socket, 0, $port);																												//bind socket to specified host
socket_listen($socket);																														//listen to port
$clients = array($socket);																													//create & add listning socket to the list

while (true) {																																//start endless loop, so that our script doesn't stop
	$changed = $clients; 																													//manage multipal connections
	socket_select($changed, $null, $null, 0, 10); 																							//returns the socket resources in $changed array
		
	if (in_array($socket, $changed)) {																										//check for new socket
		$socket_new = socket_accept($socket); 																								//accept new socket
		$header = socket_read($socket_new, 9999);								 															//read data sent by the socket
		echo "Header : ".$header."/\r\n/";
		$val = preg_match_all("/(?<=\/\?|&)([^=]+)=([^&\s]*)/", $header, $matches);
		$args = array();
			foreach($matches[1] as $i => $arg) { 
				$args[$arg] = $matches[2][$i];	
				echo "argument : ".$arg." valeur : ".$args[$arg]."\n"; 
				}		
		if( $val > 3 && strlen($args['num'])>5){ 														// on a des valeurs récupéré et on les listes
			$args['num'] = trim(str_replace('+33', '0', $args['num'])); 	
			$args['num'] = substr($args['num'], 0, 2).' '.substr($args['num'], 2, 2).' '.substr($args['num'], 4, 2).' '.substr($args['num'], 6, 2).' '.substr($args['num'], 8, 2);
			echo "numero : ".$args['num']."\n";
			if(preg_match('/voxity/', $args['remote']) == 0){ //c'est un appel recu
				switch($args['etat']){
					case 'manque':
						$response = mask(json_encode(array('pseudo'=>'Arnaud', 'type'=>'manque', 'message' =>'[MANQUE] de '.$args["num"].' le '.date("d-m H:i").'<a href="https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$args["num"].'" target="blank"> lien </a>' ))); //prepare json data
						send_message1($response, $args['ip']); 
						break;
					case 'pris';
						$response = mask(json_encode(array('pseudo'=>'Arnaud','type'=>'phone', 'message' =>'Appel de '.$args["num"].' le '.date("d-m H:i").'<script language="javascript">
						window.open("https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$args["num"].'");</script> <a href="https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$args["num"].'" target="blank"> lien </a>' ))); //prepare json data
						send_message1($response, $args['ip']); 
						break;
					default:
						break;
				}
			}
		}else{ 																																//c'est une nouvelle connexion
			$clients[] = $socket_new; 																										//Add socket to client array si pas téléphone on ajoute la socket
			perform_handshaking($header, $socket_new, $host, $port); 																		//perform websocket handshake
			socket_getpeername($socket_new, $ip); 
		}
		$found_socket = array_search($socket, $changed); 																					//Make room for new socket
		unset($changed[$found_socket]);
	}
	
	foreach ($changed as $changed_socket) {																									//loop through all connected sockets
		while(socket_recv($changed_socket, $buf, 1024, 0) >= 1){																			//check for any incomming data
			$received_text = unmask($buf); 																									//unmask data
			$tst_msg = json_decode($received_text); 																						//json decode 
			$user_name = $tst_msg->name; 																									//sender name
			$user_message = $tst_msg->message; 																								//message text
			$user_color = $tst_msg->color; 																									//color
			$response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>$user_message, 'color'=>$user_color)));//prepare data to be sent to client
			send_message($response_text); 																									//send data
			break 2; 																														//exit this loop
		}
		$buf = socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) { 																												// check disconnected client	
			$found_socket = array_search($changed_socket, $clients);																		// remove client for $clients array
			socket_getpeername($changed_socket, $ip);
			unset($clients[$found_socket]);			
			//$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' disconnected')));											//notify all users about disconnected connection
			//send_message($response);
		}
	}
}
socket_close($socket);																														// close the listening socket

function send_message($msg){
	global $clients;
	foreach($clients as $changed_socket){ 
         socket_write($changed_socket,$msg,strlen($msg));
	}
	return true;
}

function send_message1($msg,$ip_envoi){
	global $clients;
	foreach($clients as $changed_socket){ 																									// On parcours les différentes sockets
        @socket_getpeername($changed_socket, $ip); 																							// On recupère l'ip de la socket du tableau
        if($ip_envoi === $ip){
			socket_write($changed_socket,$msg,strlen($msg));																				// On ecrit le message dans le socket
			break;																															// On a envoyé le message pas la peine de boucler
        }        
	}
	return true;
}

function unmask($text) {																													//Unmask incoming framed message
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $masks[$i%4];
	}
	return $text;
}

function mask($text){																														//Encode message for transfer to client.
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}


function perform_handshaking($receved_header,$client_conn, $host, $port){																	//handshake new client.
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line){
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)){
			$headers[$matches[1]] = $matches[2];
		}
	}

	$secKey = $headers['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
	//hand shaking header
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
}
