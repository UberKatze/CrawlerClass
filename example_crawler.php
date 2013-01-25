<?php

$script_location=realpath(dirname(__FILE__));

require_once('crawler/example_crawler.class.php');
require_once('config/database_config.php');

$Crawler=new TestCrawler($script_location."/logs",$script_location."/cookies");

$Crawler->DBConnect($DB_HOST,$DB_NAME,$DB_USERNAME,$DB_PASSWORD);		

$Crawler->SetSiteAddress('http://www.google.com');

$Crawler->SetUseProxy(false);

//crawl main page
$Crawler->CrawlPage($Crawler->GetSiteRoot());

if ($Crawler->PageContent!=''){
	$Crawler->CrawlPage($Crawler->GetSiteRoot()."/search?q=test");
	$links=$Crawler->parseLinks();
	
	foreach ($links as $link) {
		$Crawler->Log($link);
	}
}



?>
