<?php
/**
* Crawler class
* @author Eugene Kosarev 
*/
/* Copyright © 2013 Eugene Kosarev <euko@ukr.net>
	*This work is free. You can redistribute it and/or modify it under the
	* terms of the Do What The Fuck You Want To Public License, Version 2,
	* as published by Sam Hocevar. See the COPYING file for more details.
*/
class SiteCrawler{
	
	//--------------------------------------------------------------- Variables ----------------------------------------------------------------
	// Link to database
	protected $DBLink;
	
	// Directory for logs								
	protected $LogPath;
	
	// Log file
	protected $LogFileMain;
	
	// Cookie file
	protected $CookieFileName;
	
	// Use Cookie Flag 
	protected $UseCookie=true;
		
	// Directory for cookies								
	protected $CookiePath;
	
	// Path where crawler script is
	protected $CrawlerPath;
	
	// root URL of site to crawl
	protected $CrawlSiteAddress;
	
	// Table of threads
	protected $ThreadsTable;
	
	// Thread id
	protected $ThreadID;
	
	// Thread type
	protected $ThreadType;
	
	// Use threads flag
	protected $UseThreads=false;
	
	// Max iteratively bad tries to curl
	protected $MaxRepeatedlyCurlBadTries=5;
	
	// Use tries to curl flag
	protected $ReCrawl=false;	
	
	// Proxy for curl
	protected $Proxy;
	
	// Curl timeout in seconds
	protected $CurlTimeout=30;
	
	// Browser
	protected $CurlUserAgent;
	
	// Header
	protected $CurlHeader= array("Accept-Language: en-us,en;q=0.5",	"Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7","Keep-Alive: 300",	"Connection: keep-alive");
	
	// Curl Maximum redirections 
	protected $CurlMaxRedirect=10;

	// Curl Auto referer
	protected $CurlAutoReferer=true;
	
	// Curl follow location
	protected $CurlFollowLocation=true;
		
	// URL of previous page (for referer header)
	protected $PrevPage='';
	
	// Good Footer
	protected $GoodTag=false;

	// Sleep options (in seconds) between crawling requests for stealth
	protected $MinSleep=0;
	protected $MaxSleep=0;		
	
	// Pulse Interval in minutes (max time interval beetween IamAlive)
	protected $NoPulseInterval=10;	
	
	// Use proxy flag
	protected $UseProxy=false;
	
	// Max time response for proxies in seconds
	protected $ProxyMaxTimeResponse=5;
	
	protected $DBConnection=false;

	// Crawled page content
	public $PageContent='';
	
	//------------------------------------------------------------------ Functions ----------------------------------------------------------------------
	// Constructor
	function __construct($LogPath,$CookiePath){
		
		$this->CrawlerPath=realpath(dirname(__FILE__));
		if ($LogPath==false) $this->LogPath=$this->CrawlerPath;
		else $this->LogPath=$LogPath;
		if ($CookiePath==false) $this->CookiePath=$this->CrawlerPath;
		else $this->CookiePath=$CookiePath;
		
		$this->CookieFileName='cookie';
		
		$this->SetRandomBrowser();
		
		$this->LogFileMain="crawler_log_".date("d.m.Y").".txt";
	}
	
	// Destructor (End crawling)
	function __destruct(){		
		if ($this->ThreadID!=false) {
        	$this->FreeThread();
		}
		$this->DeleteCookies();		
	}
	
	public function setThreads($ThreadsTable,$ThreadType,$NoPulseInterval){
		$this->UseThreads=true;
		
		$this->ThreadsTable=$ThreadsTable;
		$this->NoPulseInterval=$NoPulseInterval;
		
		$this->GetThread($ThreadType);
		
		$this->LogFileMain="crawler_log_th".$this->ThreadID."_".date("d.m.Y").".txt";
	}
	
	// Set root URL of site
	public function SetSiteAddress($SiteAddress){
		$this->CrawlSiteAddress=$SiteAddress;
	}
	
	// Get root URL
	public function GetSiteRoot(){
		return $this->CrawlSiteAddress;
	}
	
	// Set UseCookie Flag
	public function SetUseCookie($UseCookie){
		if ($UseCookie==false) $this->UseCookie=false;
		else $this->UseCookie=true;
	}
	
	// Set Use Proxy Flag
	public function SetUseProxy($UseProxy){
		if ($UseProxy==false) $this->UseProxy=false;
		else $this->UseProxy=true;
	}
	
	// Set Recrawl Flag
	public function SetReCrawl($ReCrawl){
		if ($ReCrawl==false) $this->ReCrawl=false;
		else $this->ReCrawl=true;
	}
	
