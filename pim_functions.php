<?php
/* PIM functions (for PmWiki)
 	consider adding other related scripts here too (e.g. MemoryRecipe, Recalls, GroupStats, Coeditions, ...)
		http://fabien.benetou.fr/pub/graphformatexporter.php.txt
		http://fabien.benetou.fr/pub/memorization.php.txt
		note that the order is important though
			e.g. currently MemoryRecipe is loaded after thus can't be called here
 	add to http://fabien.benetou.fr/repository/
	it would be particularly useful to refactor each properly
		especially since they share variables (e.g. first edit) but also some have better function
			e.g. caching in Coeditions
 	change ProcessingJS generation by first producing JSON
		via http://www.php.net/manual/en/function.json-encode.php
		cf http://fabien.benetou.fr/Tools/Processing and http://fabien.benetou.fr/Tools/JavaScript
		this might be even faster on most computers since JS engines are improving
*/

// implement http://fabien.benetou.fr/Wiki/ToDo#Maintenance
function DisplayLinkCheckerTest($pagename){
        global $ScriptUrl, $PubDir, $FarmD;
	$x = explode(".",$pagename);
	$url = $ScriptUrl."/pub/home/linkchecker_results_explorer.php?group=".$x[0]."&name=".$x[1];
	print "<a href=\"$url\">LinkChecker result for $x[0].$x[1]</a>";
		// XXX this should be processed and show visual indicators
		// display the update date
		// XXX update with inotify or on demande instead of nightly cron
}

//handle implicit linking
/*
	cf http://fabien.benetou.fr/MemoryRecalls/ImprovingPIM#ImplicitLinking
*/
function ImplicitLinking(){
	return "list of pages generated on the fly";
}
//$AutoCreate['/\\.GroupStats$/'] = array( 'ctime' => $Now, 'text' => ImplicitLinking());
//$AutoCreate['/^Categora\\./'] = array('ctime' => $Now);
//doesn't work, AutoCreate present in pmwiki.php and example too
// category works though
// TODO try making it global $AutoCreate;

//display working status
// see http://fabien.benetou.fr/Tools/Greasemonkey#VirtualBlinders
Markup("currenttask", "directives", "/\(:currenttask:\)/", GetCurrentTask());
function GetCurrentTask(){
	$currenttask = trim(file_get_contents("/home/web/benetou.fr/fabien/pub/currenttask"));
	if ($currenttask == "")
		return "(either available or asleep, Im usually on CET)";
	return "[[CognitiveEnvironments/".$currenttask."]]";
}

/* deactivated for security reasons
Markup("timetable", "fulltext", "/\(:timetable:\)/",
	"\n||border=1\n||!Time ||!Action ||\n"
	.preg_replace("/echo -level HILIGHT -window 1 /","",
		preg_replace("/.* (\d+) (\d+).* \/(.*)/","||$1:$2||$3||",file_get_contents("/home/utopiah/.irssi/cron.save"))
	)
	."");
*/

//load a dedicated edition page for the admin (shouldn't be hard coded but I dont use Auth)
//global $GLOBALS;
if ($GLOBALS['action'] == 'edit' && ( $GLOBALS['Author'] == 'Fabien' || $GLOBALS['Author'] == 'Utopiah' ) )
{
	//TODO fails since moved in cookbook directory, thus added back there
	global $SkinDir,$pagename;
	LoadPageTemplate($pagename, "$SkinDir/edit.tmpl");
}

