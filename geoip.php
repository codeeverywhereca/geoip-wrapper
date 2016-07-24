<?php
	/*
	* geoip-wrapper (July 23 2016)
	* https://github.com/codeeverywhereca/geoip-wrapper
	* For use with http://dev.maxmind.com/geoip/geoip2/geolite2/
	* Copyright 2016, http://codeeverywhere.ca
	* Licensed under the MIT license.
	*/
	
	class geoip {
		private $db = null;
		private $blocks_ip4 = "GeoLite2-City-Blocks-IPv4.csv";
		private $blocks_ip6 = "GeoLite2-City-Blocks-IPv6.csv";
		private $locations = "GeoLite2-City-Locations-en.csv";
		private $continent_map = array();
		private $country_map = array();
		
		function __construct() {
			$this->db = new PDO("sqlite:geoip.db"); // realative path...
		}
		
		// From stackoverflow
		private function cidrToRange($cidr) {
			$range = array();
			$cidr = explode('/', $cidr);
			$range[0] = long2ip((ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1]))));
			$range[1] = long2ip((ip2long($cidr[0])) + pow(2, (32 - (int)$cidr[1])) - 1);
			return $range;
		}
		
		public function build() {
			echo "Building database ...\n";
			
			echo "Creating 'GeoLite2_City_Blocks_IPv4' table ...\n";
			$this->db->query("create table GeoLite2_City_Blocks_IPv4 ( start integer, end integer, geoname_id integer, 
				is_anonymous_proxy integer, is_satellite_provider integer, latitude real, longitude real )");
			$this->db->query("create index ip4index on GeoLite2_City_Blocks_IPv4(end);");
			
			echo "Creating 'GeoLite2_City_Locations_en' table ...\n";
			$this->db->query("create table GeoLite2_City_Locations_en ( geoname_id integer primary key, locale_code text, 
				continent_code text, country_iso_code text, city_name text, time_zone text )");
			$this->db->query("create index locindex on GeoLite2_City_Locations_en(geoname_id);");
						
			$row = 1;
			if( ( $handle = fopen($this->blocks_ip4, "r") ) !== FALSE ) {
				fgetcsv($handle, 1000, ","); //skip line 1
				$this->db->beginTransaction();
				$stmt = $this->db->prepare("insert into GeoLite2_City_Blocks_IPv4 values ( ?, ?, ?, ?, ?, ?, ? );");
				while( ( $data = fgetcsv($handle, 1000, ",") ) !== FALSE ) {
					$range = $this->cidrToRange($data[0]);
					try {
						$stmt->execute(array( ip2long($range[0]), ip2long($range[1]), $data[1], $data[4], $data[5], $data[7], $data[8] ));
					} catch(PDOException $e) {
						echo $e->getMessage();
					}					
					if( $row % 75000 == 0 ) echo "... Importing '{$this->blocks_ip4}' data: " . floor( $row * 100 / 3180000 ) . "% @$row\r";
					$row++;			
				}
				$this->db->commit();
				fclose($handle);
				echo "{$this->blocks_ip4} Done!\n\n";
			}
						
			$row = 1;
			if( ( $handle = fopen($this->locations, "r") ) !== FALSE ) {
				fgetcsv($handle, 1000, ","); //skip line 1
				$this->db->beginTransaction();
				$stmt = $this->db->prepare("insert into GeoLite2_City_Locations_en values ( ?, ?, ?, ?, ?, ? );");
				while( ( $data = fgetcsv($handle, 1000, ",") ) !== FALSE ) {
					$range = $this->cidrToRange($data[0]);

					$this->continent_map[ $data[2] ] = $data[3];					
					$this->country_map[ $data[4] ] = $data[5];										
					try {
						$stmt->execute( array( $data[0], $data[1], $data[2], $data[4], $data[10], $data[12] ) );
					} catch(PDOException $e) {
						echo $e->getMessage();
					}
					if( $row % 5000 == 0 ) echo "... Importing '{$this->locations}' data: " . floor( $row * 100 / 95000 ) . "% @$row\r";
					$row++;			
				}
				$this->db->commit();
				fclose($handle);
				echo "{$this->locations} Done!\n\n";
			}
			
			echo "Writing 'continents.json' file to disk ...\n";
			asort($this->continent_map);
			file_put_contents("continents.json", json_encode($this->continent_map, JSON_PRETTY_PRINT) );

			echo "Writing 'countries.json' file to disk ...\n";
			asort($this->country_map);
			file_put_contents("countries.json", json_encode($this->country_map, JSON_PRETTY_PRINT) );
		}
		
		public function testRunner() {
			echo "Running tests ...\n... Running performance test on 1000 IPs\n";
			
			//create fake IPs
			$ips = array();
			for( $x=0; $x < 1000; $x++ ) {
				$ips[$x] = rand(1, 255) .'.'. rand(1, 255) .'.'. rand(1, 255) .'.'. rand(1, 255);
			}
			
			$startTime = microtime(true);
			
			//run lookup
			for( $x=0; $x < 1000; $x++ ) {
				$loc = $this->get( $ips[$x] );
			}

			echo "Test completed, took " . ( microtime(true) - $startTime ) . " s ...\n\n";
						
			echo "Running Query consistency test (testing of SQL 'between x and y' vs '<= x') ...\n";
		
			$stmt1 = $this->db->prepare("select * from GeoLite2_City_Blocks_IPv4 t1 inner join 
				GeoLite2_City_Locations_en t2 on t1.geoname_id = t2.geoname_id where ? between start and end;");
			
			$stmt2 = $this->db->prepare("select * from GeoLite2_City_Blocks_IPv4 t1 inner join 
				GeoLite2_City_Locations_en t2 on t1.geoname_id = t2.geoname_id where ? <= end limit 1;");
			
			$startTime = microtime(true);
			
			for( $x=0; $x < 1000; $x++ ) {
				$ip = ip2long($ips[$x]);
				
				$stmt1->bindParam(1, $ip);
				$stmt2->bindParam(1, $ip);
				
				$stmt1->execute();
				$stmt2->execute();
				
				$result1 = $stmt1->fetch(PDO::FETCH_ASSOC);
				$result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
				
				if( $result1['geoname_id'] != $result2['geoname_id'] ) {
					echo "\tMismatch! on ip= {$ips[$x]} - loc= <{$result1['geoname_id']}> <{$result2['geoname_id']}>\n";
				}
			}
			
			echo "Consistency Test completed, took " . ( microtime(true) - $startTime ) . " s ...\n\n";
		}
		
		
		public function get($ip) {
			$stmt = $this->db->prepare("select * from GeoLite2_City_Blocks_IPv4 t1 inner join 
				GeoLite2_City_Locations_en t2 on t1.geoname_id = t2.geoname_id where ? <= end limit 1;");
			$ip = ip2long($ip);
			
			try {
				$stmt->bindParam(1, $ip);
				$stmt->execute();
				$result = $stmt->fetch(PDO::FETCH_ASSOC);
				
				if( $result['city_name'] == '' ) $result['city_name'] = 'unknown';
				
				return $result;
			} catch(PDOException $e) {
				echo $e->getMessage();
				return false;
			}
		}
		
		public function loc($geoname_id) {
			$stmt = $this->db->prepare("select * from GeoLite2_City_Blocks_IPv4 t1 inner join
				GeoLite2_City_Locations_en t2 on t1.geoname_id = t2.geoname_id where t1.geoname_id = ?;");
			
			try {
				$stmt->bindParam(1, $geoname_id);
				$stmt->execute();
				$result = $stmt->fetch(PDO::FETCH_ASSOC);
				if( $result['city_name'] == '' ) $result['city_name'] = 'unknown';
				return $result;
			} catch(PDOException $e) {
				echo $e->getMessage();
				return false;
			}
		}
	}
?>