	// Set Crawl options: Header, Timeout, Curl Max Redirections  etc.
	public function SetCrawlOptions($CurlHeader,$CurlTimeout,$CurlMaxRedirect,$CurlAutoReferer, $CurlFollowLocation, $CurlUserAgent, $MinSleep,$MaxSleep){
		$this->CurlHeader=$CurlHeader;
		if (is_numeric($CurlTimeout)) $this->CurlTimeout=$CurlTimeout;
		if (is_numeric($CurlMaxRedirect)) $this->CurlMaxRedirect=$CurlMaxRedirect;				
		if (is_numeric($MaxSleep)) $this->MaxSleep=$MaxSleep;
		if (is_numeric($MinSleep)) $this->MinSleep=$MinSleep;
		
		if ($CurlAutoReferer==false) $this->CurlAutoReferer=false;
		else $this->CurlAutoReferer=true;
		
		if ($CurlFollowLocation==false) $this->CurlFollowLocation=false;
		else $this->CurlFollowLocatione=true;
		
		$this->CurlUserAgent=$CurlUserAgent;
	}
	
	// Set good tag (footer)
	public function SetGoodTag($GoodTag){
		$this->GoodTag=$GoodTag;
	}
	
	// Crawl page
	public function CrawlPage($URL,$POST=false,$GET=false,$goodtag=false){
		// Use object's goodtag by default
		if ($goodtag==false) $goodtag=$this->GoodTag;
						
		if (!strstr($URL,'http://') && !strstr($URL,'https://')) {
			if ($URL{0}!='/') $URL="/".$URL;
			$URL=trim($this->CrawlSiteAddress,'/').$URL;
		}
		
		if ($GET!=false) {
			if (strstr($URL,'?')) $FULLURL=$URL.'&'.$GET;
			else $FULLURL=$URL.'?'.$GET;
		}
		else $FULLURL=$URL;
				
		
		$server = parse_url($FULLURL);
		
		if (!isset($server['port'])) {
			$server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
		}		
		if (!isset($server['path'])) {
			$server['path'] = '/';
		}		
		if (isset($server['user']) && isset($server['pass'])) {
			$this->CurlHeader[] = 'Authorization: Basic ' . base64_encode($server['user'] . ':' . $server['pass']);
		}	
					
		$tries=0;
		do 	{						
			$this->IamAlive();
			
			//Curl Init
			$ch = @curl_init($server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : ''));
			
			sleep(rand($this->MinSleep,$this->MaxSleep));
			
			if ($this->Proxy!='' && $this->UseProxy==true) @curl_setopt($ch, CURLOPT_PROXY, $this->Proxy);
			
			if ($tries>=1) {
				$this->Log(" ==== Could not get good page '$FULLURL' (try: #$tries) ====\r\n");
				if ($this->ReCrawl==false) {				
					break; //NB! No repeats if flag ReCrawl is false
				}
			}			
			if ($this->ReCrawl==true) {
				if ($tries==$this->MaxRepeatedlyCurlBadTries) {
					// Shut down - if maximum tries reached
					$this->FreeThread($this->ThreadID);			
					$this->Log(" ==== SHUT DOWN! Maximum number of tries to get good page ($FULLURL) reached! ====\r\n");				
					die();		
				}
				if ($tries>=1) {	
					//change proxy
					$this->ChangeProxy();								
					$this->Log(" ==== Proxy changed to '$this->Proxy' ====\r\n");
				}
			}													

			/*Set curl options and exec it*/
			
			curl_setopt($ch, CURLOPT_HTTPHEADER,	$this->CurlHeader);
			curl_setopt($ch, CURLOPT_USERAGENT,		$this->CurlUserAgent);
			curl_setopt($ch, CURLOPT_TIMEOUT,		$this->CurlTimeout);
			curl_setopt($ch, CURLOPT_URL, 			$FULLURL);			
			if ($this->UseCookie==true) {
				curl_setopt($ch, CURLOPT_COOKIEJAR,$this->CookiePath.'/'.$this->CookieFileName.$this->ThreadID.".txt");
				curl_setopt($ch, CURLOPT_COOKIEFILE,$this->CookiePath.'/'.$this->CookieFileName.$this->ThreadID.".txt");
			}			
			if ($POST!=false) {
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $POST);						
			}							
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_AUTOREFERER, $this->CurlAutoReferer);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->CurlFollowLocation);
		  	curl_setopt($ch, CURLOPT_MAXREDIRS, $this->CurlMaxRedirect);		
			$raw=$this->curl_exec_utf8($ch);				
			$error=curl_error($ch);
			/*-----------------------------*/
			
			//Curl is Bad
			if ($error!=''){				
				$this->Log("Curl error accured.");								
				$this->Log("URL: '".$FULLURL."', POST: '".$POST."', Proxy: '". $this->Proxy."'");		
				$this->Log($error);
				$this->PageContent='';
			}
			//Curl is Good
			else {
				$this->PageContent=$raw;
				$this->PrevPage=$FULLURL;
			}
						
			curl_close($ch);
					
			$tries++;			
		} while (!$this->PageGood($this->PageContent,$goodtag));
	}
	
	// Set referer
	public function SetReferer($referer){
		$this->PrevPage=$referer;
	}
			
	// Logging function		
	public function Log($string,$echo=true,$filename=false,$timeformat="d.m.Y H:i:s",$microsec=false,$delimiter1="\t",$delimiter2="\r\n"){
		$this->IamAlive();
		if ($filename==false) $filename=$this->LogFileMain;
		$filename=$this->LogPath.'/'.$filename;
		
		if ($microsec==true){
			list($usec, $sec) = explode(" ", microtime());
			$usec=substr($usec,2);
		}
		else $usec='';
		
		$filecontent=date($timeformat).$usec.$delimiter1;
		$filecontent.=$string;
		$filecontent.=$delimiter2;
		
		if ($echo==true) print $filecontent." <br>\r\n";
		
		if (!$file=fopen($filename,"a")){			
			return false;
		}
		else{										
			if (fwrite($file, $filecontent) === FALSE) {				
				fclose($file);
				return false;	
		    }
		}		
		return true;
	}	
	
	// Delete cookie files
	public function DeleteCookies(){
		if ($this->UseCookie==true && file_exists($this->CookiePath.'/'.$this->CookieFileName.$this->ThreadID.".txt")) {
			unlink($this->CookiePath.'/'.$this->CookieFileName.$this->ThreadID.".txt");				
		}
	}
		
	// Pulse
	protected function IamAlive(){				
		if (file_exists($this->CrawlerPath."/killme.txt")){		
			$this->FreeThread();
			$this->Log(" ==== Killed! ====\r\n");			
			die();
		}
		
		if ($this->UseThreads==false) return;
		
		$sql="UPDATE `$this->ThreadsTable` SET pulse=now() WHERE id=$this->ThreadID";
		$this->MySqlQuery($sql);
	}
	
	// String function. Useful for parsing
	public function getElementString($string_to_search,$string_start,$string_end) {
				
		if (mb_strpos($string_to_search,$string_start,false,'UTF-8')===false) return false;
		if (mb_strpos($string_to_search,$string_end,false,'UTF-8')===false)	return false;

		$start=mb_strpos($string_to_search,$string_start,false,'UTF-8')+mb_strlen($string_start,'UTF-8');
		$end=mb_strpos($string_to_search,$string_end,$start,'UTF-8');

		$return=mb_substr($string_to_search,$start,$end-$start,'UTF-8');

		return $return;	
	}
	
	// String function. Useful for parsing
	public function tagStrip($total,$start, $end){
		$total = mb_stristr($total, $start,false,'UTF-8');
		$f2 = mb_stristr($total, $end,false,'UTF-8');
		return mb_substr($total,mb_strlen($start,'UTF-8'),-mb_strlen($f2,'UTF-8'),'UTF-8');
	} 	
	
	// Query function with error logging
	protected function MySqlQuery($sql){		
		if (($result=mysql_query($sql,$this->DBLink))===false) {
				$error=mysql_error($this->DBLink);				
				$this->Log("MySql Query error accured.");				
				$this->Log("Error: ". $error);
				$this->Log("Query: ". $sql."\r\n");
				return false;
		}
		else return $result;
	}
	
	// Get Thread function
	protected function GetThread($ThreadType){	
		if ($this->DBLink==false) die("FATAL: No DB Connection");
		$sql="SELECT MIN(id) as `id` FROM `$this->ThreadsTable` WHERE (pulse< (now() - INTERVAL $this->NoPulseInterval MINUTE) OR pulse IS NULL) AND thread_type='$ThreadType'";	
		$result=mysql_query($sql) or die("FATAL: SQL Error: ".mysql_error($this->DBLink)." Query: ".$sql);
		$res=mysql_fetch_assoc($result);
		
		//no free thread	
		if ($res['id']=='') die();
		//get thread
		else {			
			$this->ThreadID=$res['id'];
			$this->ThreadType=$ThreadType;
			$this->IamAlive();
		}
	}

	// Free Thread function
	protected function FreeThread(){
		if ($this->UseThreads==false) return;
		
		$sql="UPDATE `$this->ThreadsTable` SET pulse=null WHERE id=$this->ThreadID";
		$this->MySqlQuery($sql);
	}
	
	// Set Proxy function
	public function SetProxy($proxy){
		$this->IamAlive();
		if ($this->UseProxy==true) {
			if ($proxy!='') $this->Proxy=$proxy;
		}
		else {
			$this->Proxy='';
		}		
	}
	
	// function for inheration
	protected function ChangeProxy(){
		if ($this->UseProxy!=true) {
			$this->Proxy='';
			return;
		}
	}
	
	// Set Browser function
	public function SetRandomBrowser(){
		$this->IamAlive();
		$random = (rand()%10);
		switch ($random) {
	    	case 0:     
	        	$browser="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.9) Gecko/2008052906 Firefox/3.0";
	            break;	            
	        case 1:
	        	$browser="Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727)";        
	         	break;	
	        case 2:
	        	$browser="Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)";
	            break;	
	        case 3:
	            $browser="Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)";
	        	break;	        
	        case 4:	
	        	$browser="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6";
				break;				
	        case 5:
	        	$browser="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13";
				break;
	        case 6:
	        	$browser="Mozilla/5.0 (Macintosh; U; Intel Mac OS X; en; rv:1.8.1.14) Gecko/20080409 Camino/1.6 (like Firefox/2.0.0.14)";
	        	break;
	        case 7:
	        	$browser="Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.10 (KHTML, like Gecko) Chrome/8.0.552.237 Safari/534.10";
	        	break;
	        case 8:
	        	$browser="Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; SV1; Maxthon; .NET CLR 1.1.4322)";
	        	break;
	        case 9:
	        	$browser="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/534.3 (KHTML, like Gecko) Chrome/6.0.472.63 Safari/534.3";
	        	break;
	    }
		
	    $this->CurlUserAgent=$browser;
	}	
	
	// Connect to DB
	public function DBConnect($DB_Host,$DB_Name,$DB_Username,$DB_Password) {
		$this->DBLink = mysql_connect($DB_Host, $DB_Username, $DB_Password);
		if ($this->DBLink==false) die("FATAL: Could not connect to DB '$DB_Host'");
		if (!mysql_select_db($DB_Name)) die("FATAL: Could select DB '$DB_Name'");
		
	}
	
	// Function checks good page or not (by footer or another tag in text)
	protected function PageGood($raw,$goodtag){
		$this->IamAlive();
		if ($goodtag=='') return true;
		if (mb_strstr($raw,$goodtag,false,'UTF-8')) return true; // right footer => page loaded successfully
		return false; // page is wrong or not fully loaded
	}

	/** The same as curl_exec except tries its best to convert the output to utf8 **/
	protected function curl_exec_utf8($ch) {
	    $data = curl_exec($ch);
	    if (!is_string($data)) return $data;
	
	    unset($charset);
	    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	
	    /* 1: HTTP Content-Type: header */
	    preg_match( '@([\w/+]+)(;\s*charset=(\S+))?@i', $content_type, $matches );
	    if ( isset( $matches[3] ) )
	        $charset = $matches[3];
	
	    /* 2: <meta> element in the page */
	    if (!isset($charset)) {
	        preg_match( '@<meta\s+http-equiv="Content-Type"\s+content="([\w/]+)(;\s*charset=([^\s"]+))?@i', $data, $matches );
	        if ( isset( $matches[3] ) )
	            $charset = $matches[3];
	    }
	
	    /* 3: <xml> element in the page */
	    if (!isset($charset)) {
	        preg_match( '@<\?xml.+encoding="([^\s"]+)@si', $data, $matches );
	        if ( isset( $matches[1] ) )
	            $charset = $matches[1];
	    }
	
	    /* 4: PHP's heuristic detection */
	    if (!isset($charset)) {
	        $encoding = mb_detect_encoding($data);
	        if ($encoding)
	            $charset = $encoding;
	    }
	
	    /* 5: Default for HTML */
	    if (!isset($charset)) {
	        if (strstr($content_type, "text/html") === 0)
	            $charset = "ISO 8859-1";
	    }
	
	    /* Convert it if it is anything but UTF-8 */
	    /* You can change "UTF-8"  to "UTF-8//IGNORE" to 
	       ignore conversion errors and still output something reasonable */
	    if (isset($charset) && strtoupper($charset) != "UTF-8")
	        $data = iconv($charset, 'UTF-8//IGNORE', $data);
	
	    return $data;
	}
}

?>