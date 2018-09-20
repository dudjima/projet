<?php

function perform_handshaking($receved_header,$client_conn, $host, $port){
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
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

error_reporting(E_ALL);

/* Autorise l'exécution infinie du script, en attente de connexion. */
set_time_limit(0);
$host = "192.168.168.121"; //host
$port = 9000; //port
$null = null;
//Creation du socket pour le serveur
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false){
    echo "erreur socket create : " . socket_last_error();
}

//Modification des options du socket
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

//Liaison du port
socket_bind($socket, 0, $port);
if ($socket === false){
    echo "erreur socket bind : " . socket_last_error();
}

//Ecoute du port
socket_listen($socket);
if ($socket === false){
    echo "erreur socket listen : " . socket_last_error();
}

//liste des clients dans un tableau
$clients = array($socket);

// tant que le serveur tourne alors on ecoute le port
while(true){
    
    //gestion multi-clients
    $changed = $clients;
    socket_select($changed, $null, $null, 0, 10);

    if(in_array($socket, $changed)){
       
        //on ajoute une connexion
        $socket_new = socket_accept($socket);
        $clients[]  .= $socket_new; 
        echo "nouvelle connexion";
        // on lit les donnees de la nouvelle socket
        $entete    = socket_read($socket_new, 1024);
        echo 'get ip : '. $_GET['ip'];
        echo 'get num : '. $_GET['num'];

        $ip_recup  = preg_match('/[0-9]{1,3}[.][0-9]{1,3}[.][0-9]{1,3}[.][0-9]{1,3}/', $entete, $ip_recup);
        $num_recup = preg_match_all('/[0-]{10-12}/', $entete, $num_recup);
        echo $entete;
        echo 'on trouve l\'ip : '. $ip_recup;
        echo 'num = '.$num_recup;
        $ip_recup = '192.168.168.21';
        foreach ($clients as $valeur) {
            if (socket_getpeername($valeur, $ip_recup) == true){
                $reponse = mask(json_encode(array('pseudo' => 'Arnaud', 'type'=>'phone', 'message' => 'appel de '.$num_recup.' le '.date("d-m H:i").'<script language="javascript">
                window.open("https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$num_recup.'");</script> <a href="https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$num_recup.'" target="blank"> lien </a>' ))); //prepare json data
                socket_write($valeur, $reponse);
            }
        }

        // On recupère l'ip du client et le port
        // Perform_handshaking($header, $socket_new, $host, $port); //perform websocket handshake
        // Socket_getpeername($socket_new, $ip); //get ip address of connected socket
    }   
}

?>