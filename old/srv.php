<?php

error_reporting(E_ALL);
set_time_limit(0);
$adresse = "192.168.168.121"; //host
$port = 9000; //port
$null = null;
$clients=array();
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
// on boucle
while(true){
    $socket_new = socket_accept($socket); // on accepte les connexions
    $message    = socket_read($socket_new, 1024); // on recupere le socket
    preg_match('/[0-9]{1,3}[.][0-9]{1,3}[.][0-9]{1,3}[.][0-9]{1,3}/', $message, $ip_recup);
     $test =preg_match('/[0-9]{10}/', $message, $num_recup);
    if($test == 0){ //il s'agit d'une connexion 
        echo 'nouvelle connexion cliente';
        $clients = $socket_new; // on ajoute le client
        echo $clients;
    }else{ // il s'agit d'un appel
        echo 'ip_recup : '. $ip_recup[0]." \n ";
        echo 'num_recup : '. $num_recup[0]."\n";
        //si le mot cle Ip est present il s'agit d'un appel 
        foreach($clients as $skt){
            $val = $message    = socket_read($skt, 1024);
            if( preg_match($ip_recup[0], $val )== true){
                echo 'on envoi le message'
                $reponse = mask(json_encode(array('message'=>'<script language="javascript">
                window.open("https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$num_recup.'");</script> <a href="https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$num_recup.'" target="blank"> lien </a>' ))); //prepare json data
            //socket_write($clients[$ip_recup[0]],$reponse,strlen($reponse)); // on envoi le message
            //socket_close($clients[$ip_recup]);
            socket_write($socket,$reponse,strlen($reponse));             
            }
        }
        //socket_getpeername($socket_new,$adresse_new);
        //echo "on recupere le numero et l'ip demandé\n";
        //$reponse = mask(json_encode(array('message'=>'<script language="javascript">
        //    window.open("https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$num_recup.'");</script> <a href="https://go.mytiger.pro/index.php?action=UnifiedSearch&module=Home&search_onlyin=Accounts%2CContacts%2CLeads&query_string='.$num_recup.'" target="blank"> lien </a>' ))); //prepare json data
        //socket_write($clients[$ip_recup[0]],$reponse,strlen($reponse)); // on envoi le message
        //socket_close($clients[$ip_recup]);
        //socket_write($socket,$reponse,strlen($reponse));
    }


}
?>