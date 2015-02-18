<?php

## give a visual representation of pages and group
$RecipeInfo['GraphProcessing']['Version'] = '2010-04-11';
Markup('GraphProcessing', 'directives', '/\\(:GraphProcessing:\\)/e', Keep(GraphProcessing()));
 
function GraphProcessing(){
	$sourcepagename =  $_SERVER["REQUEST_URI"];
	// apply changes done to graphprocessing.php to insure proper URI format
	$sourcepagename = preg_replace("/(.*)\?.*/","$1",$sourcepagename);
	$sourcename = preg_replace("/.*[\.\/](.*)/","$1",$sourcepagename);
	$sourcegroup = preg_replace("/\/(.*)[\/\.].*/","$1",$sourcepagename);
	if ($sourcegroup == "/"){
		$sourcegroup = "Main";
		# consider instead filling the pages array with 
		## groups names
		## "neighboors" wikis from InterWiki page
		# list page, remove pagenames, sort|uniq ... then what? maybe whole different process
	}

	global $FarmD, $WikiTitle, $ScriptUrl;
	$processingpath = '/pub/processing.js';
	$processingfile = "/pub/processingjsgraph/$sourcegroup.pjs";
	$processinglib = "<script src=\"$processingpath\" type=\"text/javascript\"></script>";

	# eventually modify to use a dedicated folder
	# generate a file per page
	if (!file_exists($FarmD.$processingfile))
		if (!touch($FarmD.$processingfile))
			print "Creation of the processing file fails, check write permissions for pmWiki and $processingfile.";

	# cached version (to comment during tests)
 	#if ( (time() - filemtime($FarmD.$processingfile)) < 3600 ) return $processinglib.'<canvas data-src="'.$processingfile.'" width="'.$canvaswidth.'" height="'.$canvasheight.'"></canvas>';

	$canvaswidth=500;
	$canvasheight=150;

	$groupname = $sourcegroup;
	$pages = ListPages("/$groupname\./e");
	$pages = MatchPageNames($pages,array('-*.*RecentChanges,-*.GroupFooter,-*.GroupHeader'));
	$pagesnum = count($pages);
	$processingsetupcode = "
float[][] e = new float[$pagesnum][3];
void setup(){
  frameRate(15);
  size($canvaswidth,$canvasheight);
  strokeWeight(1);
";
	$processingdrawcode .= "
void draw(){
    background(255);
    for (int j=0;j<$pagesnum;j++){
      noStroke();
      fill(64,128,187,100);
      ellipse(e[j][0],e[j][1],e[j][2],e[j][2]);
    }
";
	# associate a color per group name

	$processinghandlinglinkscode = '';

	$processinghandlinglinkscode .= 'Object l={';
	$processinglinkcode = "  stroke(64,128,187,100);\n";
	$pageindex=0;
	# scan over the array of pages to get mazimum and generate axis...
		# use min() and max() to get limits
        foreach ($pages as $page) {
		# generate nice JS code
		$content = ReadPage($page,$since=0);
		$diameter = strlen($content["text"]) / 100 ;
		if ($diameter < 30) $diameter=30;
		if ($diameter > 120) $diameter=120;
		$author = $content["author"];
		$links = $content["targets"];
		$links_array = explode(",",$links);
		foreach ($links_array as $link) {
			# draw a line between the circle with
			# k must become $link
			if (($linkindex = array_search($link,$pages)) !== false )
				$processinglinkcode .= "  line(e[$pageindex][0],e[$pageindex][1],e[$linkindex][0],e[$linkindex][1]);\n";
		}
		$x = round( 20 + $content["rev"] * 2 );
		$y = round( 50 + ((time() - $content["time"]) / (60*60*24 * 4)) );
		$pagename = preg_replace("/$groupname.(.*)/","$1",$page);
		$processingsetupcode .= "  e[$pageindex][0]=$x;e[$pageindex][1]=$y;e[$pageindex][2]=$diameter;\n";
		//$pagename.link('$page');
		$tx = $x - 20;
		$ty = $y;
		# modify to add to $processingsetupcode instead then itterate over it
		if ($pagename == $sourcename)
			# this is the name of the current page, do sth special
			$processingdrawcode .= "  fill(100);\n  text('$pagename',$tx,$ty);\n  fill(64,128,187,100);\n";
		elseif (preg_match("/$pagename/",getenv('HTTP_REFERRER')))
		# buggy...
		# elseif (preg_match("/$ScriptUrl.*$groupname.*$pagename/",$HTTP_REFERER))
			$processingdrawcode .= "  fill(120);\n  text('$pagename',$tx,$ty);\n  fill(64,128,187,100);\n";

		else
			$processingdrawcode .= "  text('$pagename',$tx,$ty);\n";
		//$processinghandlinglinkscode .= "  new alink($x-20,$y,30,'/$groupname/$pagename','$pagename', #aa0088,#ff00aa),\n";
		# modify $processingsetupcode again to include presence of PTV like startrecall
		$pageindex++;
	}
	//$processinghandlinglinkscode = preg_replace("/(.*),$/","$1",$processinghandlinglinkscode);
	//$processinghandlinglinkscode .= "}\n";
	$processingsetupcode .= " }";
	//$processingdrawcode .= $processinglinkcode.'  forLinks("render");  }'.$processinghandlinglinkscode;
	$processingdrawcode .= $processinglinkcode."  }";
 
	$result = $processinglib.'<canvas data-src="'.$processingfile.'" width="'.$canvaswidth.'" height="'.$canvasheight.'"></canvas>';

	$write_result = file_put_contents($FarmD.$processingfile,$processingsetupcode.$processingdrawcode);
	if (!$write_result)
		if (strlen($processingcode)>0)
			print "Creation of the feed file fails, check write permissions for pmWiki and $processingfile.";
		else
			print "No code to generate, did you correctly generate your Processing code?";

	// ----------------------- return the processed result -----------------------
	return $result;
}

?>
