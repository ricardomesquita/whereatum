<?php    
	require_once("Rest.inc.php");
	error_reporting(E_ERROR | E_PARSE);
	
	class API extends REST {
	
		public $data = "";
        private $args = Array();	
		private $db = NULL;
	
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
                                                         
            //echo "func:".$func." meth: ".$this->get_request_method()." Args:".count($args)."\n"; 
            
            if((int)method_exists($this,$func) > 0)
            	$this->$func();
            else
            	$this->response('',404);				// If the method not exist with in this class, response would be "Page not found".
                    
            //validate Oauth and get userID                                 
		}
		
		/*private function testecomparativo(){
		
			if(!isset($this->args[1]) || empty($this->args[1])) return;
			
			$aux = explode(',',$this->args[1]);			
			$idUser = $aux[0];
			$idIni = $aux[1];
			$idFin = $aux[2];
			$idsFingerprints = $this->db->query("SELECT idFingerprint FROM fingerprintsHistory where idUser = ".$idUser." and idFingerprint >= ".$idIni." and idFingerprint <=".$idFin);
			$idsFingerprints = $idsFingerprints->fetchAll(PDO::FETCH_ASSOC);
			
			$avg_execution_time_v2 = 0;
			$avg_execution_time_v1 = 0;
			$v1 = array();
			$v2 = array();
			foreach($idsFingerprints as $fingerpint){
			
				$res = $this->db->query("SELECT * from apsFingerprintsHistory where idFingerprint = ".$fingerpint["idFingerprint"]);
				$aps = $res->fetchAll(PDO::FETCH_ASSOC);
				$fp = array();
				foreach($aps as $ap){
					$fp[$ap['mac']]= $ap['rssi'];
				}				
				// measure execution time				
				$start_time_v2 = microtime(true);							
				
				//função de comparação
				// v2
				$local = $this->fingerprintsRankingV2($fp);								
				$avg_execution_time_v2	= $avg_execution_time_v2 +(microtime(true) - $start_time_v2);				
				
				$v2[] = $local;
				// _____________________________________________
				//v1 						
				$start_time_v1 = microtime(true);							
				
				//função de comparação
				// v1
				$local = $this->fingerprintsRanking($fp);								
				$avg_execution_time_v1	= $avg_execution_time_v1 + (microtime(true) - $start_time_v1);
				$v1[] = $local;														
			}
			
			$avg_execution_time_v2 = ($avg_execution_time_v2 / 5);
			$avg_execution_time_v1 = ($avg_execution_time_v1 / 5);
			echo "v2_execution time: ".$avg_execution_time_v2."\n";
			print_r ($v2);
			echo "v1_execution time: ".$avg_execution_time_v1."\n";
			print_r ($v1);
		}*/

		 private function teste(){
		
			if(isset($this->args[1]) && !empty($this->args[1]))
			{
				$aux = explode(',',$this->args[1]);			
				$f1 = $aux[0];
				$f2 = $aux[1];
				//$res = $this->db->query("SELECT * from aps where idFingerprints = ".$f1);
				$res = $this->db->query("SELECT * from apsFingerprintsHistory where idFingerprint = ".$f1);
				$res = $res->fetchAll(PDO::FETCH_ASSOC);
				$f1 = array();
				foreach($res as $f){
					$f1[$f['mac']]= $f['rssi'];
				}
				//$res = $this->db->query("SELECT * from aps where idFingerprints = ".$f2);
				$res = $this->db->query("SELECT * from apsFingerprintsHistory where idFingerprint = ".$f2);
				$res = $res->fetchAll(PDO::FETCH_ASSOC);			
				$f2 = array();
				foreach($res as $f){
					$f2[$f['mac']]= $f['rssi'];
				}	
			}else
			{
				$aux = $this->_raw;
				$f1 = $aux['f1'];
				$f2 = $aux['f2'];			
			}				
			echo $this->calculateFingerprintsDistance($f1, $f2);
			
        } 
	


        /* ______________________________ Fingerprints ______________________________*/        
        
		private function fingerprints(){
		
		    // PARAMS
			$timeInterval = 1; // time interval between fingerprints          
					  
			if($this->get_request_method() != "POST" || empty($this->_raw['idDevice'])){
				$this->response('',400);
			}
						                           			
            //send http Request to MP web service
            //URL: http://193.137.8.29:8080/RTLS/pe1
            //PARAMS: add deviceID (MAC) and f=1
            //  APmac = RSSI ..
            //00:0e:d7:cd:35:10=-87      
			$device = $this->_raw['idDevice'];
            $url = 'http://rtls.dsi.uminho.pt:8080/RTLS/pe1';
            $data = array('f' => '1', 'deviceID' => $device);

			$arquivo = fopen('dados2.txt','w+');
			fwrite ($arquivo, 'deviceID : '.$device."\n");
			fclose($arquivo);
			
			$aps = array();
            foreach($this->_raw as $i => $val){
                if ($i != 'idDevice' && $i != 'timestamp' && $i != 'motion')
				{
                    $data[$i] = $val;    
					$aps[$i] = $val;
				}
            } 
			
				//TEMP (just for versions under 1.3)
			if(isset($this->_raw['timestamp']))
				$timestamp = $this->_raw['timestamp'];				
			else
				$timestamp = $this->datetime();						
						
			// try to match user fingerprint to a place
		
			// get user ID of the device
			$res = $this->db->query("SELECT idUser FROM devices WHERE idDevice = '".$device."'");
			if($res->rowCount()>0){
				$idUser = $res->fetch(PDO::FETCH_ASSOC);
				$idUser = $idUser["idUser"];
			}else{
				$this->response('',400);
			}			
			
			// measure execution time
			$start_time = microtime(true);
			
			if($this->isCurrent($idUser,$timestamp)){
					//get user lastKnownLocation 
				$res = $this->db->query("SELECT idUser FROM lastKnownLocation WHERE idUser = ".$idUser);
				if($res->rowCount()>0){ // user has last known location
				
						// first attempt is check if lastKnownLocation is in the past $timeInterval minutes compared with received fingerprint
						
					$res = $this->db->query("SELECT * FROM lastKnownLocation WHERE idUser = ".$idUser." and timestamp > DATE_SUB(now(), INTERVAL ".$timeInterval." MINUTE)");				
					if($res->rowCount()<1){													
						// user's last known location exists but has already passed the defined time interval				
						$idPlacee = $this->fingerprintsRankingV2($aps); // get place id by comparing stored annotated fingerprints///////////////////////
						$idPlace = $idPlacee[0];
						$idFin = $idPlacee[1];


						if($idPlace != null){									
							// Update lastKnownLocation											
							$this->db->query("UPDATE lastKnownLocation SET timestamp = '".$timestamp."', isCurrent = 1, idPlaces = ".$idPlace." 
								WHERE idUser = ".$idUser);																					
								// save fingerprint in database
							$this->saveFingerprintInDB($idUser,$device,$timestamp,$aps,$idPlace,(microtime(true)-$start_time), $idFin);

								
								
						}else // fingerprint didn't match to any place
						{
							// Update lastKnownLocation	as isn't the current one										
							$this->db->query("UPDATE lastKnownLocation SET isCurrent = 0 WHERE idUser = ".$idUser);
						
								// save fingerprint in database
							$this->saveFingerprintInDB($idUser,$device,$timestamp,$aps,null,(microtime(true)-$start_time),$idFin);
							
								// send fingerprint to Positioning Engine
							$result = $this->httpPOST($url,$data); 

							
						}
					}else{ 
						$res = $res->fetch(PDO::FETCH_ASSOC);
						
							// save fingerprint in database
						$this->saveFingerprintInDB($idUser,$device,$timestamp,$aps, $res['idPlaces'],(microtime(true)-$start_time), $idFin);

							
					}				
				}else{ // user's last known location doesn't exist 
								
					$idPlacee = $this->fingerprintsRankingV2($aps); // get place id by comparing stored annotated fingerprints////////////////////////////////////
					$idPlace = $idPlacee[0];
					$idFin = $idPlacee[1];


					if($idPlace != null){						
						// Insert lastKnownLocation					
						$this->db->query("INSERT INTO lastKnownLocation (idUser, timestamp, isCurrent, idPlaces)
							VALUES (".$idUser.",'".$timestamp."',1,".$idPlace.")");
					
							// save fingerprint in database
						$this->saveFingerprintInDB($idUser,$device,$timestamp,$aps,$idPlace,(microtime(true)-$start_time), $idFin);
							
					}else{
						$this->saveFingerprintInDB($idUser,$device,$timestamp,$aps,null,(microtime(true)-$start_time), $idFin);									
							// send fingerprint to Positioning Engine
						$result = $this->httpPOST($url,$data); 
							
					}										
				}
			}else{ // if fingerprint is older than lastKnownLocation
				$idPlacee = $this->fingerprintsRankingV2($aps); // get place id by comparing stored annotated fingerprints/////////////////////////////////////////
				$idPlace = $idPlacee[0];
				$idFin = $idPlacee[1];

				
				if($idPlace != null){
					// save fingerprint in database
						$this->saveFingerprintInDB($idUser,$device,$timestamp,$aps,$idPlace,(microtime(true)-$start_time), $idFin);
							
				}else
					$this->saveFingerprintInDB($idUser,$device,$timestamp,$aps,null,(microtime(true)-$start_time), $idFin);
							
			}
									
			$this->response('OK',200); 
       
		}
		
		private function isCurrent($idUser,$timestamp){		
			$res = $this->db->query("SELECT * FROM lastKnownLocation WHERE idUser= ".$idUser);
			if($res->rowCount()>0){ // if this device has already a fingerprint
				$fingerprintDB = $res->fetch(PDO::FETCH_ASSOC);				
				//check if fingerprint is older than the fingerprint database 
				if(strtotime($timestamp) < strtotime($fingerprintDB["timestamp"])){
					return false;
				}else return true;						
			}else return true;
		}
			
			// Save fingerprint of a specific device in the database
		private function saveFingerprintInDB($idUser,$device,$timestamp,$aps,$idPlace, $executionTime, $fingerprintSelected){			
			
			$query = $this->db->prepare("INSERT INTO fingerprintsHistory (deviceTimestamp, serverTimestamp, idPlaces, idDevice, idUser, executionTime, fingerprintSelected) 
                    VALUES (:deviceTimestamp, :serverTimestamp, :idPlaces, :idDevice, :idUser, :executionTime, :fingerprintSelected)");
			
			//insert new fingerprint in the database
			$query->bindValue(":deviceTimestamp",$timestamp, PDO::PARAM_STR);
			$query->bindValue(":serverTimestamp",$this->datetime(), PDO::PARAM_STR);
			$query->bindValue(":idPlaces",$idPlace, PDO::PARAM_STR);   
			$query->bindValue(":idDevice",$device, PDO::PARAM_STR);
			$query->bindValue(":idUser",$idUser, PDO::PARAM_STR);                                      
			$query->bindValue(":executionTime",$executionTime, PDO::PARAM_STR);
			$query->bindValue(":fingerprintSelected",$fingerprintSelected, PDO::PARAM_STR);
			$result = $query->execute();
			$fingerprintID = $this->db->lastInsertId(); 							

			// insert APs' fingerprint in the database
			foreach($aps as $mac => $rssi){
				$this->db->query("INSERT INTO apsFingerprintsHistory (idFingerprint,mac,rssi) 
					VALUES (".$fingerprintID.",'".$mac."', '".$rssi."')");
			}	

			
			return true;
		}				
		
                    // Get Places Outside UM
        private function placessearch(){                        
                                            
            if($this->get_request_method() == "GET" && isset($this->args[1])){
                $coordinates = explode(',',$this->args[1]); 
                $lat = $coordinates[0];
                $lng = $coordinates[1];    
                
                $dbPlaces = array();
                
                    //Get places from DB    
                $res = $this->db->query("SELECT * FROM places WHERE idType=8 and lat is not null and lng is not null");
                if($res->rowCount()>0){
                    $places = $res->fetchAll(PDO::FETCH_ASSOC);                    
                    foreach($places as &$place){                        
                        // calculate distance between coordinates
                        $distance = round($this->vincentyGreatCircleDistance($lat, $lng,$place['lat'], $place['lng']));
                        if($distance <150)
                        {
				
                            $location = array();                                                                                                                
                            $res = $this->db->query("SELECT ci.name AS city,coun.name AS country,SUBSTRING_INDEX(coun.moreDetails,'code:', -1) AS cc  FROM places AS ci, places AS coun WHERE ci.idPlaces=".$place['idParent']." AND coun.idPlaces = ci.idParent");
                            if($res->rowCount()>0){
                                $res = $res->fetch(PDO::FETCH_ASSOC);                                
                                $location['country'] = $res['country'];
                                $location['cc'] = $res['cc'];
                                $location['city'] = $res['city'];
			      
                            }                            
                            if($place['moreDetails'] != null){
                                $aux = explode("address:", $place['moreDetails']);
                                if(count($aux)>1)                                
                                    $location['address'] = $aux[1];                                                                                                                                                                    
                            }                            
                            $location['distance'] = $distance;
                            $location['lat'] = $place['lat'];
                            $location['lng'] = $place['lng'];
                            
                            $checkinsCount = $this->db->query("SELECT COUNT(idFingerprints) AS checkinsCount FROM fingerprints WHERE idPlaces = ".$place['idPlaces']);
                            $checkinsCount = $checkinsCount->fetch(PDO::FETCH_ASSOC);                            
                            
                            $dbPlaces[] = array("id"=>$place['idPlaces'],"name"=>$place['name'],"checkinsCount"=>$checkinsCount['checkinsCount'],"location"=>$location);
                        }                                                                                                      
                    }                    
                }
                                 
                //Get places from Foursquare using GPS coordinates and merge with the Database results               
                $response =array_merge($dbPlaces,$this->foursqaureSearch($this->args[1])) ;
                
                //Exclude duplicates
                $outputArray = array(); // The results will be loaded into this array.
                $keysArray = array(); // The list of keys will be added here.
                foreach ($response as $innerArray) { // Iterate through your array.
                    if (!in_array($innerArray['name'], $keysArray)) { // Check to see if this is a key that's already been used before. //in_array -> Checks if a value exists in an array
                        $keysArray[] = $innerArray['name']; // If the key hasn't been used before, add it into the list of keys.
                        $outputArray[] = $innerArray; // Add the inner array into the output.
                    }
                }
                usort($outputArray, array($this,"searchSort"));                                
                $this->response($this->json(array("places"=>$outputArray)),200);                
            }else            
                $this->response('',400);
                        
        }
        private function places(){
            
                // POST Place - Inside UM
            
            if($this->get_request_method() == "POST" && isset($this->args[1]) && $this->args[1] == "um" && isset($this->_raw['fingerprint']) && isset($this->_raw['location'])){
                $fingerprint = $this->_raw['fingerprint'];                                
                $location = $this->_raw['location'];                                                
                
                $campus = $this->_raw['location']['campus'];
                $campusID = $this->_raw['location']['campusID'];
                $building = $this->_raw['location']['building'];
                $buildingID = $this->_raw['location']['buildingID'];
                $floor = $this->_raw['location']['floor'];
                $floorID = $this->_raw['location']['floorID'];
                $room = $this->_raw['location']['room'];                
                $roomID = isset($this->_raw['location']['roomID'])?$this->_raw['location']['roomID']:"new";
				$lat = isset($this->_raw['location']['lat'])?$this->_raw['location']['lat']:null;
				$lng = isset($this->_raw['location']['lng'])?$this->_raw['location']['lng']:null;
	       $location_changed = $this->_raw['location']['location_changed'];
                
                                                

		// check if room exists                
                if(strcmp($roomID,"new") != 0){ // room exists                        
                    $placeID = $roomID;
					$res = $this->db->query("SELECT * FROM places WHERE idPlaces = ".$placeID." AND lat IS NULL AND lng IS NULL");
					if($res->rowCount()>0 && $lat != null && $lng != null){
						$this->db->query("UPDATE places SET lat = '".$lat."', lng = '".$lng."' WHERE idPlaces=".$placeID);
					}									
                }else{                 // room doesn't exist                
                    // insert new room/space
					if($lat == null || $lng == null){
						$this->db->query("INSERT INTO places (name, idParent, idType, idUser) 
						VALUES (\"".$room."\", '".$floorID."' , 7,".$fingerprint['idUser'].")");
					}else{
						$this->db->query("INSERT INTO places (name, idParent, idType, idUser, lat, lng) 
						VALUES (\"".$room."\", '".$floorID."' , 7,".$fingerprint['idUser'].",".$lat.",".$lng." )");
                    }
                    $placeID = $this->db->lastInsertId();                      
                }    
                
                // insert fingerprint
                
                $query = $this->db->prepare("INSERT INTO fingerprints (deviceTimestamp,serverTimestamp,idPlaces,idDevice,idUser, location_changed) 
                    VALUES (:deviceTimestamp, :serverTimestamp, :idPlaces, :idDevice, :idUser, :location_changed)");
                
                $query->bindValue(":deviceTimestamp",$fingerprint['timestamp'], PDO::PARAM_STR);
                $query->bindValue(":serverTimestamp",$this->datetime(), PDO::PARAM_STR);
                $query->bindValue(":idPlaces",$placeID, PDO::PARAM_STR);   
                $query->bindValue(":idDevice",$fingerprint['idDevice'], PDO::PARAM_STR);
                $query->bindValue(":idUser",$fingerprint['idUser'], PDO::PARAM_STR);    
	       $query->bindValue(":location_changed",$location_changed, PDO::PARAM_STR);                                   
                $result = $query->execute();
                $fingerprintID = $this->db->lastInsertId(); 
                
                // insert APs
				$aps = array();
                $queryAPs = $this->db->prepare("INSERT INTO aps (mac,rssi,idFingerprints) VALUES (:mac, :rssi, :idFingerprints)");
                foreach($fingerprint as $i => $val){
                    if(strcmp($i,'idDevice') != 0 && strcmp($i,'idUser') != 0 && strcmp($i,'timestamp') != 0){
                        $aps[$i] = $val;
						$queryAPs->bindValue(":mac",$i, PDO::PARAM_STR);
                        $queryAPs->bindValue(":rssi",$val, PDO::PARAM_STR);
                        $queryAPs->bindValue(":idFingerprints",$fingerprintID, PDO::PARAM_STR);                                  
                        $queryAPs->execute();   
                    }                                            
                }
				// save last fingerprint in DB
				$this->saveFingerprintInDB($fingerprint['idDevice'], $fingerprint['timestamp'], $aps);
				
				// Insert last known location 
				$res = $this->db->query("SELECT * FROM lastKnownLocation WHERE idUser = ".$fingerprint['idUser']);
				if($res->rowCount()){
					$this->db->query("UPDATE lastKnownLocation SET timestamp = '".$fingerprint['timestamp']."', isCurrent = 1, idPlaces = ".$placeID." 
								WHERE idUser = ".$fingerprint['idUser']);
				} else{ // user's last known location doesn't exist 												
					// Insert lastKnownLocation					
					$this->db->query("INSERT INTO lastKnownLocation (idUser, timestamp, isCurrent, idPlaces)
						VALUES (".$fingerprint['idUser'].",'".$fingerprint['timestamp']."',1,".$placeID.")");					
				}				
                
                $this->response('',200);
                             
                                                                                                                                                                                                                                   
            }else 
                        // POST Place - Outside UM            
            
            if($this->get_request_method() == "POST" && isset($this->_raw['fingerprint']) && isset($this->_raw['location'])){
                
                $fingerprint = $this->_raw['fingerprint'];                                
                $location = $this->_raw['location'];                                                
                
                $country = $this->_raw['location']['country'];
				$cc = isset($this->_raw['location']['cc'])?$this->_raw['location']['cc']:null;                
                $city = $this->_raw['location']['city'];
                if(isset($this->_raw['location']['address']))
                {
                    $address = $this->_raw['location']['address'];
                }else
                    $address = null;                                        
                $place = $this->_raw['location']['place'];
                $lat = isset($this->_raw['location']['lat'])?$this->_raw['location']['lat']:null;
				$lng = isset($this->_raw['location']['lng'])?$this->_raw['location']['lng']:null;   
	        $location_changed = $this->_raw['location']['location_changed'];      
                
                //check if country exists
                $res = $this->db->query("SELECT idPlaces as id, name FROM places WHERE idType = 1 and name = \"".$country."\"");
                if($res->rowCount()>0){
                    $countryID = $res->fetch();
                    $countryID = $countryID['id'];                    
                }else{
                    $res = $this->db->query("INSERT INTO places (name,idParent,idType) VALUES (\"".$country."\",null,1)");
                    $countryID = $this->db->lastInsertId();
                }
                
                //check if city exists
                $res = $this->db->query("SELECT idPlaces as id, name FROM places WHERE name = \"".$city."\" and idType = 2 and idParent = ".$countryID);
                if($res->rowCount()>0){
                    $cityID = $res->fetch();
                    $cityID = $cityID['id'];                    
                }else{
                    $res = $this->db->query("INSERT INTO places (name,idParent,idType) VALUES (\"".$city."\",".$countryID.",2)");
                    $cityID = $this->db->lastInsertId();
                }
                
                //check if place exists
				$res = $this->db->query("SELECT idPlaces as id, name FROM places WHERE idType = 8 and idParent = ".$cityID." and name = \"".$place."\"");
                if($res->rowCount()>0){
                    $placeID = $res->fetch();
                    $placeID = $placeID['id'];                    
                }else{
					$res = $this->db->query("SELECT * FROM places WHERE idType = 8 and name = \"".$place."\"");
					if($res->rowCount()>0){
						$row = $res->fetch();
						// check, by distance, if it is the same place to edit it.
						$distance = round($this->vincentyGreatCircleDistance($lat, $lng,$row['lat'], $row['lng']));
                        if($distance <100) // it's near, edit the place
                        {
							if($address == null){
								$this->db->query("UPDATE places SET idParent = ".$cityID." WHERE idPlaces= ".$row['idPlaces']);
							}else						
								$this->db->query("UPDATE places SET idParent = ".$cityID.", moreDetails=\"address:".$address."\" WHERE idPlaces= ".$row['idPlaces']);
								
							$placeID = $row['idPlaces'];
						}else{
							if($address == null)
								$res = $this->db->query("INSERT INTO places (name,idParent,idType, idUser, lat, lng) VALUES (\"".$place."\",".$cityID.",8,".$fingerprint['idUser'].", '".$lat."','".$lng."')");
							else
								$res = $this->db->query("INSERT INTO places (name,idParent,idType,moreDetails, idUser, lat, lng) VALUES (\"".$place."\",".$cityID.",8,\"address:".$address."\",".$fingerprint['idUser'].", '".$lat."','".$lng."')");                                           
							$placeID = $this->db->lastInsertId();
						}										
					}else{
						if($address == null)
							$res = $this->db->query("INSERT INTO places (name,idParent,idType, idUser, lat, lng) VALUES (\"".$place."\",".$cityID.",8,".$fingerprint['idUser'].", '".$lat."','".$lng."')");
						else
							$res = $this->db->query("INSERT INTO places (name,idParent,idType,moreDetails, idUser, lat, lng) VALUES (\"".$place."\",".$cityID.",8,\"address:".$address."\",".$fingerprint['idUser'].", '".$lat."','".$lng."')");                                           
						$placeID = $this->db->lastInsertId();
					}
				}                
                
                // insert fingerprint
                
                $query = $this->db->prepare("INSERT INTO fingerprints (deviceTimestamp,serverTimestamp,idPlaces,idDevice,idUser, location_changed) 
                    VALUES (:deviceTimestamp, :serverTimestamp, :idPlaces, :idDevice, :idUser, :location_changed)");
                
                $query->bindValue(":deviceTimestamp",$fingerprint['timestamp'], PDO::PARAM_STR);
                $query->bindValue(":serverTimestamp",$this->datetime(), PDO::PARAM_STR);
                $query->bindValue(":idPlaces",$placeID, PDO::PARAM_STR);   
                $query->bindValue(":idDevice",$fingerprint['idDevice'], PDO::PARAM_STR);
                $query->bindValue(":idUser",$fingerprint['idUser'], PDO::PARAM_STR);   
 	       $query->bindValue(":location_changed",$location_changed, PDO::PARAM_STR);                                    
                $result = $query->execute();
                $fingerprintID = $this->db->lastInsertId(); 
                
                // insert APs
                                
                $queryAPs = $this->db->prepare("INSERT INTO aps (mac,rssi,idFingerprints) VALUES (:mac, :rssi, :idFingerprints)");
                foreach($fingerprint as $i => $val){
                    if(strcmp($i,'idDevice') != 0 && strcmp($i,'idUser') != 0 && strcmp($i,'timestamp') != 0){
                        $queryAPs->bindValue(":mac",$i, PDO::PARAM_STR);
                        $queryAPs->bindValue(":rssi",$val, PDO::PARAM_STR);
                        $queryAPs->bindValue(":idFingerprints",$fingerprintID, PDO::PARAM_STR);                                  
                        $queryAPs->execute();   
                    }                                            
                }
				
				// save last fingerprint in DB
				//$this->saveFingerprintInDB($fingerprint['idDevice'], $fingerprint['timestamp'], $aps);
				
				// Insert last known location 
				$res = $this->db->query("SELECT * FROM lastKnownLocation WHERE idUser = ".$fingerprint['idUser']);
				if($res->rowCount()){
					$this->db->query("UPDATE lastKnownLocation SET timestamp = '".$fingerprint['timestamp']."', isCurrent = 1, idPlaces = ".$placeID." 
								WHERE idUser = ".$fingerprint['idUser']);
				} else{ // user's last known location doesn't exist 												
					// Insert lastKnownLocation					
					$this->db->query("INSERT INTO lastKnownLocation (idUser, timestamp, isCurrent, idPlaces)
						VALUES (".$fingerprint['idUser'].",'".$fingerprint['timestamp']."',1,".$placeID.")");					
				}
                
                $this->response('',200);
                
                                
            }else            
                        //    Get UM places
                        
            if($this->get_request_method() == "GET" && isset($this->args[1]) && $this->args[1] == "um"){													                                   
            
                $campus = $this->db->query("SELECT idPlaces as id, name FROM places WHERE idType = 4");            
                if($campus->rowCount()>0)
                {                                                                    
                    $campus = $campus->fetchAll(PDO::FETCH_ASSOC);                                                                                     
                    foreach($campus as &$camp){                                                            
                        $buildings = $this->db->query("SELECT idPlaces as id, name FROM places WHERE idType = 5 and idParent = ".$camp['id']."");                    
                        if($buildings->rowCount()>0)
                        {
                            $buildings = $buildings->fetchAll(PDO::FETCH_ASSOC);                                                                                                                    
                            foreach($buildings as &$building)
                            {                                   
                                $floors = $this->db->query("SELECT idPlaces AS id, name FROM places WHERE idType = 6 and idParent = ".$building['id']);
                                if($floors->rowCount()>0)
                                {
                                    $floors = $floors->fetchAll(PDO::FETCH_ASSOC);
                                    foreach($floors as &$floor)
                                    {
                                        $rooms = $this->db->query("SELECT idPlaces AS id, name FROM places WHERE idType = 7 and idParent = ".$floor['id']);
                                        if($rooms->rowCount()>0)
                                        {
                                            $rooms = $rooms->fetchAll(PDO::FETCH_ASSOC);
                                            foreach($rooms as &$room)
                                            {
                                                $checkinsCount = $this->db->query("SELECT COUNT(idFingerprints) AS checkinsCount FROM fingerprints WHERE idPlaces = ".$room['id']);
                                                $checkinsCount = $checkinsCount->fetch(PDO::FETCH_ASSOC);
                                                $room['checkinsCount'] = $checkinsCount['checkinsCount'];
                                            }                                                
                                            $floor["rooms"]=$rooms;                                
                                        }                                        
                                    }
                                    $building["floors"]=$floors;                                     
                                }                                                                                                                        
                            }
                            $camp["buildings"] = $buildings;
                                                                                                     
                        }                                                                  
                    }                    
                    $this->response($this->json(array("campuses" => $campus)),200);                                                                      
                }   
            }else{
                $this->response('',400);
            }         
		}		                		
		
        private function datetime(){
            $date = new DateTime(null, new DateTimeZone('Europe/London'));                                  
            return $date->format('Y-m-d H:i:s');
        }
        private function foursquareDate(){
            $date = new DateTime(null, new DateTimeZone('Europe/London'));                                  
            return $date->format('Ymd');
        }
        
		/*
		 *	Encode array into JSON
		*/
		private function json($data){
			if(is_array($data)){
				return json_encode($data);
			}
		}
        
        private function foursqaureSearch($ll){
				
			$client_id = "Y3O5Z1SCI4KQHJXZQUVALVCX5OGBJABWZC0YE2SCKNIVMPSR";
			$client_secret = "LYHMARIJRMJ5LWCPKO0I3LYC4YVQAKTS0GNHVOQZP2G1FTYR";
			$limit = "15";
			$intent = "checkin";
            $response = file_get_contents("https://api.foursquare.com/v2/venues/search?client_id=".$client_id."&client_secret=".$client_secret."&v=".$this->foursquareDate()."&limit=".$limit."&intent=".$intent."&ll=".$ll);
            $response = json_decode($response,true);
            $venues = $response['response']['venues']; 
            $foursquarePlaces = array();
            foreach($venues as $venue){
                if(isset($venue['location']['city']) && $venue['location']['distance'] < 150){                    
                    $foursquarePlaces[] = array("id"=>$venue['id'],"name"=>$venue['name'],"location"=>$venue['location']);
                }                
            }
            //usort($foursquarePlaces, array($this,"searchSort"));                  
            return $foursquarePlaces;  
        }
        private function searchSort($a, $b){
            
            if(isset($a['location']['distance']) && isset($b['location']['distance']))
            {
                $aDist = $a['location']['distance'];
                $bDist = $b['location']['distance'];
            }else
                return 0;
                
            if($aDist == $bDist) return 0;
            return $aDist > $bDist ? 1 : -1;
        }
        private function httpPOST($url, $data){
            // use key 'http' even if you send the request to https://...
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                    'ignore_errors' => true,
                ),
            );
            
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);            
                                    
            return preg_replace('/\s+/', '', $result);                 
        }

		//________________________________________________________________________________________
		
		
			// compare two fingerprint  - It's not used
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
				return 100;			
			
			$remaining = array_diff_key($f2,$result); // Get APs that isn't in Fingerprint 1			
			foreach($remaining as $k=>$v){
				$result[$k] = abs(abs($v) - abs($missingRSSIvalue)); // calculate the difference with defined value		
			}

			// calculations
			$distance = array_sum($result)/count($result); // RSSI value / number of APs								
			return $distance;
		}
		
		//________________________________________________________________________________________
		
			// ranking process - trying to match fingerprint with database fingerprints - Only 1 fingerprint per place
		private function fingerprintsRanking($fingerprint){
		
			//PARAMS
			$threshold = 17.5;
		
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
				
				// check if lowest value is under threshold value					
				if ($ranking[$firstKey] < $threshold){				
					//get first fingerprint and return place ID				
					return $places[$firstKey]; 
				}else return null;												
			}else return null;						
		}		
		
		// ranking process - trying to match fingerprint with database fingerprints - Maximum of "limitPerPlace" fingerprints per place
		private function fingerprintsRankingV2($fingerprint){
		
			//PARAMS
			$threshold = 17.5; 
			$limitPerPlace = 10;
		
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
						// Save association between fingerprint and place
						$places[$row["idFingerprints"]] = $row["idPlaces"];						
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
			
					return array($places[$firstKey], $firstKey); 


				}else return null;												
			}else return null;						
		}
        
        private static function vincentyGreatCircleDistance(
          $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
        {  
            // convert from degrees to radians
            $latFrom = deg2rad($latitudeFrom);
            $lonFrom = deg2rad($longitudeFrom);
            $latTo = deg2rad($latitudeTo);
            $lonTo = deg2rad($longitudeTo);
            
            $lonDelta = $lonTo - $lonFrom;
            $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
            $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);
            
            $angle = atan2(sqrt($a), $b);
            return $angle * $earthRadius;
        }   




	}
	
	// Initiiate Library
	
	$api = new API;
	$api->processApi();
?>