//display editions through an horizontal line
/*
fomally done via GnuPlot, cf http://fabien.benetou.fr/Wiki/Visualization#timeline
http://www.scholarpedia.org/article/Spike-response_model is probably too complicated for the small resolution
 yet provide interesting non-linear properties

*/
function DisplayVisualEdits($pagename){
        global $ScriptUrl, $PubDir, $FarmD;

	# equivalent to 404
	if (!PageExists($pagename)) return;

        $processingpath = '/pub/libraries/processing.js';
        $processingfile = "/pub/visualization/edits_per_page/$pagename.pjs";
        $processinglib = "<script src=\"$processingpath\" type=\"text/javascript\"></script>";
        $first_edit = 1212192000;
        $now = time();
        // draw the timeline from the first edit to now()
        // get the list of edits of the current page
        // "get inspired" by PrintDiff() in scripts/pagerev.php
        $canvaswidth = 600-2*10;
        $canvasheight = 10;
        $block_width = 4;
        $block_height = 8;
        $page = ReadPage($pagename);
        if (!$page) return;
        krsort($page); reset($page);
        // newest first
        // for each edit
        $canvas = "
                void setup()
                {
                  size($canvaswidth, $canvasheight);
PFont font;
font = loadFont(\"FFScala-Bold-12.vlw\");
textFont(font);
                }
                void mouseMoved() {
                  checkButtons();
                }

                void mouseDragged() {
                  checkButtons();
                }
                void draw()
                {
                  background(251);
                  fill(0,0,255,20);
                noStroke();
";
        $mousePressed = "void mousePressed() {";
        $destination_link = "$ScriptUrl/".strtr($pagename,".","/")."?action=diff#diff";
        $mousePressed .= "\t\tif (lastHovered>0) { link(\"$destination_link\"+lastHovered); }\n";
        $checkButtons = "void checkButtons() {";
        print $processinglib.'<canvas data-src="'.$processingfile.'" width="'.$canvaswidth.'" height="'.$canvasheight.'"></canvas>';
        // set to false to de-activate cache (practical for tests)
        $newest_diff = true;
        //$newest_diff = false;
	//$first_diff = true;
        foreach($page as $k=>$v) {
                if (!preg_match("/^diff:(\d+):(\d+):?([^:]*)/",$k,$match)) continue;

                $diff = $match[1];

                $diffclass = $match[3];
                if ($diffclass=='minor')
                        { $canvas .= "\t\tfill(0,255,0,20);\n"; }
                else
                        { $canvas .= "\t\tfill(0,0,255,20);\n"; }
		//if ($first_diff)
                //        { $canvas .= "\t\tfill(255,0,0,20);\n"; $first_diff = false;}

                $buttonname = "over".$diff."Button";
                //$bools .= "boolean $buttonname = false;\n";
                if (file_exists($FarmD.$processingfile) && $newest_diff)
                        if ( filemtime($FarmD.$processingfile) > $diff)
                                return;
                $newest_diff = false;
                //  add a sightly transparent tick rectangle with its Unix timestamp link to the diff page
                //  the mouse over a certain edit should change its color
                //  see http://processingjs.org/learning/basic/embeddedlinks
                // or clicablerects.js via Pomax on :mozilla2/#processing.js (14/05/2011 ~11pm)
                $x = round ( ($canvaswidth - $block_width) * ( (($now - $first_edit)-($now-$diff)) / ($now - $first_edit)));
                $y = 1;
                $checkButtons .= "\t\tif ( mouseX > $x && mouseX < $x+$block_width) lastHovered = $diff; \n";
                // if (mouseY > $y && mouseY < $y+$block_height) not really required
                // others should be set to false else one always jump to the olded diff mouved over
                $canvas .= "\t\trect($x,$y,$block_width,$block_height);\n";
        }
        $canvas .= "\t\tstroke(0,155);\n";
        $canvas .= "\t\tfill(0,0,255,80);\n";
        $canvas .= "\t\ttext(\"Edits:\",2,10 );\n";
        $canvas .= "\t\tfill(0,0,255,40);\n";
        for ($year=2009;$year<date("Y")+1;$year++){  //each year until now
                $unixyear = mktime(0,0,0,1,1,$year);
                $x = round ( ($canvaswidth - $block_width) * ( (($now - $first_edit)-($now-$unixyear)) / ($now - $first_edit)));
                $y = 0;
                $canvas .= "line($x,$y,$x,$y+$block_height+2); text(\"$year\",$x+2,$y+10 );\n";
        }
        $canvas = $bools . $canvas ."}" . $mousePressed ."}" .$checkButtons . "}";

        // load ProcessinJS

        $write_result = file_put_contents($FarmD.$processingfile,$canvas);

        // print resulting canvas

        $older_gnuplot_version = "<div><center><img src=\"/pub/visualization/edits_per_page/{$pagename}.png\" alt=\"/pub/visualization/edits_per_page/{$pagename}.png\"/></br>(<a href=\"$ScriptUrl/Wiki/Visualization#timeline\">visualization details</a>).</center></div><hr />";
}

/*
details at http://fabien.benetou.fr/MemoryRecalls/ImprovingPIM#AudioPIM
	generated via e.g.
		P=ReadingNotes.TheThingsWeDo; grep ^text= $P | sed "s/%0a/\\n/g" | sed 's/^!/TITLE /' | sed "s/[^a-zA-Z]/ /g" | sed "s/^TITLE\(.*\)/<voice gender=\"female\">\1<\/voice>/" > $P.txt && espeak -m -f $P.txt -w ../pub/audio/$P.wav && oggenc ../pub/audio/$P.wav && rm ../pub/audio/$P.wav $P.txt
	the output should be improved by better parsing
	details on the HTML markup
		https://developer.mozilla.org/en/HTML/Element/audio
		http://www.w3.org/TR/html5/video.html#attr-media-controls
*/
function DisplayAudio($pagename){
        global $ScriptUrl, $PubDir, $FarmD;
	$audiofile = $FarmD."/pub/audio/".$pagename.".ogg";
	$audiourl = $ScriptUrl."/pub/audio/".$pagename.".ogg";
	if (file_exists($audiofile))
		print "
<div width=\"100px\"><a name=\"DisplayAudio\">
	<audio width=\"100\" controls=\"controls\" >
		<source src=\"$audiourl\"  type=\"audio/ogg\"  />
		Your browser does not allow HTML5 audio.
	</audio>
	</a></br>Download the <a href=\"$audiourl\">audio file (ogg)</a> to play later
	(see <a href=\"$ScriptUrl/MemoryRecalls/ImprovingPIM#AudioPIM\">details</a>).
</div>";
}

//display images with transparancy invertionnaly proportional to last time of update
// http://fabien.benetou.fr/MemoryRecalls/ImprovingPIM#VisualDecayOfInformation
function DisplayDecay($pagename){

/*
 consider
        adding threshold
        not keeping it linear (e.g. log)
                but still constantly inscreasing between 0 and 1
        use a factor when matches (e.g. regex changing $impeding_factor or $first_edit)
                regex would match groupname (e.g. "Person." with fast decay) or pagename or both (e.g. "Math" with slow decay)
        yellowish background, looking like old paper
 ...rest got deleted by a dumb rm...
*/
	global $ScriptUrl;
        $first_edit = 1212192000;
        $now = time();
	//load page
        $page = ReadPage($pagename);
        if (!$page) return;

	$last_edit = $page["time"];
	//get last edit

        $destination_link = "$ScriptUrl/".strtr($pagename,".","/")."?action=edit";

	//use the previous equation adding 1 - ()
        $opacity = round ( 1 - ( (($now - $first_edit)-($now-$last_edit)) / ($now - $first_edit)) , 2 );

	$opacitymsg = "opacity=$opacity";
	if ($opacity > 0.8)
		$opacitymsg = "<font color=\"red\">".$opacitymsg."</font>";

	//if user if admin
	if ( $GLOBALS['Author'] == 'Fabien' || $GLOBALS['Author'] == 'Utopiah' ) {
		//for 1 to a multiplier of value
		for ($i=0;$i<$opacity*10;$i++){
			// display another visual problem with a link back to improvingwiki#visualdecay
			print "<div style=\"opacity:$opacity;position:absolute;top:".rand(60,800)."px;left:".rand(100,500)."px;\"><a href=\"$destination_link\">"
				.decbin(rand(0,10000))."</a></div>";
		}
		// add a good practice msg
		print "<div style=\"opacity:$opacity;position:absolute;top:10px;left:450px;width:300px;background-color:gray;\">
			$opacitymsg edits should be done to check if 
			the informamtion presented is still relevant,
			links are working,
			opinion expressed still correct, etc.
			</div>";
	}


	//print img with opacity + warning message

	print "<div style=\"opacity:$opacity;\"><a name=\"Decay\"></a>If you can read this text ($opacitymsg) if means the page has not been edited for a long time. Consequently the information it holds might be deprecated or no longer represent the opinion of this author.</div>";

}
