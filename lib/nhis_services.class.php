<?php

	/****************************************************************************
	 * File: nhis_service.class.php
	 * Author : Phusit Roongroj <phusit@nectec.or.th>
	 * Internet Innovation Lab (INO)
	 * National Electronics and Computer Technology Center (NECTEC)
	 * NHIS Project web services
 	 * 10-2019 
	***************************************************************************/
	@require_once "../robot/database.class.sqlite.php";
	
	class NHIS_Webservices 
	{
	        private $t,$c,$config;	 /* Input variable parameter */
		private $result = array ();	/* Result variable */

		/************************************************************
                * Class constructor 
                * Parameter: t[data service type], c[hosp_code]
                ************************************************************/
		/* -- Constuctor -- */
		public function __construct ($t=null,$c=null)
		{
			$this->config = parse_ini_file ("../robot/settings.ini.php");
			$this->t  = $t;
			$this->c  = $c;
		}


		/************************************************************
                * Class destructor
                * No event.
                ************************************************************/
		public function __destruct ()
		{
			/*---- Blank -----*/
		}


		/************************************************************
                * Display error page if url is wrong!
                * Woring with nginx.
                ************************************************************/
		public function Access_error ()
		{
			$errMsg[0]['error']  = "Invalid URL!";
			return json_encode ($errMsg);
		} 



	       /************************************************************
                * Main public method 
                * Check services type and call private method.
                ************************************************************/
		public function callServiceType ()
		{
			/* get meta data from META.inf */
			$this->result['meta'] = self::getMetaData ();
			if ( $this->t != null ) {
			if ( strtoupper ( $this->t ) == 'NAME' ){ self::getName ();} 
			else if ( strtoupper ( $this->t ) == 'LASTCON' ) { self::getLastConnection (); } 
			else if ( strtoupper ( $this->t ) == 'PUBLICIP' ) { self::getIPPublic(); } 
			else if ( strtoupper ( $this->t ) == 'PORT' ) { self::getPort (); } 
			else if ( strtoupper ( $this->t ) == 'CERT' ) { self::getUserCertExpire ();} 
			else if ( strtoupper ( $this->t ) == 'NETMASK' ) { self::getGatewayServer ('netmask');} 
			else if ( strtoupper ( $this->t ) == 'GATEWAY' ) { self::getGatewayServer ('gateway'); } 
			else if ( strtoupper ( $this->t ) == 'SERVPROCESS' ) { self::getOVPNProcess (); } 
			else if ( strtoupper ( $this->t ) == 'ROUTING' ) { self::getRouteInfo (); }
			else if ( strtoupper ( $this->t ) == 'IP' ) { self::getClientIP (); }
			else if ( strtoupper ( $this->t ) == 'STATUS' ) { self::getConnectStatus (); }
			else if ( strtoupper ( $this->t ) == 'SUCCESS' ) { self::pushEvents ("success"); }
			else if ( strtoupper ( $this->t ) == 'ERROR' ) { self::pushEvents ("error"); }
			else if ( strtoupper ( $this->t ) == 'DSPEVENTS' ) { self::getEvents (); }
			else $this->result['data'] = "Invalid parameter"; }

			return self::uniConvert ();
		}


		/**********************************************************
	 	 * Get client ip address 
		 * Get ip from status.log and fixed ip from sys database
		**********************************************************/
    		private function getClientIP ()
                {
                        $status_data =  file ( $this->config['status'] ) ;
                        $i = 0;
                        while ( $i < count ( $status_data ) ) { /* Process openvpn-status.log */
                                $buffer = explode ("," , $status_data [$i] ) ;
                                if ( count ( $buffer ) == 4) {
                                        if ( strstr( $status_data[$i], $this->c ) ) {
                                                $status_info = explode ("," , $status_data[$i] );
                                                $this->result['data']['id'] = $this->c;
                                                $this->result['data']['private_ip'] = trim($status_info[0]);
                                                $this->result['data']['connect_date'] = trim($status_info[3]);
                                                $this->result['data']['from'] = "status.log";
                                         }
                                }
                                $i++;
                        }

			/* Id not found in openvpn-status.log */
			/* Check fixed ip address from database */			
			if ( ! isset ( $this->result['data']['private_ip'] ) ) 	{
				$dbs = new DB ( $this->config['database'] );
                         	$c = $dbs->query ("SELECT * FROM tbl_vpn_org where org_id = '" . $this->c . "'");
                         	if (  count ( $c ) ) { /* Check org */
					$dbfx = new DB ( $this->config['database'] );		
					$resfx = $dbfx->query ("SELECT * FROM tbl_custom_ip WHERE org_id = '" . $this->c . "'");
					if ( count ( $resfx ) ) { /* Check fixed ip by id */
						$this->result['data']['id'] = $this->c;
						$this->result['data']['ip'] = $resfx['fix_ip'];
						$this->result['data']['note'] = "Fixed ip address";
					} else /* Fixed ip address not found, But id founded in main system */
						$this->result['data']['result'] = "DHCP";
					$dbfx->CloseConnection ();	
				} else { 
					/* Main id not found */
					$this->result['data']['result'] = "Not found [error code:100:101]";	
				}
				$dbs->CloseConnection ();
			}
                        return;
                }


		/*******************************************************
		 * get events record from database 
		 * *****************************************************/
		private function getEvents ()  

		{
			 $dbs = new DB ( $this->config['database'] );
                         $c = $dbs->query ("SELECT * FROM tbl_events_record WHERE org_id = '" . $this->c . "'");
			 if (  count ( $c ) ) {
				$this->result['data']['id'] = trim ( $this->c );
				$this->result['data']['events'] = $c[0]['events'];
				$this->result['data']['date'] = $c[0]['date_rec'];
			 } else 
				$this->result['data']['result'] = "Not found [error code:100:101]";

			 $dbs->CloseConnection ();
			
		  	return;
		}


		/***************************************************************
		 * Get client connection status 
	  	 * Process from openvpn-status.log [openvpn-status.log update every 1 min.]
		 **************************************************************/
		 
		private function getConnectStatus () 
		{	
			$status_data =  file ( $this->config['status'] ) ;
			$i = 0;
			while ( $i < count ( $status_data ) ) {
				$buffer = explode ("," , $status_data [$i] ) ;
				if ( count ( $buffer ) == 4) { /* part private ip and public ip */
					if ( strstr( $status_data[$i], $this->c ) ) {
						$status_info = explode ("," , $status_data[$i] );
						$this->result['data']['id'] = $this->c;
						$this->result['data']['status'] = "Connected";
						$this->result['data']['private_ip'] = trim($status_info[0]);	
						$this->result['data']['public_ip'] = trim($status_info[2]);	
						$this->result['data']['connect_date'] = trim($status_info[3]);
					    }
					}
				$i++;
			} /* Not found in openvpn-status.log */
			if ( ! isset( $this->result['data']['status'] ) ) 
				$this->result['data']['result'] = "Offline [note: status.log refresh every 1 minute.]";
			return;
		}



		/****************************************************
	 	 * Push event by Id
		 * 2 types of events [success] [error]
		 * success - send data success or connection vpn success. 
		 * error - send data or connection vpn error. 
		 ******************************************************/
		private function pushEvents ($e)
		{
			   if ( self::chkOrgId () ) { /* Check main Id */
				 $dbs = new DB ( $this->config['database'] );
				 $c = $dbs->query ("SELECT COUNT(*) AS C FROM tbl_events_record WHERE org_id = '" . $this->c . "'");
				 if ( ! $c[0]['C'] ) { /* Event not found in events table , Insert */ 
					$db_ins = new DB ( $this->config['database'] ) ;
                        	       	$e_ins = $db_ins->query ("INSERT INTO tbl_events_record 
						VALUES('" . $this->c . "','" . $e . "','". date('Y-m-d H:i:s')."');");
					$db_ins->CloseConnection ();
					return true;
				 } else { /* Event found in events table , Update events by Id */
					$db_upd = new DB ( $this->config['database'] ) ;
					$e_upd = $db_upd->query ("UPDATE tbl_events_record 
						SET events = '".$e."', date_rec = '". date('Y-m-d H:i:s') . 
						"' WHERE org_id = '" . $this->c ."'");
					$db_upd->CloseConnection ();
				}
				$dbs->CloseConnection ();
				$this->result['data']['id'] = $this->c;
				$this->result['data']['result'] = "Push event: " . $e; /* Events status */
			   } else  /* Id not found */
				$this->result['data']['result'] = "Id not found [error code:100:101]";

			return false;
		}


		/*************************************************************
		 * Check main data by Id from database 
		 * Result is found and not found [1 or 0]
		 *************************************************************/ 
		private function chkOrgId ()
		{
			/* Check Org Id in main table */
			$dbs = new DB ( $this->config['database'] ) ;
			$c = $dbs->query ("SELECT COUNT (*) AS C FROM tbl_vpn_org WHERE org_id = '" . trim($this->c) . "'");
			$dbs->CloseConnection ();
			
			return $c[0]['C']; /* return 1 or 0 */
		}



		/****************************************************************
	 	 * Get routing information from openvpn server.conf
		 * Process server.conf and get routing by regular expression 
		 ***************************************************************/ 	
		private function getRouteInfo () 
		{
                        $config_data = file( $this->config['server'] );
                        $i=$j=0;
			while ( $i < count ( $config_data ) ) {
				if ( strstr ( $config_data[$i], 'push "route') ) {
					if ( strpos ( $config_data[$i] , "#") === false )  {
						$this->result['data']['routing'][$j] =  trim ( $config_data [$i] );
						$j++;
					}
				}
				$i++;
			}
			return;
		}
	
	

		/*****************************************************************
		 * Get openvpn process 
	 	 * Use pidof command [ref: /bin/pidof]
		 * display running or process not running
		 * Can be multi-openvpn process [Cluster VPN]
		 *****************************************************************/
		private function getOVPNProcess () 
		{
			@exec ('/bin/pidof openvpn',$o,$r ) ; /* pidof command use by exec function */
			if ( ! $r ) {
				$this->result['data']['status'] = "Running";
				$this->result['data']['pid'] = $o[0];
			} else 
				$this->result['data']['result'] = "Not found [error code:200]";
			return ;
		}



		/***************************************************************************
		 * Get server ip or ip gateway 
		 * Parameter : String $t [gateway,netmask] 
		 * Process from openvpn server.conf
		 **************************************************************************/
		private function getGatewayServer ($t)
		{
			$server_config = $this->config['server'];
                        $config_data = file( $this->config['server'] );
                        $i=0;
                        while ( $i < count ( $config_data ) ) {
                                if(strstr ( $config_data [$i] , "255.255" ) ) { /* 255.255 is line of gw/netmask */
                                        $buffer = explode (" " , $config_data[$i] ) ;
					if ( count ( $buffer ) == 3 ) {
					   if ( $t == 'netmask' ) 
						$this->result['data']['netmask'] =  trim($buffer[2]);
					   if ( $t == 'gateway' )  {
						$b = explode ("." , trim($buffer[1]));
						$gw = $b[0].".".$b[1].".".$b[2].".1";
						$this->result['data']['ip_vpn_gateway'] = $gw;
					   }
					}
                                }
                                $i++;
                        }
			return;
		}



		/************************************************************
		 * Get user certificate expire day from database
	 	 * ********************************************************/
		private function getUserCertExpire ()
		{
			$dbs = new DB ( $this->config['database'] );
                        $search = $dbs->query("SELECT * FROM tbl_vpn_cert_expire WHERE org_id = " . trim($this->c)  );
                        if ( count($search) ) {
				$this->result['data']['id'] = $search[0]['org_id'];
				$this->result['data']['certificate_expire'] = $search[0]['org_cert_expire'];
			} else 
				$this->result['data']['result'] = "Not found [error code:100:101]";

			$dbs->CloseConnection ();
			return ;
		}


		/************************************************************
                 * JSON Thai character convert 
                 ************************************************************/
		private function uniConvert ()
		{
			 return preg_replace("/\\\\u([a-f0-9]{4})/e",
                                "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))",
                                json_encode($this->result));
		}


		
		/************************************************************
		 * Get meta data from META.inf  
		 * Merge this meta data in header of json result 
		 ************************************************************/
		private function getMetaData ()
		{
			$data  = file_get_contents ("META.inf");
			$o = json_decode ($data,true);
			return $o;	
		}

		/************************************************************
		 * Get port number info from openvpn server.conf
		 ************************************************************/
		private function getPort ()
		{
			$server_config = $this->config['server'];
			$config_data = file( $this->config['server'] );
			$i=0;
			while ( $i < count ( $config_data ) ) {
				if(strstr ( $config_data [$i] , "port" ) ) { /* line of port in server.conf */
					$buffer = explode (" " , $config_data[$i] ) ;
					if ( count ( $buffer ) == 2 ) 
						$this->result['data']['port'] = trim($buffer[1]);
				}
				$i++;
			}
			return;
		}		


		/************************************************************
		 * Get organization name by ID from database  
		 ************************************************************/
		private function getName  ()
		{	
			$dbs = new DB ( $this->config['database'] );
			$search = $dbs->query("SELECT * FROM tbl_vpn_org WHERE org_id = " . trim($this->c)  );
			if ( count($search) ) {
				// Convert result to human code 
				$this->result['data']['id'] = $search[0]['org_id'];
				$this->result['data']['name'] = $search[0]['org_name'];
				$this->result['data']['description'] = $search[0]['org_desc'];
			} else 
				$this->result['data']['result'] = "Not found";
		
			$dbs->CloseConnection ();	
			return;
		}
		

		/************************************************************
		 * Get last connection info by Id from database 
		 ************************************************************/
		private function getLastConnection ()
		{
			$dbs = new DB ( $this->config['database'] );
			$search = $dbs->query("SELECT private_rec_date FROM tbl_client_connection_priv WHERE 
				priv_client_id LIKE '". $this->c ."%' order by private_rec_date desc limit 1");
			if ( count ( $search ) ) {
				$this->result['id'] = $this->c;
				$this->result['last_connection'] = $search[0]['private_rec_date'];
			} else 
				$this->result['data']['result'] = $this->c . ":No usage history found";
			
			$dbs->CloseConnection ();
			return;
		}
		
		 /************************************************************
                 * Get client public ip from database
                 ************************************************************/
                private function getIPPublic ()
                {
                        $dbs = new DB ( $this->config['database'] );
                        $search = $dbs->query("SELECT * FROM tbl_client_connection_priv WHERE
                                priv_client_id LIKE '". $this->c ."%' order by private_rec_date desc limit 1");
			if ( count ( $search ) ) {
				$this->result['id'] = $this->c;
				$this->result['real_ip_address'] = $search[0]['real_client_ip'];
			} else
				$this->result['data']['result'] = $this->c . ":No usage history found";
			
			$dbs->CloseConnection ();
			return;
		}
	}
?>
