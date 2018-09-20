<!DOCTYPE html>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style type="text/css">
		.chat_wrapper {
			width: 70%;
			height:472px;
			margin-right: auto;
			margin-left: auto;
			background: #3B5998;
			border: 1px solid #999999;
			padding: 10px;
			font: 14px 'lucida grande',tahoma,verdana,arial,sans-serif;
		}

		.chat_wrapper .message_box {
			background: #F7F7F7;
			height:350px;
				overflow: auto;
			padding: 10px 10px 20px 10px;
			border: 1px solid #999999;
		}

		.system_msg{color: #BDBDBD;font-style: italic;}
		.phone_msg{color: #404DA4;font-weight:bold;font-size: 12px;}
		.phone_manque{color: #DF0101;font-weight:bold;font-size: 12px;}
		.user_name{font-weight:bold;}
		.user_message{color: #88B6E0;}

		@media only screen and (max-width: 720px) {
			/* For mobile phones: */
			.chat_wrapper {
				width: 95%;
			height: 40%;
			}
		}
	</style>
</head>
<body>	
<script src="jquery-3.3.1.js"></script>
<script language="javascript" type= "text/javascript">
$(document).ready(function(){
			//create a new WebSocket object.
			var wsUri = "ws://192.168.168.121:9000/projet/s.php"; 	
			websocket = new WebSocket(wsUri); 
			
			websocket.onopen = function(ev) { // connection is open 
				$('#message_box').append('<div class="system_msg">Connecté!</div>'); //notify user
			}

			//#### Message received from server?
			websocket.onmessage = function(ev) {
				var msg = JSON.parse(ev.data); //PHP sends Json data
				var type = msg.type; //message type
				var umsg = msg.message; //message text
				var uname = msg.name; //user name
				var ucolor = msg.color; //color

				if(type == 'system'){
					$('#message_box').append("<div class=\"system_msg\">"+umsg+"</div>");
				}
				if(type == 'phone'){
					$('#message_box').append("<div class=\"phone_msg\">"+umsg+"</div>");
				}
				if(type == 'manque'){
					$('#message_box').append("<div class=\"phone_manque\">"+umsg+"</div>");
				}
				$('#message').val(''); //reset text
				
				var objDiv = document.getElementById("message_box");
				objDiv.scrollTop = objDiv.scrollHeight;
			};
			
			websocket.onerror	= function(ev){$('#message_box').append("<div class=\"system_error\">Error Occurred - "+ev.data+"</div>");}; 
			websocket.onclose 	= function(ev){$('#message_box').append("<div class=\"system_msg\">Connection fermée</div>");}; 
		});
	</script>

	<div class="chat_wrapper">
		<div class="message_box" id="message_box"></div>
	</div>

</body>
</html>