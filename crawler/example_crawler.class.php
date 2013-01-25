<?php

require_once("crawler.class.php");

class TestCrawler extends SiteCrawler{
	function __construct($LogPath,$CookiePath){
		parent::__construct($LogPath,$CookiePath);
					
		$this->LogFileMain="test_crawler_log_".date("d.m.Y").".txt";
		$this->CookieFileName='test_cookie';
			
		$this->Log("================= Crawler work start ==================");	
	}
	
	function __destruct(){
		$this->Log("================= Crawler work end ====================\r\n");
		parent::__destruct();		
	}
	
	function parseLinks(){		
		$links=array();
		$search_div=$this->getElementString($this->PageContent,'<div id="ires">','</ol>');
		$raw_arr=explode('</li>',$search_div);		
		array_pop($raw_arr);
				
		foreach ($raw_arr as $raw_element){
			
			$link=$this->getElementString($this->getElementString($raw_element,'<h3','</h3>'),'href="','"');
			if (mb_strpos($link,'url?q=',false,'UTF-8')) {
				$link=$this->getElementString($link,'url?q=','&');
			}
			$links[]=$link;
			
		}
		
		return $links;
	}
	
}

?>