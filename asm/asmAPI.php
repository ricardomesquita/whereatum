<?php    

    error_reporting(E_ERROR | E_PARSE);
	require_once("Rest.inc.php");
	require_once("../PHPMailer-master/class.phpmailer.php");
	
	class API extends REST {
	
		public $data = "";
        private $args = Array();	
		private $db = NULL;
		
		// Google Cloud Messaging API Key
		// Place your Google API Key		
		//private $GOOGLE_API_KEY = "AIzaSyCuhXUzY9EZcV_1PmbeSF85CMxhLwfn4HU";
		private $GOOGLE_API_KEY = "AIzaSyCKkCJKIvL5SHjo2jH5n-KjLgnPqR5u738";
	
		public function __construct(){
			parent::__construct();				// Init parent contructor
			$this->dbConnect();					// Initiate Database connection
		}
		
		/*
		 *  Database connection 
		*/
		private function dbConnect(){
            try {		
		        $this->db = new PDO('mysql:host=127.0.0.1;dbname=whereatum;charset=utf8', 'a58666','44574' , array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
            }catch(PDOException $e){
                print "Error!: " . $e->getMessage();
            }
		}
		
		/*
		 * Public method for access api.
		 * This method dynmically call the method based on the query string
		 *
		 */
		public function processApi(){
            if(empty($_REQUEST['rquest']))
            {
                $this->response('No endpoint',404);
            }            
            $this->args = explode("/",$_REQUEST['rquest']);
            
            //get the method
            if (count($this->args) < 3)
            {
                $func = $this->args[0];
            }else
                $func = $this->args[0].$this->args[2];                                                                     
            
            if((int)method_exists($this,$func) > 0)
            	$this->$func();
            else
            	$this->response('',404);				// If the method not exist with in this class, response would be "Page not found".
                    
            //validate Oauth and get userID
           
                     
		}       
	
		private function teste(){
			//$fingerprint = $this->getLastFingerprintFromDB("90:18:7C:70:2B:75");	// Meneses
			//$fingerprint = $this->getLastFingerprintFromDB("c4:43:8f:cf:5f:8e");	// Diogo
			//$fingerprint = $this->getLastFingerprintFromDB("d8:50:e6:7d:55:ad");	// Ponto
			//$fingerprint = $this->getLastFingerprintFromDB("1");	// Ciclista		
			//$result = $this->fingerprintsRankingV2teste($fingerprint['fingerprint']);
			$url =  'http://rtls.dsi.uminho.pt:8080/RTLSQ/q1';
			$data = array('f' => '1','deviceID' =>"d8:50:e6:7d:55:ad");
			$result = $this->httpPOST($url,$data);  
			print_r( $result);
		}		
        /* ______________________________ Login ______________________________*/        
        
		private function userslogin(){
			
			if($this->get_request_method() != "POST" || empty($this->_raw['email']) || empty($this->_raw['password'])){
				$this->response('',400);
			}
			
			$email = $this->_raw['email'];		
			$password = $this->_raw['password'];			           
			
			// Input validations
			if(!empty($email) and !empty($password)){
				if(filter_var($email, FILTER_VALIDATE_EMAIL)){					
     
                    $res = $this->db->query("SELECT * FROM users where email='".$email."' and deletedAccount = 0");
                    if($res->rowCount()>0)
                    {   
						$result = $res->fetch(PDO::FETCH_ASSOC);		

						$stored_hash = $result["password"];
						unset($result["password"]);                            
                        if (crypt($password, $stored_hash) == $stored_hash){							
							$this->response($this->json($result), 200);
						}                           
                    }			
				}
			}			
			
			// If invalid inputs "Bad Request" status message and reason
			$error = array('status' => "Failed", "msg" => "Invalid Email address or Password");
			$this->response($this->json($error), 400);
		}	
		
		/* ______________________________ Register Users GCM for notifications ______________________________*/        
        
		private function usersgcm(){
			
			if($this->get_request_method() != "POST" || !isset($this->_raw['regID']) || !isset($this->_raw['idDevice']) || empty($this->args[1])){
				$this->response('',400);
			}			
			
			$regID = $this->_raw['regID'];
			
			$idUser = $this->args[1];			           
			$idDevice = $this->_raw['idDevice'];			
		
			$res = $this->db->query("UPDATE devices SET gcm_regID='".$regID."' WHERE idDevice='".$idDevice."' and idUser=".$idUser);
			if($res->rowCount()>0){
				$this->response('', 200);
			}else			
				$this->response('', 400);
		}

	/* ______________________________ Get Regid  ______________________________*/
        
        private function usersRegid(){
            if($this->get_request_method() == "GET" && !empty($this->args[1])){  
                
                
                $res = $this->db->query("SELECT gcm_regID FROM devices WHERE idUser = ".$this->args[1]."");
                if($res->rowCount()>0)
                {                    
                    $result = $res->fetchAll(PDO::FETCH_OBJ);   
					$this->response($this->json($result), 200);
                }else
                    $result = null;
					
                
            }else
                $this->response('',400);
        }


	/*_______________________________Messages______________________________*/
		

	private function usersmessages(){
		
		
		if($this->get_request_method() != "POST" || !isset($this->_raw['regid']) || !isset($this->_raw['message']) || empty($this->args[1])){
				$this->response('',400);
			}

			$idUser = $this->args[1];
			$names = $this->db->query("SELECT name, nickname FROM users WHERE idUser= ".$idUser);
			$names = $names->fetch(PDO::FETCH_ASSOC);  
			$name = $names['name'];
			$nickname = $names['nickname'];
			

			$message = $this->_raw['message'];		
			$regid = $this->_raw['regid'];
			date_default_timezone_set('Europe/Lisbon');
			$dataHora=date('d-m-Y H:i:s');
 
	

			$this->send_push_notification(array($regid),  array("name" => $name , "message" => $message, "idUser" => $idUser, "date" => $dataHora, "nickname" => $nickname));
			
			

	}



        /* ______________________________ Users ______________________________*/        
        
		private function users(){
			
                // User registration
			if($this->get_request_method() == "POST"){                                   
			
                $query = $this->db->prepare("INSERT INTO users (name,email,password,nickname,locationSharing,deletedAccount) 
                VALUES (:name, :email, :password, :nickname, :locationSharing, :deletedAccount)"); 
                
                
                if (empty($this->_raw['name']) || empty($this->_raw['email']) || empty($this->_raw['password']) || empty($this->_raw['idDevice']))
                {
                    $this->response('',400);
                } 
                   
				//check if user exists
				$res = $this->db->query("SELECT * FROM users where email='".$this->_raw['email']."'");
				if($res->rowCount()>0)
				{
					$this->response('',400);
				}
				
                $query->bindValue(":name",$this->_raw['name'], PDO::PARAM_STR);
                $query->bindValue(":email",$this->_raw['email'], PDO::PARAM_STR);   
                $query->bindValue(":password",crypt($this->_raw['password']), PDO::PARAM_STR); // encrypt Password with Bcrypt
                $query->bindValue(":nickname",(empty($this->_raw['nickname'])) ? null:$this->_raw['nickname'], PDO::PARAM_STR);                
                $query->bindValue(":locationSharing","1", PDO::PARAM_STR);
                $query->bindValue(":deletedAccount","0", PDO::PARAM_STR);   
                $result = $query->execute();
                $userID = $this->db->lastInsertId();
                
                //add device
                $res = $this->db->query("INSERT INTO devices (idDevice, idUser) VALUES ('".$this->_raw['idDevice']."', ".$userID.")");
                
                
                $res = $this->db->query("SELECT * FROM users where idUser='".$userID."'");                                        
                if($res->rowCount()>0)
                {                    
                    $result = $res->fetch(PDO::FETCH_ASSOC);                     
                    $this->response($this->json($result), 200);                       
                }else{
                    $this->response('',400);
                }                                                                             
                
			}else 
            //  GET an User
            if ($this->get_request_method() == "GET" && !empty($this->args[1])){
                
                $res = $this->db->query("SELECT * FROM users WHERE idUser = ".$this->args[1]);
                if($res->rowCount()>0)
                {                    
                    $result = $res->fetchAll(PDO::FETCH_OBJ);                    
                    $this->response(substr($this->json($result), 1, -1), 200);                       
                }
			}else // GET all users
            if ($this->get_request_method() == "GET"){
                
                $res = $this->db->query("SELECT * FROM users");
                if($res->rowCount()>0)
                {                    
                    $result = $res->fetchAll(PDO::FETCH_OBJ);                    
                    $this->response($this->json($result), 200);                       
                }
			}else
            if($this->get_request_method() == "DELETE" && !empty($this->args[1])){ // DELETE user
                $res = $this->db->query("SELECT * FROM users WHERE idUSer = ".$this->args[1]);
                if($res->rowCount()>0)
                {                    
                    $res = $this->db->query("UPDATE users SET deletedAccount = 1 WHERE idUser = ".$this->args[1]);       
                    $res = $this->db->query("UPDATE friendships SET status = 2, date ='".$this->datetime()."'  WHERE inviter = ".$this->args[1]." or invited = ".$this->args[1]);                    
                    $this->response('', 200);                       
                }
                
            }else            
                $this->response('',400);	
        }			
        /* ______________________________ Update Nickname ______________________________*/
        
        private function usersnickname(){
            if($this->get_request_method() == "PUT" && !empty($this->args[1]) && !empty($this->_raw['nickname'])){
                $res = $this->db->query("UPDATE users SET nickname = \"".$this->_raw['nickname']."\" WHERE idUser='".$this->args[1]."'");
                if($res->rowCount()>0){
                    $this->response('',200);
                }else
                    $this->response('',400);       
            }else
                $this->response('',400);
        }
		
		/* ______________________________ Recovery Password Request ______________________________*/
        
        private function userspassword(){
            if($this->get_request_method() == "POST" && !empty($this->args[1]) && !empty($this->_raw['email'])){
                $res = $this->db->query("SELECT * FROM users WHERE email='".$this->_raw['email']."'");
                if($res->rowCount()>0){
					$user = $res->fetch(PDO::FETCH_ASSOC);
						// generate random password
					$password = $this->generateRandomString();
						// send email with new password
					$this->sendRecoveryPasswordByEmail($user['name'],$user['email'], $password);
						//update Password in Database						
					$res = $this->db->query("UPDATE users SET password = \"".crypt($password)."\" WHERE idUser=".$user['idUser']);
					
                    $this->response('',200);
                }else
                    $this->response('',400);       
            }else 
					// Update password
			if($this->get_request_method() == "PUT" && !empty($this->args[1]) && !empty($this->_raw['currentPassword']) && !empty($this->_raw['newPassword'])){
			
				$idUser = $this->args[1];
				$currentPassword = $this->_raw['currentPassword'];
				$newPassword = $this->_raw['newPassword'];
				$res = $this->db->query("SELECT * FROM users WHERE idUser=".$idUser);
				if($res->rowCount()>0)
				{   
					$user = $res->fetch(PDO::FETCH_ASSOC);		

					$stored_hash = $user["password"];					
					if (crypt($currentPassword, $stored_hash) == $stored_hash){							
							//update Password in Database						
						$res = $this->db->query("UPDATE users SET password = \"".crypt($newPassword)."\" WHERE idUser=".$user['idUser']);
						$this->response('',200);
					}else
						$this->response('',203);						
				}else
					$this->response('',400);	
			}else
                $this->response('',400);
        }		
		function generateRandomString() {
			$length = 5;
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$randomString = '';
			for ($i = 0; $i < $length; $i++) {
				$randomString .= $characters[rand(0, strlen($characters) - 1)];
			}
			return $randomString;
		}
		
		/* ______________________________ Update Location Sharing Permission ______________________________*/
        
        private function userspermission(){		
            if($this->get_request_method() == "PUT" && !empty($this->args[1]) && isset($this->_raw['permission'])){				                
                if($res = $this->db->query("UPDATE users SET locationSharing = ".$this->_raw['permission']." WHERE idUser='".$this->args[1]."'"))
                    $this->response('',200);
                else
                    $this->response('',400);       
            }else
                $this->response('',400);
        }

        /* ______________________________ Friendship Request ______________________________*/
        
        private function usersrequest(){            
            if($this->get_request_method() == "POST" && !empty($this->args[1]) && !empty($this->_raw['email']))
            {

                $query = $this->db->prepare("INSERT INTO friendships (inviter,invited,date,status) 
                    VALUES (:inviter, :invited, :date, :status)");
                
                $idUser = $this->args[1];
                
                $invitedEmail = $this->_raw['email'];
                
					// Get invited user ID
                $res = $this->db->query("SELECT idUser FROM users WHERE email='".$invitedEmail."'");                                    
                            
                if($res->rowCount() > 0) // email exists, Get user ID
                {                    
                    $result = $res->fetch();
                    $invitedID = $result['idUser'];   					  
                    
					
					// Get friendship status
					
					$res = $this->db->query("SELECT * FROM friendships WHERE (inviter = ".$idUser." and invited = ".$invitedID.") or (inviter = ".$invitedID." and invited = ".$idUser.")");
					if($res->rowCount() > 0) // request already made
					{
						$res = $res->fetch(PDO::FETCH_ASSOC);
						$status = $res['status'];
						
						if($status == 2){ // friendship previously removed , update status
							$res = $this->db->query("UPDATE friendships SET status = 1, inviter = ".$idUser.", invited = ".$invitedID.", date ='".$this->datetime()."' WHERE (inviter = ".$idUser." and invited = ".$invitedID.") or (inviter = ".$invitedID." and invited = ".$idUser.")");
							
							$inviterName = $this->db->query("SELECT name FROM users WHERE idUser = ".$idUser);
							$inviterName = $inviterName->fetch(PDO::FETCH_ASSOC);
							$inviterName = $inviterName['name'];
							
							//send notifications
							// get invited devices - Reg ID
							$res = $this->db->query("SELECT * FROM devices WHERE idUser = ".$invitedID);
							if($res->rowCount()>0){
								$res = $res->fetchAll(PDO::FETCH_ASSOC);								
								foreach($res as $r){
									if($r['gcm_regID'] != null)									
										$this->send_push_notification(array($r['gcm_regID']), array("user" => $inviterName));																	
								}															
							}							
							$this->response('',200);
						}else
							$this->response($this->json(array('msg'=>'Request already made')),400);
					}else{
						//add friendship
						$query->bindValue(":inviter",$idUser, PDO::PARAM_STR);
						$query->bindValue(":invited",$invitedID, PDO::PARAM_STR);   
						$query->bindValue(":date",$this->datetime(), PDO::PARAM_STR);
						$query->bindValue(":status","1", PDO::PARAM_STR); // 1 - Pending request                                      
						$result = $query->execute();
						
						$inviterName = $this->db->query("SELECT name FROM users WHERE idUser = ".$idUser);
						$inviterName = $inviterName->fetch(PDO::FETCH_ASSOC);
						$inviterName = $inviterName['name'];
						
						//send notifications
							// get invited devices - Reg ID
						$res = $this->db->query("SELECT * FROM devices WHERE idUser = ".$invitedID);
						if($res->rowCount()>0){
							$res = $res->fetchAll(PDO::FETCH_ASSOC);								
							foreach($res as $r){
								if($r['gcm_regID'] != null)									
									$this->send_push_notification(array($r['gcm_regID']), array("user" => $inviterName));																	
							}															
						}							
						$this->response('',200);
					}															                                                                          
                }else{
                    // send email to invited user
					$res = $this->db->query("SELECT name, email FROM users WHERE idUser = ".$idUser);
					if($res->rowCount()>0){
						$res = $res->fetch(PDO::FETCH_ASSOC);
						$this->sendInvitationEmail($res['name'],$invitedEmail, $res['email']);
						$this->response($this->json(array('msg'=>'Email sent')),200);
					}else					
						$this->response($this->json(array('msg'=>'User is not registed')),400);
                }
            }else
            if($this->get_request_method() == "GET" && !empty($this->args[1])){  //Get pending requests of an user
                
                $res = $this->db->query("SELECT idUser,date,name,email,nickname FROM friendships,users WHERE idUser = inviter and invited = ".$this->args[1]." and status = 1 ORDER BY date DESC");
                if($res->rowCount()>0)
                {                    
                    $result = $res->fetchAll(PDO::FETCH_OBJ);
                    //print_r($result);
                    $this->response($this->json($result), 200);
                }else
                    $this->response('',400);
                     
            }else
            if($this->get_request_method() == "DELETE" && !empty($this->args[1])){  //DELETE - cancel pending request
                
                $res = $this->db->query("SELECT * FROM friendships WHERE inviter = ".$this->_raw['idUser']." and invited = ".$this->args[1]." and status = 1");
                if($res->rowCount()>0)
                {                
                    $res = $this->db->query("DELETE FROM friendships WHERE inviter = ".$this->_raw['idUser']." and invited = ".$this->args[1]." and status = 1");
                    $this->response('', 200);
                }else
                    $this->response('',400);
                     
            }else
                $this->response('',400);
            
                                    
        }
        
        private function userscancel(){
            if($this->get_request_method() == "POST" && !empty($this->args[1])){  //DELETE - cancel pending request
                
                $res = $this->db->query("SELECT * FROM friendships WHERE inviter = ".$this->_raw['idUser']." and invited = ".$this->args[1]." and status = 1");
                if($res->rowCount()>0)
                {                
                    $res = $this->db->query("DELETE FROM friendships WHERE inviter = ".$this->_raw['idUser']." and invited = ".$this->args[1]." and status = 1");
                    $this->response('', 200);
                }else
                    $this->response('',400);
                     
            }else
                $this->response('',400);
        }
        
        /* ______________________________ Friendship Reply Request ______________________________*/
        
        private function usersapprove(){
                        
                        
            if ($this->get_request_method() != "POST" || empty($this->args[1])){
                $this->response('',400);
            }
            
            $res = $this->db->query("SELECT * FROM friendships WHERE inviter =".$this->args[1]." and invited = ".$this->_raw['idUser']);
            
            if($res->rowCount() > 0){
                $res = $this->db->query("UPDATE friendships SET date ='".$this->datetime()."', status = 0 WHERE inviter =".$this->args[1]." and`invited`= ".$this->_raw['idUser']);
                $this->response('',200);
            }else
            {
                $this->response('',400);
            }                        
        }
        
        private function usersdeny(){                                                
            if ($this->get_request_method() != "POST" || empty($this->args[1])){
                $this->response('',400);
            }
            
            $res = $this->db->query("SELECT * FROM friendships WHERE inviter =".$this->args[1]." and invited = ".$this->_raw['idUser']);
            
            if($res->rowCount() > 0){
                $res = $this->db->query("DELETE FROM friendships WHERE inviter =".$this->args[1]." and`invited`= ".$this->_raw['idUser']);
                $this->response('',200);
            }else
            {
                $this->response('',400);
            }                        
        }
		
        /* ______________________________ Get Friends  ______________________________*/
        
        private function usersfriends(){
            if($this->get_request_method() == "GET" && !empty($this->args[1])){  //Get all friends

                $res = $this->db->query("SELECT idUser,date,name,email,nickname FROM friendships,users WHERE idUser = inviter and invited = ".$this->args[1]." and status = 1 ORDER BY date DESC");
                if($res->rowCount()>0)
                {                    
                    $pending = $res->fetchAll(PDO::FETCH_OBJ);                    
                }else
                    $pending = null;
                                                            
                $res = $this->db->query("SELECT idUser,date,name,email,nickname FROM friendships,users WHERE status = 0 and ( (idUser = inviter and invited = ".$this->args[1].") or (idUser = invited and inviter = ".$this->args[1].")) ORDER BY date DESC");
                if($res->rowCount()>0)
                {                    
                    $friends = $res->fetchAll(PDO::FETCH_OBJ);                                        
                }else
                    $friends = null;
                                        
                $res = $this->db->query("SELECT idUser,date,name,email,nickname FROM friendships,users WHERE idUser = invited and inviter = ".$this->args[1]." and status = 1 ORDER BY date DESC");
                if($res->rowCount()>0)
                {                    
                    $requests = $res->fetchAll(PDO::FETCH_OBJ);                                        
                }else
                    $requests = null;
                    
                     
                $result = array("pending"=>$pending, "friends" =>$friends, "requests" => $requests);     
                $this->response($this->json($result), 200);
            }else
                $this->response('',400);

        }
		
        /* ______________________________ Friendship - Unfriend ______________________________*/
        
        private function usersunfriend(){
                        
              //TEMPORARIO          
            //if ($this->get_request_method() != "DELETE" || empty($this->args[1])){
            if ($this->get_request_method() != "POST" || empty($this->args[1])){
                $this->response('',400);
            }
            
            $res = $this->db->query("SELECT * FROM friendships WHERE (inviter = ".$this->args[1]." and invited = ".$this->_raw['idUser'].") or (inviter = ".$this->_raw['idUser']." and invited = ".$this->args[1].")");
            
            if($res->rowCount() > 0){
                $res = $this->db->query("UPDATE friendships SET date ='".$this->datetime()."', status = 2 WHERE (inviter = ".$this->args[1]." and invited = ".$this->_raw['idUser'].") or (inviter = ".$this->_raw['idUser']." and invited = ".$this->args[1].")");
                $this->response('',200);
            }else
            {
                $this->response('',400);
            }                        
        }
		
		
        
        
        /* ______________________________ Get user and user's friends location ______________________________*/
       	
	   private function userscheckins(){           

			//send http Request to MP web service
			//URL: http://193.137.8.29:8080/RTLSQ/q1
			//PARAMS: add deviceID (MAC) and f=1                                     
			$url = 'http://rtls.dsi.uminho.pt:8080/RTLSQ/q1';
				
            if($this->get_request_method() == "GET" && !empty($this->args[1]))
            {
							              						                
                //get idDevice
                $res = $this->db->query("SELECT idDevice FROM devices WHERE idUser = ".$this->args[1]);
                if($res->rowCount() > 0){
                    $userDevice = $res->fetch();
                    $userDevice = $userDevice['idDevice'];                    
                }else
                    $this->response('',204);
					
				 // get friends
                $res = $this->db->query("SELECT users.idUser, name, nickname, idDevice FROM users,friendships,devices WHERE users.idUser = devices.idUser and (inviter = ".$this->args[1]." and invited = users.idUser or invited = ".$this->args[1]." and inviter = users.idUser) and status = 0 and locationSharing = 1");
                $friends = $res->fetchAll(PDO::FETCH_ASSOC);  
                                          
				//___________________________________________________________ get self location						  
				
                    //get self location    
                $res = $this->db->query("SELECT * FROM lastKnownLocation WHERE idUser = ".$this->args[1]." and isCurrent = 1");
				if($res->rowCount()>0){
					$lastKnownLocation = $res->fetch(PDO::FETCH_ASSOC);
					
					// get location object                    
					$selfLocation = $this->getLocationObject($lastKnownLocation['idPlaces']);	 
					$date = new DateTime($lastKnownLocation['timestamp'], new DateTimeZone('Europe/London'));                                  					
					//$selfLocation['timeStamp'] = $date->format('M j, Y g:i:s A');
					$selfLocation['timeStamp'] = $date->format('M j, Y G:i:s');
					
				}else{ // last known location isn't updated
					$data = array('f' => '1','deviceID' =>$userDevice);
					$result = $this->httpPOST($url,$data);     
					$selfLocation = json_decode($result, true);
										
						// get timestamp of the latest fingerprint saved
					$fingerDB = $this->getLastFingerprintFromDB($this->args[1]);				
					$date = new DateTime($fingerDB['timestamp'], new DateTimeZone('Europe/London'));
					//$selfLocation['timeStamp'] = $date->format('M j, Y g:i:s A');
					$selfLocation['timeStamp'] = $date->format('M j, Y G:i:s');									
				}
				
				//___________________________________________________________ get friends location
				
                //get friends location
                $friendsInfo = null;                                                                                               
                
				foreach($friends as $i){                        
					//get friend location    
					$res = $this->db->query("SELECT * FROM lastKnownLocation WHERE idUser = ".$i['idUser']);
					if($res->rowCount()>0){
					
						$lastKnownLocation = $res->fetch(PDO::FETCH_ASSOC);		
						
						if($lastKnownLocation['isCurrent'] ==1){ // LastKnownLocation is updated
							// get location object                    
							$location = $this->getLocationObject($lastKnownLocation['idPlaces']);	 
							$date = new DateTime($lastKnownLocation['timestamp'], new DateTimeZone('Europe/London'));                                  					
							//$location['timeStamp'] = $date->format('M j, Y g:i:s A');
							$location['timeStamp'] = $date->format('M j, Y G:i:s');
							
						}else{	// LastKnownLocation isn't updated
							$data = array('f' => '1','deviceID' =>$i['idDevice']);
							$result = $this->httpPOST($url,$data);                
							$location = json_decode($result, true);
							
							if($location['room'] == "null" || empty($result)){ // if PE doesn't recognize the location
									// get location object from LKL                   
								$location = $this->getLocationObject($lastKnownLocation['idPlaces']);	 
								$date = new DateTime($lastKnownLocation['timestamp'], new DateTimeZone('Europe/London'));                                  					
								//$location['timeStamp'] = $date->format('M j, Y g:i:s A');
								$location['timeStamp'] = $date->format('M j, Y G:i:s');
							}else{
									// get timestamp of the latest fingerprint saved
								$fingerDB = $this->getLastFingerprintFromDB($i['idUser']);				
								$date = new DateTime($fingerDB['timestamp'], new DateTimeZone('Europe/London'));
								//$location['timeStamp'] = $date->format('M j, Y g:i:s A');
								$location['timeStamp'] = $date->format('M j, Y G:i:s');
							}
						}						
					}else{
						$data = array('f' => '1','deviceID' =>$i['idDevice']);
						$result = $this->httpPOST($url,$data);                
						$location = json_decode($result, true);
						
						// get timestamp of the latest fingerprint saved						
						$date = new DateTime(null, new DateTimeZone('Europe/London'));
						//$location['timeStamp'] = $date->format('M j, Y g:i:s A');
						$location['timeStamp'] = $date->format('M j, Y G:i:s');						
					}				               
                    $i["location"] = $location;                        
                    $friendsInfo[] = $i;  

//$this->sendquestion1($this->args[1]); 
//$this->sendquestion2($this->args[1]); 
                                                                                        
                }                                                                             
                usort($friendsInfo, array($this,"cmp"));                                          
                                                                                                                            
                $array = array("self"=>$selfLocation, "friends" =>$friendsInfo);
                $this->response($this->json($array),200);
                                                                                                                        
            }else
                $this->response('',400); 

                          
        }		
		
		
			// get location Object from Database giving a specific idPlaces		
		private function getLocationObject($idPlace){
			$res = $this->db->query("SELECT * FROM places WHERE idPlaces = ".$idPlace);
			$res = $res->fetch(PDO::FETCH_ASSOC);                                                            
			$location = null;
			
			if($res['idType'] == "7")
			{
				$result = $this->db->query("
				SELECT 
					r.idPlaces AS roomID, r.name AS room,
					f.idPlaces AS floorID, f.name AS floor, 
					b.idPlaces AS buildingID, b.name AS building,
					a.idPlaces AS areaID, a.name AS area
				FROM 
					places AS r, places AS f, places AS b, places AS a
				WHERE 
					r.idPlaces = ".$res['idPlaces']." AND
					r.idParent = f.idPlaces AND  
					f.idParent = b.idPlaces AND
					b.idParent = a.idPlaces");                     
				$location = $result->fetch(PDO::FETCH_ASSOC);                                                                  
			}else if($res['idType'] == "8"){
				$result = $this->db->query("
				SELECT 
					coun.name AS country,SUBSTRING_INDEX(coun.moreDetails,'code:', -1) AS cc,
					ci.name AS city,
					p.name AS place, SUBSTRING_INDEX(p.moreDetails,'address:', -1) AS address,
					p.lat, p.lng, p.idPlaces
				FROM 
					places AS coun, places AS ci, places AS p 
				WHERE 
					p.idPlaces = ".$res['idPlaces']." AND
					p.idParent = ci.idPlaces AND
					ci.idParent = coun.idPlaces");     
				$location = $result->fetch(PDO::FETCH_ASSOC);                   
				if($location["address"] == null){
					unset($location["address"]);
				}
			}
			return $location;		
		}
		
		/*
		 *	Encode array into JSON
		*/
		private function json($data){
			if(is_array($data)){
				return json_encode($data);
			}
		}
        
        private function httpPOST($url, $data){
            // use key 'http' even if you send the request to https://...
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                    'ignore_errors' => true,
					'timeout' => 30,
                ),
            );
            
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);            
                                    
            //return preg_replace('/\s+/', '', $result);
            return $result;                 
        } 
        
        private function datetime(){
            $date = new DateTime(null, new DateTimeZone('Europe/London'));                                  
            return $date->format('Y-m-d H:i:s');
        }
        
        private function cmp($a, $b){
                $aTime = strtotime($a['location']['timeStamp']);
                $bTime = strtotime($b['location']['timeStamp']);
            
                if($aTime == $bTime) return 0;
                return $aTime < $bTime ? 1 : -1;
        }
		
		
		// get latest fingerprint of a specific device from database
		private function getLastFingerprintFromDB($idUser){
			$res = $this->db->query("SELECT * FROM fingerprintsHistory WHERE idUser = '".$idUser."' ORDER BY deviceTimestamp DESC LIMIT 1");
			if($res->rowCount()>0){ // get last fingerprint info			
				$fingerprintDB = $res->fetch(PDO::FETCH_ASSOC);
				
				$data = array();
				$data["timestamp"] = $fingerprintDB["deviceTimestamp"];
				
				$res = $this->db->query("SELECT mac,rssi FROM apsFingerprintsHistory WHERE idFingerprint = ".$fingerprintDB['idFingerprint']);
				if($res->rowCount()>0){ // get last APs' fingerprint info
					$apsDB = $res->fetchAll(PDO::FETCH_ASSOC);
					$fingerprint = array();
					foreach($apsDB as $ap){
						$fingerprint[$ap["mac"]] = $ap["rssi"];
					}
				}else return null;
				$data["fingerprint"] = $fingerprint;				
				return $data;
			}else return null;			
		}
				
		
		//________________________________________________________________________________________
		
		
			// compare two fingerprint
		private function fingerprintsComparison($f1, $f2){
								
			$missingRSSIvalue = -109; // RSSI value used when a particular AP isn't in a fingerprint and it's the other one
			$threshold = 17.5; // threshold value
			
			$result = array();
			foreach($f1 as $k=>$v){  
				if(array_key_exists($k,$f2)){ // compare APs of Fingerprint 1 with Fingerprint 2				
					$rssi = abs($v) - abs($f2[$k]); // calculate the difference between both RSSI values
					$result[$k] = abs($rssi);  // save result in a new array
					$result_just_with_similiares[$k] = abs($rssi);
				}else				
					$result[$k] = abs(abs($v) - abs($missingRSSIvalue)); // if AP of first fingerprint isn't in the second, the difference is calculated with defined value
			}			
			
			$remaining = array_diff_key($f2,$result); // Get APs that isn't in Fingerprint 1			
			foreach($remaining as $k=>$v){
				$result[$k] = abs(abs($v) - abs($missingRSSIvalue)); // calculate the difference with defined value		
			}

			// calculations
			$distance = array_sum($result)/count($result); // RSSI value / number of APs								
			if($distance < $threshold){
				return true;
			}else return false;
		}
		
		private function calculateFingerprintsDistance($f1, $f2){
								
			$missingRSSIvalue = -109; // RSSI value used when a particular AP isn't in a fingerprint and it's the other one
			//$threshold = 17.5; // threshold value - > Not used!
			$hasSimilarAPs = false; // if there is no similar AP both fingerprints we assume that they aren't in the same place
			
			$result = array();
			foreach($f1 as $k=>$v){  
				if(array_key_exists($k,$f2)){ // compare APs of Fingerprint 1 with Fingerprint 2				
					$rssi = abs($v) - abs($f2[$k]); // calculate the difference between both RSSI values
					$result[$k] = abs($rssi);  // save result in a new array
					$result_just_with_similiares[$k] = abs($rssi);
					$hasSimilarAPs = true;
				}else				
					$result[$k] = abs(abs($v) - abs($missingRSSIvalue)); // if AP of first fingerprint isn't in the second, the difference is calculated with defined value
			}			
			
			if(!$hasSimilarAPs)
				return false;
			
			$remaining = array_diff_key($f2,$result); // Get APs that isn't in Fingerprint 1			
			foreach($remaining as $k=>$v){
				$result[$k] = abs(abs($v) - abs($missingRSSIvalue)); // calculate the difference with defined value		
			}
			
			// calculations
			$distance = array_sum($result)/count($result); // RSSI value / number of APs								
			return $distance;
		}
		
		//________________________________________________________________________________________
		
			// ranking process - trying to match fingerprint with database fingerprints
		private function fingerprintsRanking($fingerprint){
		
			//PARAMS
			$threshold = 25;
		
				// get all annotated fingerprints from database (One fingerprint by place - Descending order)
			$fingerprints = $this->db->query("SELECT idFingerprints, idPlaces FROM 
				(SELECT * FROM fingerprints WHERE idPlaces IS NOT NULL ORDER BY idFingerprints DESC) a
				GROUP BY idPlaces ORDER BY idFingerprints DESC ");
									
			if($fingerprints->rowCount()>0){
				$fingerprints = $fingerprints->fetchAll(PDO::FETCH_ASSOC);				
				$ranking = array();
				$places = array();
				
				foreach($fingerprints as $row){				
					// Save association between fingerprint and place
					$places[$row["idFingerprints"]] = $row["idPlaces"];
					$res = $this->db->query("SELECT * FROM aps WHERE
										idFingerprints = (SELECT idFingerprints 
										FROM fingerprints 									
										WHERE idFingerprints = ".$row["idFingerprints"].")");
					$res = $res->fetchAll(PDO::FETCH_ASSOC);					
					$fingerprintDB = array();
					foreach($res as $f){
						$fingerprintDB[$f['mac']]= $f['rssi'];					
					}
					
						// compare fingerprint with database fingerprint and save distance result					
					$distance = $this->calculateFingerprintsDistance($fingerprint, $fingerprintDB);					
					if($distance != false){
						$ranking[$row["idFingerprints"]]= $distance;
					}
				}
				
				// sort results				
				asort($ranking, SORT_NUMERIC);
				reset($ranking);
				$firstKey = key($ranking);				
				
				// check if lowest value is under threshold value					
				if ($ranking[$firstKey] < $threshold){				
					//get first fingerprint and return place ID				
					return $places[$firstKey]; 
				}else return null;												
			}else return null;						
		}	

		private function fingerprintsRankingteste($fingerprint){
		
			//PARAMS
			$threshold = 25;
		
			$start_time = microtime(true);
			
				// get all annotated fingerprints from database (One fingerprint by place - Descending order)
			$fingerprints = $this->db->query("SELECT idFingerprints, idPlaces FROM 
				(SELECT * FROM fingerprints WHERE idPlaces IS NOT NULL ORDER BY idFingerprints DESC) a
				GROUP BY idPlaces ORDER BY idFingerprints DESC ");
									
			if($fingerprints->rowCount()>0){
				$fingerprints = $fingerprints->fetchAll(PDO::FETCH_ASSOC);				
				$ranking = array();
				$places = array();
				
				foreach($fingerprints as $row){									
					$res = $this->db->query("SELECT * FROM aps WHERE
										idFingerprints = (SELECT idFingerprints 
										FROM fingerprints 									
										WHERE idFingerprints = ".$row["idFingerprints"].")");
					$res = $res->fetchAll(PDO::FETCH_ASSOC);					
					$fingerprintDB = array();
					foreach($res as $f){
						$fingerprintDB[$f['mac']]= $f['rssi'];					
					}
					
						// compare fingerprint with database fingerprint and save distance result	
					$distance = $this->calculateFingerprintsDistance($fingerprint, $fingerprintDB);					
					if($distance != false){
						// Save association between fingerprint and place
						$places[$row["idFingerprints"]] = $row["idPlaces"];
						
						$ranking[$row["idFingerprints"]]= $distance;
					}					
				}
				
				
				// sort results				
				asort($ranking, SORT_NUMERIC);
				reset($ranking);
				$firstKey = key($ranking);				
				print_r($ranking);
				echo "Execution Time:".(microtime(true)-$start_time)."\n";				
				// check if lowest value is under threshold value					
				if ($ranking[$firstKey] < $threshold){				
					//get first fingerprint and return place ID				
					return $places[$firstKey]; 
				}else return null;												
			}else return null;						
		}		
		
		private function fingerprintsRankingV2teste($fingerprint){
		
			//PARAMS
			$threshold = 17.5;
			$limitPerPlace = 5;
		
			$start_time = microtime(true);
			
				// get all annotated fingerprints from database (One fingerprint by place - Descending order)
			$fingerprints = $this->db->query("SELECT idFingerprints, idPlaces FROM 
				(SELECT * FROM fingerprints ORDER BY idFingerprints DESC) a
				WHERE (
					select count(*) from fingerprints as f
					where f.idPlaces = a.idPlaces and f.idFingerprints >= a.idFingerprints               
					)<=".$limitPerPlace);
									
			if($fingerprints->rowCount()>0){
				$fingerprints = $fingerprints->fetchAll(PDO::FETCH_ASSOC);				
				$ranking = array();
				$places = array();
				
				foreach($fingerprints as $row){																		
					$res = $this->db->query("SELECT * FROM aps WHERE
										idFingerprints = (SELECT idFingerprints 
										FROM fingerprints 									
										WHERE idFingerprints = ".$row["idFingerprints"].")");
					$res = $res->fetchAll(PDO::FETCH_ASSOC);					
					$fingerprintDB = array();
					foreach($res as $f){
						$fingerprintDB[$f['mac']]= $f['rssi'];					
					}
					
						// compare fingerprint with database fingerprint and save distance result	
					$distance = $this->calculateFingerprintsDistance($fingerprint, $fingerprintDB);					
					if($distance != false){
						$ranking[$row["idFingerprints"]]= $distance;
						// Save association between fingerprint and place
						$places[$row["idFingerprints"]] = $row["idPlaces"];
					}					
				}
				
				
				// sort results				
				asort($ranking, SORT_NUMERIC);
				reset($ranking);
				$firstKey = key($ranking);				
				print_r($ranking);
				echo "Execution Time:".(microtime(true)-$start_time)."\n";				
				// check if lowest value is under threshold value					
				if ($ranking[$firstKey] < $threshold){				
					//get first fingerprint and return place ID				
					return $places[$firstKey]; 
				}else return null;												
			}else return null;						
		}		
		
			
		//send recovery password Email		
		private function sendRecoveryPasswordByEmail($user,$to, $password){			
			$msg = "Hi ".$user.",\n\n"
			."Your temporary password is ".$password."\n"
			."For security, log in with the temporary password, go to Settings -> Change Password and select a new password.\n\n\n"
			."The where@UM app has been developed at the University of Minho.\n"
			."It can be used to share your current location with your friends, and to contribute for the development of a large scale positioning system.\n\n"
			."Join us, invite more friends to our network and have fun!!\n\n"
			."Best Regards from the\nwhere@UM team\n\n"			
			."________________________________________________________________\n"
			."For further information, please contact where@mail.dsi.uminho.pt";					
			
			$msg = wordwrap($msg,70);

			$email = new PHPMailer();
			$email->IsSMTP();
//			$email->SMTPDebug = 1;
			$email->SMTPAuth = true;
			$email->Host = "smtp.gmail.com";
			$email->Port = 465;
			$email->SMTPSecure = "ssl";
			$email->Username = "where@dsi.uminho.pt";
			$email->Password = "l952peapi.";

			$email->SetFrom("where@mail.dsi.uminho.pt", "where@UM team");

//			$email->IsHTML(true);
			$email->Subject = "New Password";
			$email->Body = $msg;

			$email->AddAddress ($to);			
							
			return $email->Send();
		}	
			
		
		//send Invitation Email		
		private function sendInvitationEmail($user,$to, $from){
			$msg = "Hi,\n\n"
			.$user." (".$from.") wants to be your friend in where@UM.\n".
			"If you still haven't registered, you can install the app into your Android device and join the where@UM community!\n\n"
			."Google Play link:\n"
			."https://play.google.com/store/apps/details?id=com.whereum\n\n"
			."Website:\n"
			."http://where.dsi.uminho.pt/\n\n"
			."The where@UM app has been developed at the University of Minho.\n"
			."It can be used to share your current location with your friends, and to contribute for the development of a large scale positioning system.\n\n"
			."Join us, invite more friends to our network and have fun!!\n\n"
			."Best Regards from the\nwhere@UM team\n\n"			
			."________________________________________________________________\n"
			."For further information, please contact where@mail.dsi.uminho.pt";					
			
			$msg = wordwrap($msg,70);

			$email = new PHPMailer();
			$email->IsSMTP();
//			$email->SMTPDebug = 1;
			$email->SMTPAuth = true;
			$email->Host = "smtp.gmail.com";
			$email->Port = 465;
			$email->SMTPSecure = "ssl";
			$email->Username = "where@dsi.uminho.pt";
			$email->Password = "l952peapi.";

			$email->SetFrom("where@mail.dsi.uminho.pt", "where@UM team");
			$email->AddBCC("ajcm2appls@gmail.com", "AJCM2");

//			$email->IsHTML(true);
			$email->Subject = "Friend Invitation";
			$email->Body = $msg;

			$email->AddAddress ($to);
			
			return $email->Send();
		}	


	
	/*	private function survey(){
		
			
			$email = new PHPMailer();
			$email->CharSet = 'UTF-8';
			$email->From = "a58666@alunos.uminho.pt";
			$email->FromName = "where@UM";
			$email->Subject = "where@UM - Experiência do utilizador";
			
			
			$res = $this->db->query("select name,email from users where deletedAccount=0 and idUser >= 124 "); //131
			
			$emails = $res->fetchAll(PDO::FETCH_ASSOC);
			
			foreach($emails as $mail){
				$msg = "Caro(a) ".$mail['name'].",\n\n"
				."Desde já, quero agradecer-lhe por utilizar a aplicação where@UM e com isso contribuir para o desenvolvimento deste projeto.\n\n"
				."Uma vez que o meu trabalho sobre o projeto where@UM está em fase terminal, gostaria de receber o seu feedback relativamente à aplicação assim como conhecer a sua opinião sobre futuros melhoramentos.\n\n"
				."De seguida é apresentado uma lista de possíveis funcionalidades a implementar numa futura versão da aplicação where@UM:\n\n"
				."- Introdução de perfil e histórico de utilizadores\n"
				."- Integração com o Facebook (Login/convidar utilizadores/atualização do estado do Facebook)\n"
				."- Troca de mensagens (individuais) entre utilizadores\n"
				."- Deixar comentário ao efetuar checkin(seria visível pelos amigos)\n"
				."- Criação de grupos de amigos (com partilha da localização configurável para cada grupo)\n"
				."- Troca de mensagens (chat) entre utilizadores e entre grupos\n"
				."- Criação de alertas por áreas (Geofencing)\n"
				."- Mapa com a localização dos utilizadores\n\n"		
				."De forma a contribuir para o meu trabalho, gostaria que respondesse a este email com um feedback sobre a aplicação e escolhesse 3 funcionalidades presentes na lista por ordem de preferência.\n\n"
				."Muito obrigado pela atenção,\nDiogo Matos";				
				$msg = wordwrap($msg,100);
				$email->Body = $msg;
																			
				$email->AddAddress ($mail['email']);
				$email->Send();
				$email->ClearAllRecipients();
			}
		}*/
			
		/*private function emailnewversion(){
			$msg = "Caro utilizador da aplicação,\n\n"
			."Desde já quero agradecer-lhe por utilizar a aplicação Where@UM e, com isso, contribuir para o desenvolvimento deste projeto.\n\n"
			."Foi lançada uma nova versão (1.3) com algumas novidades e alterações:\n"
			."- Correcção de Bugs\n"
			."- Introduzida funcionalidade de recuperação de password\n"
			."- Introduzida funcionalidade de alteração de password\n"
			."- Introduzidas notificações\n"
			."- Alguns melhoramentos menores\n\n"
			."Cumprimentos,\nDiogo Matos";				
			
			$msg = wordwrap($msg,70);
			$email = new PHPMailer();
			$email->CharSet = 'UTF-8';
			$email->From = "diogomatos.gmr@gmail.com";
			$email->FromName = "Where@UM";
			$email->Subject = "Nova atualização - Versão 1.3";
			$email->Body = $msg;
			
						
			
			chdir("../");			
			$email->AddAttachment("Where@UM v1.3.apk", "Where@UM v1.3.apk");
			
			$res = $this->db->query("select email from users where deletedAccount=0");
			$emails = $res->fetchAll(PDO::FETCH_ASSOC);
			
			foreach($emails as $mail){
				$email->AddAddress ($mail['email']);
				$email->Send();
				$email->ClearAllRecipients();
			}
		}*/
		
		function send (){
			$idDevice = "c4:43:8f:cf:5f:8e";
			$idUser = 1;
			$res = $this->db->query("SELECT gcm_regID FROM devices WHERE idDevice='".$idDevice."' and idUser=".$idUser);
			if($res->rowCount()){
				$regID = $res->fetch(PDO::FETCH_ASSOC);
				$regID = $regID['gcm_regID'];
			}					
			$this->send_push_notification(array($regID),  array("user" => "Diogo Matos"));
		}

		//Sending Push Notification
		function send_push_notification($registatoin_ids, $message) {          
			// Set POST variables
			$url = 'https://android.googleapis.com/gcm/send';
	 
			$fields = array(
				'registration_ids' => $registatoin_ids,
				'data' => $message,
				'delay_while_idle'=> false,
			);
	 
			$headers = array(
				'Authorization: key=' . $this->GOOGLE_API_KEY,
				'Content-Type: application/json'
			);
			//print_r($headers);
			// Open connection
			$ch = curl_init();
	 
			// Set the url, number of POST vars, POST data
			curl_setopt($ch, CURLOPT_URL, $url);
	 
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	 
			// Disabling SSL Certificate support temporary
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	 
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
	 
			// Execute post
			$result = curl_exec($ch);
			if ($result === FALSE) {
				die('Curl failed: ' . curl_error($ch));
			}
	 
			// Close connection
			curl_close($ch);
			echo $result;
		}
		

	



//envia notifica��o caso a localiza��o seja conhecida
private function sendquestion1 (){


	if($this->get_request_method() == "GET" && !empty($this->args[1])){  

	$idUser=$this->args[1];


      $regid2=$this->db->query("SELECT gcm_regID FROM devices WHERE idUser=".$idUser);
        if($regid2->rowCount() > 0) // request already made
        {
           $regid2 = $regid2->fetch(PDO::FETCH_ASSOC);  
           $regid2 = $regid2['gcm_regID'];  

        }
        $date = new DateTime(null, new DateTimeZone('Europe/Lisbon'));                                  
        $date= $date->format('Y-m-d H:i:s');


       $questiondat=$this->db->query("SELECT questionDate FROM feedbackUsers WHERE typeQuestion=1 AND idUser=".$idUser." ORDER BY idQuestion DESC LIMIT 1");
       if($questiondat->rowCount() > 0) // request already made///////////////////////////////////////////////
	{

       $questiondat = $questiondat->fetch(PDO::FETCH_ASSOC);  
       $questiondat = $questiondat['questionDate'];

        $dif_horas=$this->contarhoras($questiondat,$date);
	//$rand=rand(120,216);//para vers�o de produ��o
	$rand=rand(3,9);//para vers�o de desenvolvimento
            if($dif_horas >= $rand){
                $dia_semana=$this->diasemana($date);

                if($dia_semana == 'S�bado' || $dia_semana == 'Domingo'){
                    date_default_timezone_set('Europe/Lisbon');
                    $time=date('H');

                    if($time>=11 && $time<=22){
                        $idlocall=$this->db->query("SELECT idPlaces, fingerprintSelected FROM fingerprintsHistory WHERE idUser=".$idUser." ORDER BY deviceTimestamp DESC LIMIT 1");
                        if($idlocall->rowCount() > 0) // request already made
                        {
                           
                                $idlocall = $idlocall->fetch(PDO::FETCH_ASSOC);  
                                $idlocal = $idlocall['idPlaces'];
			      $fingerprintSelected = $idlocall['fingerprintSelected'];

			 
                       // }
				if($idlocal >0 || $idlocal !=null){
                                    $locali=$this->db->query("SELECT name, moreDetails FROM places WHERE idPlaces =".$idlocal);
                                    if($locali->rowCount() > 0) // request already made
                                    {
                                       $locali = $locali->fetch(PDO::FETCH_ASSOC);  
                                       $idlocal1 = $locali['name'];
				     $moreDetails = $locali['moreDetails'];

                                       if($idlocal1 != null){

                                           $query = $this->db->prepare("INSERT INTO feedbackUsers (idUser,typeQuestion,questionDate,local_questioned,fingerprintQuestioned) 
                                VALUES (:idUser, :typeQuestion, :questionDate, :local_questioned, :fingerprintQuestioned)");

                                                $query->bindValue(":idUser",$idUser, PDO::PARAM_STR);   
                                                $query->bindValue(":typeQuestion","1", PDO::PARAM_STR);
                                                $query->bindValue(":questionDate",$this->datetime(), PDO::PARAM_STR);
				              $query->bindValue(":local_questioned",$idlocal, PDO::PARAM_STR); 
					     $query->bindValue(":fingerprintQuestioned",$fingerprintSelected, PDO::PARAM_STR); 

                                                $result = $query->execute();

                                          $this->send_push_notification(array($regid2),  array("idlocal" => $idlocal, "local" => $idlocal1, "date_s" => $date, "idUserQ" => $idUser, "moreDetails" => $moreDetails));
                                       }   
                                    }
                           }
                        }

                    }
                    
                }
                else{ //se for dia da semana
                     date_default_timezone_set('Europe/Lisbon');
                    $time=date('H');
                    if($time>=9 && $time<=21){
                        $idlocall=$this->db->query("SELECT idPlaces, fingerprintSelected FROM fingerprintsHistory WHERE idUser=".$idUser." ORDER BY deviceTimestamp DESC LIMIT 1");
                        if($idlocall->rowCount() > 0) // request already made
                        {
                           
                                $idlocall = $idlocall->fetch(PDO::FETCH_ASSOC);  
                                $idlocal = $idlocall['idPlaces'];
			      $fingerprintSelected = $idlocall['fingerprintSelected'];



                       // }
                           if($idlocal >0 || $idlocal !=null){
                                    $locali=$this->db->query("SELECT name, moreDetails FROM places WHERE idPlaces =".$idlocal);
                                    if($locali->rowCount() > 0) // request already made
                                    {
                                       $locali = $locali->fetch(PDO::FETCH_ASSOC);  
                                       $idlocal1 = $locali['name'];
                                     $moreDetails = $locali['moreDetails'];

                                       if($idlocal1 != null){

                                            $query = $this->db->prepare("INSERT INTO feedbackUsers (idUser,typeQuestion,questionDate,local_questioned,fingerprintQuestioned) 
                                VALUES (:idUser, :typeQuestion, :questionDate, :local_questioned, :fingerprintQuestioned)");

                                                $query->bindValue(":idUser",$idUser, PDO::PARAM_STR);   
                                                $query->bindValue(":typeQuestion","1", PDO::PARAM_STR);
                                                $query->bindValue(":questionDate",$this->datetime(), PDO::PARAM_STR);
				              $query->bindValue(":local_questioned",$idlocal, PDO::PARAM_STR); 
					     $query->bindValue(":fingerprintQuestioned",$fingerprintSelected, PDO::PARAM_STR); 


                                                $result = $query->execute();


                                          $this->send_push_notification(array($regid2),  array("idlocal" => $idlocal, "local" => $idlocal1, "date_s" => $date, "idUserQ" => $idUser, "moreDetails" => $moreDetails));
                                       }   
                                    }
                           }
                        }
                           
                        
                    }
               }
            }
        }
        else {//casoquestiondat for null
	   $dia_semana=$this->diasemana($date);
           if($dia_semana == 'S�bado' || $dia_semana == 'Domingo'){
                    date_default_timezone_set('Europe/Lisbon');
                    $time=date('H');



                    if($time>=11 && $time<=22){
                         $idlocall=$this->db->query("SELECT idPlaces, fingerprintSelected FROM fingerprintsHistory WHERE idUser=".$idUser." ORDER BY deviceTimestamp DESC LIMIT 1");
                        if($idlocall->rowCount() > 0) // request already made
                        {
                           
                                $idlocall = $idlocall->fetch(PDO::FETCH_ASSOC);  
                                $idlocal = $idlocall['idPlaces'];
			      $fingerprintSelected = $idlocall['fingerprintSelected'];


                       // }
                           if($idlocal >0 || $idlocal !=null){
                                    $locali=$this->db->query("SELECT name, moreDetails FROM places WHERE idPlaces =".$idlocal);
                                    if($locali->rowCount() > 0) // request already made
                                    {
                                       $locali = $locali->fetch(PDO::FETCH_ASSOC);  
                                       $idlocal1 = $locali['name'];
                                     $moreDetails = $locali['moreDetails'];

                                       if($idlocal1 != null){

                                           $query = $this->db->prepare("INSERT INTO feedbackUsers (idUser,typeQuestion,questionDate,local_questioned,fingerprintQuestioned) 
                                VALUES (:idUser, :typeQuestion, :questionDate, :local_questioned, :fingerprintQuestioned)");

                                                $query->bindValue(":idUser",$idUser, PDO::PARAM_STR);   
                                                $query->bindValue(":typeQuestion","1", PDO::PARAM_STR);
                                                $query->bindValue(":questionDate",$this->datetime(), PDO::PARAM_STR);
				              $query->bindValue(":local_questioned",$idlocal, PDO::PARAM_STR); 
					     $query->bindValue(":fingerprintQuestioned",$fingerprintSelected, PDO::PARAM_STR); 


                                                $result = $query->execute();


                                          $this->send_push_notification(array($regid2),  array("idlocal" => $idlocal, "local" => $idlocal1, "date_s" => $date, "idUserQ" => $idUser, "moreDetails" => $moreDetails));
                                       }   
                                    }
                           }
                        }

                    }
                    
                }
                else{ //se for dia da semana
                     date_default_timezone_set('Europe/Lisbon');
                    $time=date('H');

                    if($time>=9 && $time<=21){
                        $idlocall=$this->db->query("SELECT idPlaces, fingerprintSelected FROM fingerprintsHistory WHERE idUser=".$idUser." ORDER BY deviceTimestamp DESC LIMIT 1");
                        if($idlocall->rowCount() > 0) // request already made
                        {
                           
                                $idlocall = $idlocall->fetch(PDO::FETCH_ASSOC);  
                                $idlocal = $idlocall['idPlaces'];
			      $fingerprintSelected = $idlocall['fingerprintSelected'];


                       // }
                           if($idlocal >0 || $idlocal !=null){
                                    $locali=$this->db->query("SELECT name, moreDetails FROM places WHERE idPlaces =".$idlocal);
                                    if($locali->rowCount() > 0) // request already made
                                    {
                                       $locali = $locali->fetch(PDO::FETCH_ASSOC);  
                                       $idlocal1 = $locali['name'];
                                     $moreDetails = $locali['moreDetails'];

                                       if($idlocal1 != null){

                                             $query = $this->db->prepare("INSERT INTO feedbackUsers (idUser,typeQuestion,questionDate,local_questioned,fingerprintQuestioned) 
                                VALUES (:idUser, :typeQuestion, :questionDate, :local_questioned, :fingerprintQuestioned)");

                                                $query->bindValue(":idUser",$idUser, PDO::PARAM_STR);   
                                                $query->bindValue(":typeQuestion","1", PDO::PARAM_STR);
                                                $query->bindValue(":questionDate",$this->datetime(), PDO::PARAM_STR);
				              $query->bindValue(":local_questioned",$idlocal, PDO::PARAM_STR); 
					     $query->bindValue(":fingerprintQuestioned",$fingerprintSelected, PDO::PARAM_STR); 


                                                $result = $query->execute();


                                          $this->send_push_notification(array($regid2),  array("idlocal" => $idlocal, "local" => $idlocal1, "date_s" => $date, "idUserQ" => $idUser, "moreDetails" => $moreDetails));
                                       }   
                                    }
                           }
                        }
                           
                        
                    }
               }
        }

   }
}




//enviar notifica��o caso a localiza��o seja desconhecida
private function sendquestion2 (){

	if($this->get_request_method() == "GET" && !empty($this->args[1])){  

	$idUser=$this->args[1];
	
      $regid2=$this->db->query("SELECT gcm_regID FROM devices WHERE idUser=".$idUser);
        if($regid2->rowCount() > 0) // request already made
        {
           $regid2 = $regid2->fetch(PDO::FETCH_ASSOC);  
           $regid2 = $regid2['gcm_regID'];  

        }
        $date = new DateTime(null, new DateTimeZone('Europe/Lisbon'));                                  
        $date= $date->format('Y-m-d H:i:s');


       $questiondat=$this->db->query("SELECT questionDate FROM feedbackUsers WHERE typeQuestion=2 AND idUser=".$idUser." ORDER BY idQuestion DESC LIMIT 1");
       if($questiondat->rowCount() > 0) // request already made///////////////////////////////////////////////1
	{

       $questiondat = $questiondat->fetch(PDO::FETCH_ASSOC);  
       $questiondat = $questiondat['questionDate'];

        $dif_horas=$this->contarhoras($questiondat,$date);
	//$rand=rand(120,216);//para vers�o de produ��o
	$rand=rand(3,9);//para vers�o de desenvolvimento
            if($dif_horas >= $rand){
                $dia_semana=$this->diasemana($date);

                if($dia_semana == 'S�bado' || $dia_semana == 'Domingo'){
                    date_default_timezone_set('Europe/Lisbon');
                    $time=date('H');

                    if($time>=11 && $time<=22){
                         $idlocal=$this->db->query("SELECT idPlaces FROM fingerprintsHistory WHERE idUser=".$idUser." ORDER BY deviceTimestamp DESC LIMIT 1");
                        if($idlocal->rowCount() > 0) // request already made
                        {
                                $idlocal = $idlocal->fetch(PDO::FETCH_ASSOC);  
                                $idlocal = $idlocal['idPlaces']; 
                                if($idlocal ==0 || $idlocal ==null){
                       // }
                                    $query = $this->db->prepare("INSERT INTO feedbackUsers (idUser,typeQuestion,questionDate) 
                    VALUES (:idUser, :typeQuestion, :questionDate)");
                
                                    $query->bindValue(":idUser",$idUser, PDO::PARAM_STR);   
                                    $query->bindValue(":typeQuestion","2", PDO::PARAM_STR);
                                    $query->bindValue(":questionDate",$this->datetime(), PDO::PARAM_STR);
              
                                    $result = $query->execute();

                                    $this->send_push_notification(array($regid2),  array("date_s2" => $date, "idUserQ2" => $idUser));
                           }
                        }

                    }
                    
                }
                else{ //se for dia da semana
                     date_default_timezone_set('Europe/Lisbon');
                    $time=date('H');
                    if($time>=9 && $time<=21){
                        $idlocal=$this->db->query("SELECT idPlaces FROM fingerprintsHistory WHERE idUser=".$idUser." ORDER BY deviceTimestamp DESC LIMIT 1");
                        if($idlocal->rowCount() > 0) // request already made
                        {
                                $idlocal = $idlocal->fetch(PDO::FETCH_ASSOC);  
                                $idlocal = $idlocal['idPlaces']; 
                                if($idlocal ==0 || $idlocal ==null){
                       // }
                                    $query = $this->db->prepare("INSERT INTO feedbackUsers (idUser,typeQuestion,questionDate) 
                    VALUES (:idUser, :typeQuestion, :questionDate)");
                
                                    $query->bindValue(":idUser",$idUser, PDO::PARAM_STR);   
                                    $query->bindValue(":typeQuestion","2", PDO::PARAM_STR);
                                    $query->bindValue(":questionDate",$this->datetime(), PDO::PARAM_STR);
              
                                    $result = $query->execute();

                                    $this->send_push_notification(array($regid2),  array("date_s2" => $date, "idUserQ2" => $idUser));
                           }
                        }
                           
                        
                    }
               }
            }
        }
        else {//casoquestiondat for null
	   $dia_semana=$this->diasemana($date);
           if($dia_semana == 'S�bado' || $dia_semana == 'Domingo'){
                    date_default_timezone_set('Europe/Lisbon');
                    $time=date('H');



                    if($time>=11 && $time<=22){
                        $idlocal=$this->db->query("SELECT idPlaces FROM fingerprintsHistory WHERE idUser=".$idUser." ORDER BY deviceTimestamp DESC LIMIT 1");
                        if($idlocal->rowCount() > 0) // request already made
                        {
                                $idlocal = $idlocal->fetch(PDO::FETCH_ASSOC);  
                                $idlocal = $idlocal['idPlaces']; 
                                if($idlocal ==0 || $idlocal ==null){
                       // }
                                    $query = $this->db->prepare("INSERT INTO feedbackUsers (idUser,typeQuestion,questionDate) 
                    VALUES (:idUser, :typeQuestion, :questionDate)");
                
                                    $query->bindValue(":idUser",$idUser, PDO::PARAM_STR);   
                                    $query->bindValue(":typeQuestion","2", PDO::PARAM_STR);
                                    $query->bindValue(":questionDate",$this->datetime(), PDO::PARAM_STR);
              
                                    $result = $query->execute();

                                    $this->send_push_notification(array($regid2),  array("date_s2" => $date, "idUserQ2" => $idUser));
                           }
                        }

                    }
                    
                }
                else{ //se for dia da semana
                     date_default_timezone_set('Europe/Lisbon');
                    $time=date('H');



                    if($time>=9 && $time<=21){
			$idlocal=$this->db->query("SELECT idPlaces FROM fingerprintsHistory WHERE idUser=".$idUser." ORDER BY deviceTimestamp DESC LIMIT 1");
                        if($idlocal->rowCount() > 0) // request already made
                        {
                                $idlocal = $idlocal->fetch(PDO::FETCH_ASSOC);  
                                $idlocal = $idlocal['idPlaces']; 
                                if($idlocal ==0 || $idlocal ==null){
                       // }
                                    $query = $this->db->prepare("INSERT INTO feedbackUsers (idUser,typeQuestion,questionDate) 
                    VALUES (:idUser, :typeQuestion, :questionDate)");
                
                                    $query->bindValue(":idUser",$idUser, PDO::PARAM_STR);   
                                    $query->bindValue(":typeQuestion","2", PDO::PARAM_STR);
                                    $query->bindValue(":questionDate",$this->datetime(), PDO::PARAM_STR);
              
                                    $result = $query->execute();

                                    $this->send_push_notification(array($regid2),  array("date_s2" => $date, "idUserQ2" => $idUser));
                           }
                        }
                           
                        
                    }
               }
        }
    }

}








 
   

		//recebe a resposta do utilizador � quest�o 1
		private function sendresponseQ1(){

    			if($this->get_request_method() != "POST" || !isset($this->_raw['response']) || !isset($this->_raw['date_e']) || empty($this->args[1])){
				$this->response('',400);
			}

			$date2 = new DateTime(null, new DateTimeZone('Europe/Lisbon'));                                  
        			$date2= $date2->format('Y-m-d H:i:s');

                        
   			$idUser = $this->args[1];
    			$response=$this->_raw['response'];
    			$responseDate=$date2;


    			//$idQuestion = $this->db->query("SELECT MAX(idQuestion) FROM feedbackUsers WHERE typeQuestion=1 AND idUser= ".$idUser);
			$idQuestion=$this->db->query("SELECT idQuestion FROM feedbackUsers WHERE typeQuestion=1 AND idUser=".$idUser." ORDER BY idQuestion DESC LIMIT 1");
    			$idQuestion = $idQuestion->fetch(PDO::FETCH_ASSOC);  
    			$idQuestion = $idQuestion['idQuestion'];



    			$res = $this->db->query("UPDATE feedbackUsers SET response='".$response."', responseDate='".$responseDate."' WHERE idQuestion=".$idQuestion);
			if($res->rowCount()>0){ 
				
			$fingerprintQuestioned=$this->db->query("SELECT fingerprintQuestioned FROM feedbackUsers WHERE idQuestion=".$idQuestion);
    			$fingerprintQuestioned= $fingerprintQuestioned->fetch(PDO::FETCH_ASSOC);  
    			$fingerprintQuestioned = $fingerprintQuestioned['fingerprintQuestioned'];


			$counters=$this->db->prepare("SELECT idQuestion FROM feedbackUsers WHERE fingerprintQuestioned='$fingerprintQuestioned' AND response='$response'");
			$counters->execute();
			$counter = $counters->rowCount();
			


			if($response=='NO'){
				$resu = $this->db->query("UPDATE fingerprints SET unlikes='".$counter."' WHERE idFingerprints=".$fingerprintQuestioned);
	
			}
			if($response=='YES'){
				$resu = $this->db->query("UPDATE fingerprints SET likes='".$counter."' WHERE idFingerprints=".$fingerprintQuestioned);

			}





			}
			
			


		}////

		
		//recebe a resposta do utilizador � quest�o 2
		private function sendresponseQ2(){

    			if($this->get_request_method() != "POST" || !isset($this->_raw['response']) || !isset($this->_raw['date_e']) || empty($this->args[1])){
				$this->response('',400);
			}

			$date2 = new DateTime(null, new DateTimeZone('Europe/Lisbon'));                                  
        			$date2= $date2->format('Y-m-d H:i:s');

                        
   			$idUser = $this->args[1];
    			$response=$this->_raw['response'];
    			$responseDate=$date2;




    			//$idQuestion = $this->db->query("SELECT MAX(idQuestion) FROM feedbackUsers WHERE typeQuestion=1 AND idUser= ".$idUser);
			$idQuestion=$this->db->query("SELECT idQuestion FROM feedbackUsers WHERE typeQuestion=2 AND idUser=".$idUser." ORDER BY idQuestion DESC LIMIT 1");
    			$idQuestion = $idQuestion->fetch(PDO::FETCH_ASSOC);  
    			$idQuestion = $idQuestion['idQuestion'];



    			$res = $this->db->query("UPDATE feedbackUsers SET response='".$response."', responseDate='".$responseDate."' WHERE idQuestion=".$idQuestion);
			
		}////






function diasemana($data) {
	$ano =  substr("$data", 0, 4);
	$mes =  substr("$data", 5, -3);
	$dia =  substr("$data", 8, 9);

	$diasemana = date("w", mktime(0,0,0,$mes,$dia,$ano) );

	switch($diasemana) {
		case"0": $diasemana = "Domingo";       break;
		case"1": $diasemana = "Segunda-Feira"; break;
		case"2": $diasemana = "Ter�a-Feira";   break;
		case"3": $diasemana = "Quarta-Feira";  break;
		case"4": $diasemana = "Quinta-Feira";  break;
		case"5": $diasemana = "Sexta-Feira";   break;
		case"6": $diasemana = "S�bado";        break;
	}

	return "$diasemana";

}
   
   
   
   function contarhoras($data1,$data2) {

      $time_inicial = strtotime($data1);
      $time_final = strtotime($data2);

      $d3 = $time_final - $time_inicial;
      $dias=round($d3/60/60/24);
      $horas=round($d3/60/60);

      return $horas;
     
       
  }


	}
	
	// Initiiate Library
	
	$api = new API;
	$api->processApi();
?>
