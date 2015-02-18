<?php

## give a visual representation of pages and group
$RecipeInfo['GraphProcessingBooks']['Version'] = '2011-07-05';
Markup('GraphProcessingBooks', 'directives', '/\\(:GraphProcessingBooks:\\)/e', Keep(GraphProcessingBooks()));
 
function GraphProcessingBooks(){
	$sourcepagename =  $_SERVER["REQUEST_URI"];
	// apply changes done to graphprocessing.php to insure proper URI format
	$sourcepagename = preg_replace("/\/(.*)\?.*/","$1",$sourcepagename);
	$sourcename = preg_replace("/\/.*[\.\/](.*)/","$1",$sourcepagename);
	$sourcegroup = preg_replace("/\/(.*)[\/\.].*/","$1",$sourcepagename);
	$groupname = $sourcegroup;
	if ($sourcename != "ReadingNotes") return;
	if ($sourcegroup != "ReadingNotes") return;

	global $FarmD, $WikiTitle, $ScriptUrl;

	$processingpath = '/pub/libraries/processing.js';
	$processingfile = "/pub/processingjsgraph/Books.pjs";
	//$covercachefile = "/pub/openlibrarycoverscache/listing.txt";
	//$covercachecontent = '';
	$processinglib = "<script src=\"$processingpath\" type=\"text/javascript\"></script>";

	# eventually modify to use a dedicated folder
	# generate a file per page
	if (!file_exists($FarmD.$processingfile))
		if (!touch($FarmD.$processingfile))
			print "Creation of the processing file fails, check write permissions for pmWiki and $processingfile.";

	# cached version (to comment during tests)
	# note that this could be improved by checking for the age of this page too
 	if ( (time() - filemtime($FarmD.$processingfile)) < 3600 ) return $processinglib.'<canvas data-src="'.$processingfile.'" width="'.$canvaswidth.'" height="'.$canvasheight.'"></canvas>';

	$canvaswidth=700;
	if ($sourcename == $groupname)
		# if we are on the group page displays a larger canvas
		$canvasheight=700; else $canvasheight=150;

	$pages = ListPages("/$groupname\./e");
	//$pages = MatchPageNames($pages,array('-*.RecentChanges,-*.GroupFooter,-*.GroupHeader'));
	unset($pages[array_search('ReadingNotes.ReadingNotes',$pages)]);
	unset($pages[array_search('ReadingNotes.Template',$pages)]);
	unset($pages[array_search('ReadingNotes.GroupHeader',$pages)]);
	unset($pages[array_search('ReadingNotes.GroupFooter',$pages)]);
	$pages = GroupKeyPages($groupname);
	unset($pages[array_search('ReadingNotes.3DNegotiation',$pages)]);
	//JS bug on varname starting with number
	$pagesnum = count($pages);
	$processingpreloadcode = "
float[][] e = new float[$pagesnum][9];
";

# draggable code directly from http://processingjs.org/source/head-animation.pjs
	$processingsetupcode = "

// Selected mode switch
int sel = 0;

// Set drag switch to false
boolean dragging=false;

// If use drags mouse...
void mouseDragged(){
  
  // Set drag switch to true
  dragging=true;
}
  
// If user releases mouse...
void mouseReleased(){
  
  // ..user is no-longer dragging
  dragging=false;
}

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
	if( dist(e[j][0],e[j][1],mouseX,mouseY) < e[j][2] ){
	     if(dragging){
        
	        // Move circle to circle position
	        e[j][0]=mouseX;
	        e[j][1]=mouseY;
		text(e[j][7],mouseX-10,mouseY);
	      } else {      
		      // User has nothing selected
		      sel=0;		      
		}
	}
	text(e[j][7],e[j][0]-10,e[j][1]);
	fill(e[j][3],e[j][4],e[j][5],e[j][6]);
 	ellipse(e[j][0],e[j][1],e[j][2],e[j][2]);
	image(e[j][8],e[j][0]-10,e[j][1]-10,10*4,10*4);
    }
