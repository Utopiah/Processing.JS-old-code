<?php if (!defined('PmWiki')) exit();

## give a visual representation of pages and group
$RecipeInfo['GraphCSV']['Version'] = '2010-04-11';

SDV($HandleActions['graphcsv'],'GraphCSV');
SDV($HandleAuth['graphcsv'],'view');

function GraphCSV($pagename, $auth){
	$sourcename = preg_replace("/.*\.(.*)/","$1",$pagename);
	$groupname = preg_replace("/(.*)\..*/","$1",$pagename);
	global $FarmD, $WikiTitle, $ScriptUrl;

	//entire wiki
	if ($pagename == "Main.HomePage")
		$pages = ListPages("//e");
	else
		$pages = ListPages("/$groupname\./e");

	//$pages = MatchPageNames($pages,array('-*.RecentChanges,-*.GroupFooter,-*.GroupHeader'));
	$pagesnum = count($pages);

	$nodes = "Id,Group,Label,Size,Time,Rev\n";
	$edges = "Source,Target\n";
	# scan over the array of pages to get mazimum and generate axis...
	foreach ($pages as $page) {
		$cleanpage = preg_replace("/.*\.(.*)/","$1",$page);
		$content = ReadPage($page,$since=0);
		$size = strlen($content["text"]);
		$pagetime = $content["time"];
		$pagerev = $content["rev"];
		$group = preg_replace("/(.*)\..*/","$1",$page);
		$nodes .= "$page,$group,$cleanpage,$size,$pagetime,$pagerev\n";
		$page_category = preg_replace("/.*\[\[\!(.*)\]\].*/s","$1",$content["text"]);
		$author = $content["author"];
		$links = $content["targets"];
		$links_array = explode(",",$links);
		foreach ($links_array as $link) {
			if (($linkindex = array_search($link,$pages)) !== false )
			{
				$cleanlink = preg_replace("/.*\.(.*)/","$1",$link);
				$edges .= "$page,$link\n";
			}
		}
	}
	// ----------------------- return the processed result -----------------------
	print "$nodes<hr/>$edges";

	$nodesfile = $FarmD."/pub/nodes.csv";
	if (!file_exists($nodesfile))
		if (!touch($nodesfile))
			print "Creation of the processing file fails, check write permissions for pmWiki and $nodesfile.";
	$write_result = file_put_contents($nodesfile,$nodes);
	if (!$write_result)
		if (strlen($nodes)>0)
			print "Creation of the feed file fails, check write permissions for pmWiki and $nodesfile.";
		else
			print "No code to generate, did you correctly generate your Processing code?";

	$edgesfile = $FarmD."/pub/edges.csv";
	if (!file_exists($edgesfile))
		if (!touch($edgesfile))
			print "Creation of the processing file fails, check write permissions for pmWiki and $edgesfile.";
	$write_result = file_put_contents($edgesfile,$edges);
	if (!$write_result)
		if (strlen($edges)>0)
			print "Creation of the feed file fails, check write permissions for pmWiki and $edgesfile.";
		else
			print "No code to generate, did you correctly generate your Processing code?";


	return ;
}

SDV($HandleActions['graphvan'],'GraphVAN');
SDV($HandleAuth['graphvan'],'view');

function GraphVAN($pagename, $auth){
	$sourcename = preg_replace("/.*\.(.*)/","$1",$pagename);
	$groupname = preg_replace("/(.*)\..*/","$1",$pagename);
	global $FarmD, $WikiTitle, $ScriptUrl;

	//entire wiki
	if ($pagename == "Main.HomePage")
		$pages = ListPages("//e");
	else
		$pages = ListPages("/$groupname\./e");

	//$pages = MatchPageNames($pages,array('-*.RecentChanges,-*.GroupFooter,-*.GroupHeader'));
	$pagesnum = count($pages);

	$vangraph = "*Node data\nId Group Label Size Time Rev\n";
	$vangraphties = "*Tie data\nFROM TO\n";
	# scan over the array of pages to get mazimum and generate axis...
	foreach ($pages as $page) {
		$cleanpage = preg_replace("/.*\.(.*)/","$1",$page);
		$content = ReadPage($page,$since=0);
		$size = strlen($content["text"]);
		$pagetime = $content["time"];
		$pagerev = $content["rev"];
		$group = preg_replace("/(.*)\..*/","$1",$page);
		$vangraph .= "$page $group $cleanpage $size $pagetime $pagerev\n";
		$page_category = preg_replace("/.*\[\[\!(.*)\]\].*/s","$1",$content["text"]);
		$author = $content["author"];
		$links = $content["targets"];
		$links_array = explode(",",$links);
		foreach ($links_array as $link) {
			if (($linkindex = array_search($link,$pages)) !== false )
			{
				$cleanlink = preg_replace("/.*\.(.*)/","$1",$link);
				$vangraphties .= "$page $link\n";
			}
		}
	}
	// ----------------------- return the processed result -----------------------
	print "$vangraph<hr />$vangraphties";

	$vanfile = $FarmD."/pub/graph.van";
	if (!file_exists($vanfile))
		if (!touch($vanfile))
			print "Creation of the processing file fails, check write permissions for pmWiki and $vanfile.";
	$write_result = file_put_contents($vanfile,$vangraph.$vangraphties);
	if (!$write_result)
		if (strlen($vangraph)>0)
			print "Creation of the feed file fails, check write permissions for pmWiki and $vanfile.";
		else
			print "No code to generate, did you correctly generate your Processing code?";

	return ;
}
