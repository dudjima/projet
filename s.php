<?php
$host = '192.168.168.121'; //host
$port = '9000'; //port
$null = NULL; //null var
date_default_timezone_set('Europe/Paris');

//$i=0;
//Create TCP/IP sream socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//reuseable port
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

//bind socket to specified host
socket_bind($socket, 0, $port);

//listen to port
socket_listen($socket);

//create & add listning socket to the list
$clients = array($socket);

//start endless loop, so that our script doesn't stop
while (true) {
	//manage multipal connections
	$changed = $clients;
	//returns the socket resources in $changed array
	socket_select($changed, $null, $null, 0, 10);
	
	//check for new socket
	if (in_array($socket, $changed)) {
		$socket_new = socket_accept($socket); //accept new socket
		
		$header = socket_read($socket_new, 9999); //read data sent by the socket
		echo "Header : ".$header."/\r\n/";

		if (strpos($header,'num')>0){ // il s'agit d'un socket venant d'un tel
				//on cherche l'ip dans le header
				preg_match('/[0-9]{1,3}[.][0-9]{1,3}[.][0-9]{1,3}[.][0-9]{1,3}/', $header, $ip_recup);
				$line = chop($line);			
				
				$lines = preg_split("/\r\n/", $header);

					preg_match('~Y(.*?)Z~', $header, $matches1);
					$num = substr($matches1[1], 4, 12);
					$num = trim(str_replace('&','', $num));
					$num = trim(str_replace('+33','0', $num));
					$num= substr($num, 0, 2).' '.substr($num, 2, 2).' '.substr($num, 4, 2).' '.substr($num, 6, 2).' '.substr($num, 8, 2);
					if(strlen($num)>9){	// si le numero est superieur à 9 c'est un appel valide sinon c'est un appel interne et on laisse          
						if(strpos($header,'manque')>0){ //c'est un appel manqué
							socket_getpeername($socket_new, $ip); //get ip address of connected socket
							$response = mask(json_encode(array('pseudo'=>'Arnaud', 'type'=>'manque', 'message' =>'[MANQUE] de '.$num.' le '.date("d-m H:i").'<a href="https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$num.'" target="blank"> lien </a>' ))); //prepare json data								
							send_message1($response,$ip_recup[0]); //notifie tout les utilisateur
						}else{
							socket_getpeername($socket_new, $ip); //get ip address of connected socket
							$response = mask(json_encode(array('pseudo'=>'Arnaud','type'=>'phone', 'message' =>'appel de '.$num.' le '.date("d-m H:i").'<script language="javascript">
							window.open("https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$num.'");</script> <a href="https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$num.'" target="blank"> lien </a>' ))); //prepare json data								
							send_message1($response, $ip_recup[0]); //notifie l'utilisateur  

						}
					}			
		}else{ // il s'agit d'une connexion client
			$clients[] = $socket_new; //add socket to client array si pas téléphone on ajoute la socket
			$i=1;
			perform_handshaking($header, $socket_new, $host, $port); //perform websocket handshake
			socket_getpeername($socket_new, $ip); //get ip address of connected socket
			//$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' connected'))); //prepare json data
			//send_message($response); //notify all users about new connection
			
			//prepare data to be sent to client
				//$response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>'Arnaud', 'message'=>'Message'));
				//send_message($response_text); //send data
		}
		
		//make room for new socket
		$found_socket = array_search($socket, $changed);
		unset($changed[$found_socket]);
	}
	
	//loop through all connected sockets
	
	//if ($i==1)
	//{
		foreach ($changed as $changed_socket) {	
			
			//check for any incomming data
			while(socket_recv($changed_socket, $buf, 1024, 0) >= 1){
				$received_text = unmask($buf); //unmask data
				//print "AAAAAAAAAAAAAAAAA      ".$received_text;
				$tst_msg = json_decode($received_text); //json decode 
				$user_name = $tst_msg->name; //sender name
				$user_message = $tst_msg->message; //message text
				$user_color = $tst_msg->color; //color
				
				//prepare data to be sent to client
				$response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>$user_message, 'color'=>$user_color)));
				send_message($response_text); //send data
				break 2; //exist this loop
			}
			
			$buf = socket_read($changed_socket, 1024, PHP_NORMAL_READ);
			if ($buf === false) { // check disconnected client
				// remove client for $clients array
				$found_socket = array_search($changed_socket, $clients);
				socket_getpeername($changed_socket, $ip);
				unset($clients[$found_socket]);
				
				//notify all users about disconnected connection
				$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' disconnected')));
				send_message($response);
			}
		}
	//}
}
// close the listening socket
socket_close($socket);

function send_message($msg){
	global $clients;
	foreach($clients as $changed_socket){ 
            socket_write($changed_socket,$msg,strlen($msg));
	}
	return true;
}
function send_message1($msg,$ip_envoi){
	global $clients;
	foreach($clients as $changed_socket){
        socket_getpeername($changed_socket, $ip);
        echo 'ip recupéré : '.$ip_envoi.'ip comparé '. $ip ;
        if($ip_envoi === $ip){
            socket_write($changed_socket,$msg,strlen($msg));
        }        
	}
	return true;
}

//Unmask incoming framed message
function unmask($text) {
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

//Encode message for transfer to client.
function mask($text){
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

//handshake new client.
function perform_handshaking($receved_header,$client_conn, $host, $port){
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