";
	$processingdrawcode .= "  text('^ -#revisions',10,".$canvasheight."-20);\n";
	$processingdrawcode .= "  text('-> -freshness',30,".$canvasheight."-10);\n";
	$processingdrawcode .= "  text('diameter = amount of text',".$canvaswidth."-200,".$canvasheight."-10);\n";

	# detect category
	$category_finder = ReadPage($sourcegroup.".".$sourcename,$since=0);
	$sourcename_category = preg_replace("/.*\[\[\!(.*)\]\].*/s","$1",$category_finder["text"]);
	
	$processinghandlinglinkscode = '';

	$processinghandlinglinkscode .= 'Object l={';
	$processinglinkcode = "  stroke(64,128,187,100);\n";
	$pageindex=0;
	# scan over the array of pages to get mazimum and generate axis...
		# use min() and max() to get limits

        foreach ($pages as $page) {
		//$covercachecontent .= "http://covers.openlibrary.org/b/isbn/".PageVar($page,'$:isbn')."-S.jpg\n";
		# generate nice JS code
		$content = ReadPage($page,READPAGE_CURRENT);
		$diameter = strlen($content["text"]) / 300 ;
		if ($diameter < 30) $diameter=30;
		if ($diameter > 120) $diameter=120;
		$page_category = preg_replace("/.*\[\[\!(.*)\]\].*/s","$1",$content["text"]);
		
		$c_alpha=100; 
		if (($sourcename != $groupname) && ($page_category != $sourcename_category))
			$c_alpha = 50;
		
		# distinguished page with the same tag (eventually color based on hash of the name of the category)
		switch ($page_category) {
			# categories listed by importance as only the first found matters
			# todo transform to a table then display ellipse+category as explanations
			# foreach category apply color
			case "Information" :
				$c_val1=20; $c_val2=20; $c_val3=20; break; #dark blue
			case "Physics" :
				$c_val1=20; $c_val2=240; $c_val3=17; break; #green
			case "Chemistry" :
				$c_val1=250; $c_val2=40; $c_val3=17; break; #red
			case "Biology" :
				$c_val1=250; $c_val2=140; $c_val3=17; break; #orange
			case "Neurology" :
				$c_val1=200; $c_val2=140; $c_val3=17; break; #ligh orange
			case "Psychology" :
				$c_val1=255; $c_val2=204; $c_val3=0; break; #yellow
			# still to handle: Sociology Politics Uncategorized
			default:
				$c_val1=64; $c_val2=128; $c_val3=187; break;
		}
		$author = $content["author"];
		$links = $content["targets"];
		$links_array = explode(",",$links);
		foreach ($links_array as $link) {
			# draw a line between the circle with
			if (($linkindex = array_search($link,$pages)) !== false )
				if ($linkindex < $pagesnum - 1 ){
					$processinglinkcode .= "  line(e[$pageindex][0],e[$pageindex][1],e[$linkindex][0],e[$linkindex][1]);\n";
					if (isset($b["$page"]["outgoing"]))
						$b["$page"]["outgoing"]++;
					else
						{ $b["$page"]["outgoing"] = 1; $b["$page"]["name"]=$page; }
					if (isset( $b["$link"]["incoming"]))
						$b["$link"]["incoming"]++;
					else
						{ $b["$link"]["incoming"] = 1; $b["$link"]["name"]=$link; }
				}
		}
		if ($sourcename == $groupname)
			# change scale if we are on the group page which displays a larger canvas
			$y_factor = 40; else $y_factor = 6;
		$xcenter = $canvaswidth / 2;
		$ycenter = $canvasheight / 2;
		$radiosmin = 30;
		$radius = $radiosmin + log($content["rev"]) * 50;
		# this would result in a problem in page view, for now just use it in the group view
		$angle = ( log( 1 + ((time() - $content["time"]) / (60*60*24 * 4)) * 12) ) * 100; 

		# $y = round( 20 + $content["rev"] * $y_factor );
		# $x = round( 20 + log( 1 + ((time() - $content["time"]) / (60*60*24 * 4)) * 12) * 100);
		$x = $xcenter + $radius * cos($angle);
		$y = $ycenter + $radius * sin($angle);
		# log is used for faster "decay", if a lot of pages are edited it avoid craming them to the left
		$pagename = preg_replace("/$groupname.(.*)/","$1",$page);
		$processingsetupcode .= "  e[$pageindex][0]=$x;e[$pageindex][1]=$y;e[$pageindex][2]=$diameter;\n";
		$processingsetupcode .= "  e[$pageindex][3]=$c_val1;e[$pageindex][4]=$c_val2;e[$pageindex][5]=$c_val3;e[$pageindex][6]=$c_alpha;e[$pageindex][7]='$pagename';\n";
		$tx = $x - 20;
		$ty = $y;
		# add pic if it exists
		$isbn = PageVar($page,'$:isbn');
		$localcover = $FarmD."/pub/openlibrarycoverscache/".$isbn."-S.jpg";
		$localcoverurl = "/pub/openlibrarycoverscache/".$isbn."-S.jpg";
		if (file_exists($localcover)){
			if ($sourcename == $groupname)
				$pic_factor = 4; else $pic_factor = 2;
			#preload pic
			$processingpreloadcode .= "/* @pjs preload=\"".$localcoverurl."\"; */\n";
			$processingpreloadcode .= "  PImage $pagename"."Pic;\n";
			#display pic
			$processingsetupcode .= "e[$pageindex][8] = loadImage(\"".$localcoverurl."\");\n";
		}
		$pageindex++;
	}
	//$processinghandlinglinkscode = preg_replace("/(.*),$/","$1",$processinghandlinglinkscode);
	//$processinghandlinglinkscode .= "}\n";
	$processingsetupcode .= " }";
	//$processingdrawcode .= $processinglinkcode.'  forLinks("render");  }'.$processinghandlinglinkscode;
	$processingdrawcode .= $processinglinkcode."  }";
 
	$result = $processinglib.'<canvas data-src="'.$processingfile.'" width="'.$canvaswidth.'" height="'.$canvasheight.'"></canvas>';

	$write_result = file_put_contents($FarmD.$processingfile,$processingpreloadcode.$processingsetupcode.$processingdrawcode);
	if (!$write_result)
		if (strlen($processingcode)>0)
			print "Creation of the feed file fails, check write permissions for pmWiki and $processingfile.";
		else
			print "No code to generate, did you correctly generate your Processing code?";

	//file_put_contents($FarmD.$covercachefile,$covercachecontent);
	// ----------------------- return the processed result -----------------------
	$mo = $mi = 0;
	foreach ($b as $bs){
		if (isset($bs["incoming"]))
			if ($bs["incoming"] > $mi) { $mi = $bs["incoming"]; $miname = $bs["name"]; }
		if (isset($bs["outgoing"]))
			if ($bs["outgoing"] > $mo) { $mo = $bs["outgoing"]; $moname = $bs["name"]; }
	}
	return $result."Note that the network is limited to \"significant\" notes only (metrics are changing as they are tested).<br/>Within this group:<br/>MaxOutgoing=$moname ($mo links)<br/>MaxIncoming=$miname ($mi links)<br/>";
}

?>
