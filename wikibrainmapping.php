<?php

## give a visual representation of pages and group
$RecipeInfo['WikiBrainMapping']['Version'] = '2011-05-15';
Markup('WikiBrainMapping', 'directives', '/\\(:WikiBrainMapping:\\)/e', Keep(WikiBrainMapping()));
 
function WikiBrainMapping(){
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
	$processingpath = '/pub/libraries/processing.js';
	$processingfile = "/pub/visualization/wikibrainmapping.pjs";
	$processinglib = "<script src=\"$processingpath\" type=\"text/javascript\"></script>";

	# eventually modify to use a dedicated folder
	# generate a file per page
	if (!file_exists($FarmD.$processingfile))
		if (!touch($FarmD.$processingfile))
			print "Creation of the processing file fails, check write permissions for pmWiki and $processingfile.";

	# cached version (to comment during tests)
 	#if ( (time() - filemtime($FarmD.$processingfile)) < 3600 ) return $processinglib.'<canvas data-src="'.$processingfile.'" width="'.$canvaswidth.'" height="'.$canvasheight.'"></canvas>';

	$canvaswidth=500;
	$canvasheight=300;

	$groupname = $sourcegroup;
	$pages = ListPages("/$groupname\./e");
	$pages = MatchPageNames($pages,array('-*.*RecentChanges,-*.GroupFooter,-*.GroupHeader'));
	$pagesnum = count($pages);
	$processingsetupcode = "
PShape s;
void setup(){
	frameRate(15);
	size($canvaswidth,$canvasheight);
	strokeWeight(2);

	PFont font;
	font = loadFont(\"FFScala-32.vlw\"); 
	textFont(font); 

	sright = loadShape(\"/pub/illustrations/flat_brain_right.svg\");
	sleft = loadShape(\"/pub/illustrations/flat_brain_left.svg\");
	smooth();
";
	$processingdrawcode .= "
void draw(){
    background(255);
";

	$processingdrawcode .= "shape(sright,300, 400);";
	$processingdrawcode .= "shape(sleft,200, 400);";
	$processingdrawcode .= "fill(0,0,255,255);\n";
	$processingdrawcode .= "text(\"flattened left hemisphere\",300,10);\n;";
	$processingdrawcode .= "text(\"flattened right hemisphere\",100,10);\n;";

	$target_area["x"] = 150;
	$target_area["y"] = 100;
	$target_area["width"] = 50;
	$target_area["height"] = 60;

	//replace with actual list of pages
	for ($pagen=0;$pagen<10;$pagen++){
		$x = rand($target_area["x"],$target_area["x"]+$target_area["width"]);
		$y = rand($target_area["y"],$target_area["y"]+$target_area["height"]);
		//draw cercle in the area
		$radius = 3;
		$processingdrawcode .= "fill(255,0,0,100);ellipse($x,$y,$radius,$radius);\n";
		//draw diagonal line of edits
		$linelength=10;
		$processingdrawcode .= "strokeWeight(1); line($x,$y,$x+$linelength,$y-$linelength);\n";
		//replace with actual list of edits
		$maxdiff = rand(0,10);
		for ($diff=0;$diff<$maxdiff;$diff++){
			//draw points on the line of edits with smaller stroke weight
			$diff_pos = rand(0,$linelength);
			$processingdrawcode .= "strokeWeight(2); point($x+$diff_pos,$y-$diff_pos);\n";
		}
	}
	$processingdrawcode .= "fill(255,0,0,255);\n";
	$processingdrawcode .= "text(\"Note that currently points are randomly generated\\nand randomly positionned on a designated area.\\nNo mapping has been done between function and position.\\nGyri and sulci are also not represented.\",10,200);\n;";
	$processingsetupcode .= " }";
	$processingdrawcode .= " }";
 
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
