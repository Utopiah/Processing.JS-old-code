<?php if (!defined('PmWiki')) exit();

/*
Generate exercises based on wiki content and its structure
 
exercises idea to implement (cf http://fabien.benetou.fr/Cookbook/Cognition#DailyExercisesFeed )
	which of those group corresponds the graph visualization V, A, B or C? 
		using groupnetworkvisualization.php
	what is the correct order for pages A, B and C on descending criteria i A>B>C? A>C>B? B>C>A? B>A>C? C>B>A? C>A>B?
		e.g. size, frequency update, last edition, ... 
	what is the color hashing used in RevertedPIM for this group, color A, B or C?
		might require JS
	definition of word and vice versa
		limited to pages with definitions (e.g. some languages)
			list lines with "* word = definition"
			split word and definition
			pick one and display the other with alternatives
		extend type=pagehasexpression
		keyword or URL in pages or specific "word" per type
	which of those update visualizations VA, VB or VC corresponds the page P ? (invert of type=historyvisualization)
	note that overall most exercise which is about assign a set to another have a mirror
		this mirror might have a completely different difficulty though
difficulty parameter
	should be linked to the counter
	e.g. increasing the number of possibilities (at least 1 out of 5 pages instead of at least 1 out of 3)
	see the hall of fame to see which exercise are the easiest
do a graph traversal rather than random jumps
	i.e. if pick randomly from the linked pages or from the group or from the the whole wiki of the previous page
	consider https://research.cc.gatech.edu/inc/game-forge 
improve parsing
	page with just one line, especially markups, typically redirections
filters
	structuring e.g. PmWiki. , RecentChanges, GroupFooter, GroupHeader, Template, ...
	strictly limited to "new" pages (e.g. less than a month ago)
	strictly limited to "old" pages (e.g. more than 2 years ago)
support bias
	X% of page from an existing filter
		e.g. 80% of "new" pages
support other interfaces
	e.g. http://www.cs.cmu.edu/~listen/ listed in http://fabien.benetou.fr/Content/Education#SeeAlso
		ideally also more visual, animated, ...
	Vimperator with predictable link numbers to go faster
track time require for answering each question
	display a clock
	could improve excitment, e.g. answer the maximum number of questions correctly in X minutes
better manage the whole process	
 	random type is not implemented properly enough for score tracking
		e.g. highest score from random appear as other exercise
	random type happens just once, not as a sequence like the other types
	mix types
		support non equally divided session
			e.g. 50% of exercice type A, 30% type B, 10% C and 10% D
		dynamically divise based on score history
			e.g. 40% of exercice type B with the lowest high score, 30% D with 2nd lowest high score, 20% A with difficulty increase, 10% C diff++

implemented
	is word X from page A, B, C or D? (type=expressioninpage)
	does page A contains word X, Y or Z? (type=pagehasexpression)
	which of those page corresponds the updates visualization V, A, B or C? (type=historyvisualization)
	is page A linked to page B? (type=pagelink)
*/

$RecipeInfo['Exercises']['Version'] = '2015-16-08';

SDV($HandleActions['Exercises'], 'Exercises');
SDV($HandleAuth['Exercises'],'read');

function Exercises($pagename, $auth){
	global $ScriptUrl, $FarmD;
	$type = $_GET["type"];
	$counter = (int) $_GET["counter"];
	$difficulty = (int) $_GET["difficulty"];
	list($group,$page) = explode(".",$pagename);

	$availableexercisetypes = array("pagelink", "expressioninpage", "pagehasexpression");
	if (file_exists($FarmD."/pub/visualization/edits_per_page/" ))
		//the pim_functions.php generated the PJS has been loaded before
		$availableexercisetypes[] = "historyvisualization";

	if ($type=="random" || $type=="") {
		// XXX after 1 random exercise, going back to the same type of the last picked exercise
		$type= $availableexercisetypes[array_rand($availableexercisetypes)];
		$random=true;
	}

	$result = "";
	$verbatimresult = "";

	$n=3;
	// n should be based on $difficulty, e.g. n=2+$difficulty or n=2^$difficulty
	// it used to determine the number of false answers
	// it can be overwritten per each exercise

	$minlinelength = 20;
	// this could also be consider a difficulty, the shorter the harder
	// it can be overwritten per each exercise

	// tracking the current score
	$counterparam = "";
	if ( $counter > 0) {
		$counterparam = "counter=$counter&";
	}

	if ($group == "AllPages"){
		// equivalent to getting ALL pages
		$pages = ListPages();
	} else {
		$pages = ListPages("/$group\./e");

	}

	//randomly pick a page in the possible pages
	$sourcepage = $pages[array_rand($pages)];
	unset($pages[array_search($sourcepage,$pages)]);

	switch ($type){
		case "pagelink":
			$content = ReadPage($sourcepage,READPAGE_CURRENT);
			$links = $content["targets"];
			$links_array = explode(",",$links);
			//note that count($links_array)<1) was not used since explode returned an array with en empty value
			while (($links_array[0]=="") && (count($pages)>0)) {
				$sourcepage = $pages[array_rand($pages)];
				unset($pages[array_search($sourcepage,$pages)]);
				$content = ReadPage($sourcepage,READPAGE_CURRENT);
				$links = $content["targets"];
				$links_array = explode(",",$links);
			}
			if ($links_array[0]=="") {
				$result .= "Unfortunately it seems no suitable page has been found for this game. ";
				$result .= "Try in another group or [[AllPages/AllPages?action=Exercises&type=$type&counter=$counter|in the entire wiki]].";
				break;
			}

			//randomly pick a page amongst the linked pages
			$answers[] = $links_array[array_rand($links_array)];
			unset($pages[array_search($answers[0],$links_array)]);
			unset($pages[array_search($answers[0],$pages)]);

			//randomly pick n-1 others pages which may not be amongst the list of linked page
			for ($i=1;$i<$n;$i++) {
				$answers[] = $pages[array_rand($pages)];
				unset($pages[array_search($answers[$i],$pages)]);
			}

			//display
			$result .="Is page [[$sourcepage]] linked to ";

			// there should now be at least 1 needle in the haystack, the first one
			// note that there might be more but this is not a problem as long as the question is stated clearly.
			shuffle($answers);

			for ($i=0;$i<count($answers);$i++) {
				if ( $i == count($answers) -1 )
					$result .= " or ";
				$currentpage = $answers[$i];
				//display the groupname everytime only if using AllPages
				if ($group != "AllPages") 
					list($currentgroup,$currentpage) = explode(".",$answers[$i]);
				$result .="[[$pagename?action=ExercisesCheck&".$counterparam
					."type=$type&source=$sourcepage&answer=".$answers[$i]."|".$currentpage."]], ";
			}
			$result .="? ";
			$result .="\n\nClick on the answer.";
			break;
		case "historyvisualization":
			$processingpath = "$ScriptUrl/pub/libraries/processing.js";
			$processinglib = "<script src=\"$processingpath\" type=\"text/javascript\"></script>";
			$processingfile = "$ScriptUrl/pub/visualization/edits_per_page/$sourcepage.pjs";
			// TODO if it does not exist, pick another page, adapt code above

			$answers[] = $sourcepage;

			//randomly pick n-1 others pages which may not be amongst the list of linked page
			for ($i=1;$i<$n;$i++) {
				$answers[] = $pages[array_rand($pages)];
				unset($pages[array_search($answers[$i],$pages)]);
			}

			//display
			$historyvisualization = $processinglib.'<canvas data-src="'.$processingfile.'"></canvas>';
			$verbatimresult = $historyvisualization;
			$result .="Which of those page corresponds the updates visualization:";

			// note that there might be more than 1 correct answer but this is not a problem as long as the question is stated clearly.
			shuffle($answers);

			for ($i=0;$i<count($answers);$i++) {
				if ( $i == count($answers) -1 )
					$result .= " or ";
				//display the groupname everytime only if using AllPages
				$currentpage = $answers[$i];
				if ($group != "AllPages") 
					list($currentgroup,$currentpage) = explode(".",$answers[$i]);
				$result .="[[$pagename?action=ExercisesCheck&".$counterparam
					."type=$type&source=$sourcepage&answer=".md5($answers[$i])."|".$currentpage."]], ";
			}
			$result .="? ";
			$result .="\n\nClick on the answer.";
			break;
		case "pagehasexpression":
			$content = ReadPage($sourcepage,READPAGE_CURRENT);
			$text = $content["text"];
			$lines = explode("\n",$text);

			$pickedline = $lines[array_rand($lines)];
			unset($lines[array_search($pickedline,$lines)]);
			//should be better parsed! e.g. remove markups
			//pregreplace("/(:.*:)/","",$pickedline);
			while (( strlen($pickedline) < $minlinelength ) && ( count($lines) > 0 ) ) {
				$pickedline = $lines[array_rand($lines)];
				//pregreplace("/(:.*:)/","",$pickedline);
				unset($lines[array_search($pickedline,$lines)]);
			}
			$actualanswer = $pickedline;
			$answers[] = $pickedline;

			for ($i=1;$i<$n;$i++) {
				$otherpage = $pages[array_rand($lines)];
				$otherpage = $pages[array_rand($pages)];
				unset($pages[array_search($otherpage,$pages)]);
				$content = ReadPage($otherpage,READPAGE_CURRENT);
				$text = $content["text"];
				$lines = explode("\n",$text);
				$pickedline = $lines[array_rand($lines)];
				unset($lines[array_search($pickedline,$lines)]);
				while (( strlen($pickedline) < $minlinelength ) && ( count($lines) > 0 ) ) {
					$pickedline = $lines[array_rand($lines)];
					unset($lines[array_search($pickedline,$lines)]);
				}
				$answers[] = $pickedline;
			}

			//display
			$result .= "Does the page [[$sourcepage]] has expression ";

			shuffle($answers);

			for ($i=0;$i<$n;$i++) {
				if ($answers[$i]==$actualanswer)
					$hashedanswer = md5($sourcepage);
				else
					$hashedanswer = md5($answers[$i]);
				// hashing the answer, NOT the page, but only used to hide so should not be problematic
				$result .="\n* [[$pagename?action=ExercisesCheck&".$counterparam
					."type=$type&source=$sourcepage&answer=$hashedanswer|$i]] [@".$answers[$i]."@] ";
			}
			$result .="\n\nClick on the answer.";
			
			break;
		case "expressioninpage":
			$content = ReadPage($sourcepage,READPAGE_CURRENT);
			$text = $content["text"];
			$lines = explode("\n",$text);

			$pickedline = $lines[array_rand($lines)];
			unset($lines[array_search($pickedline,$lines)]);
			while (( strlen($pickedline) < $minlinelength ) && ( count($lines) > 0 ) ) {
				$pickedline = $lines[array_rand($lines)];
				unset($lines[array_search($pickedline,$lines)]);
			}
			$answers[] = $sourcepage;

			//randomly pick n-1 others pages which may not be amongst the list of linked page
			for ($i=1;$i<$n;$i++) {
				$otherpage = $pages[array_rand($pages)];
				unset($pages[array_search($otherpage,$pages)]);
				$answers[] = $otherpage;
			}

			//display
			$result .= "Does the page expression \n* [@$pickedline@] \n\nbelongs to ";


			// there should now be at least 1 needle in the haystack, the first one
			// note that there might be more but this is not a problem as long as the question is stated clearly.
			shuffle($answers);

			for ($i=0;$i<$n;$i++) {
				$hashedanswer = md5($answers[$i]);
				if ( $i == count($answers) -1 )
					$result .= " or ";
				$result .="[[$pagename?action=ExercisesCheck&".$counterparam
					."type=$type&source=$sourcepage&answer=$hashedanswer|".$answers[$i]."]], ";
			}
			$result .="?\n\nClick on the answer.";
			
			// explode content by line "%0a" or word " "
			break;
		default:
			$result .= "Exercise type unknown.";
	}

        unset($availableexercisetypes[array_search($type,$availableexercisetypes)]);
	if (count($availableexercisetypes) > 0) {
		$result .= "\n\nTry other types of exercises:";
		foreach ($availableexercisetypes as $e) {
			$result	.= " [[$pagename?action=Exercises&type=$e|$e]],";
		}
		$result .= " or a [[$pagename?action=Exercises&type=random|random]] one.";
		if ( $counter > 0) {
			$result .= " (note that it resets the counter)";
		}
	}
	$result .= "\n\n%center%[-Generated by [[http://fabien.benetou.fr/MemoryRecalls/ImprovingPIM#PIMBasedExercises|PIM Based Exercises]].-]%%";
	$renderedresult = MarkupToHTML($pagename, $result);
	print "<html>".$verbatimresult.$renderedresult."</html>";
	
	//$text = RetrieveAuthSection($pagename,$auth);
	//$content = MarkupToHTML($pagename, $text);
	//print $content;
}

SDV($HandleActions['ExercisesCheck'], 'ExercisesCheck');
SDV($HandleAuth['ExercisesCheck'],'read');

function ExercisesCheck($pagename, $auth){
	global $ScriptUrl, $FarmD, $Author;

	$activityfile = $FarmD."/wiki.d/.exercisesscores";

	$type = $_GET["type"];
	$sourcepage = $_GET["source"];
	$answer = $_GET["answer"];
	$counter = 0;
	if ( isset($_GET["counter"]) )
		$counter = (int) $_GET["counter"];

	$score_lower_threshold = 3;
	// this could also be a value relative to the top score if there is one

	list($group,$page) = explode(".",$pagename);
	if ($group == "AllPages"){
		// equivalent to getting ALL pages
	        $pages = ListPages();
	} else {
	        $pages = ListPages("/$group\./e");
	}

	$result = "";
	$verbatimresult = "";

	switch ($type) {
		case "pagelink":
			$content = ReadPage($sourcepage,READPAGE_CURRENT);
			$links = $content["targets"];
			$links_array = explode(",",$links);
			$formattedlinks = implode(", ",$links_array);

			if ( in_array($answer,$links_array) ) {
				$result .="Excellent! [[$sourcepage]] is indeed linked to [[$answer]]. ";
				$result .="Note that $formattedlinks also are. ";
				if ( $counter > 0) {
					$counter++;
					$result .="\n\nSee if you can [[$pagename?action=Exercises&type=$type&counter=$counter|solve yet another one]]. ";
				} else {
					$result .="\n\nSee if you can [[$pagename?action=Exercises&type=$type&counter=1|solve yet another one]]. ";
				}
			} else {
				$result .="No, [[$sourcepage]] is not linked to [[$answer]] ";
				$result .="but $formattedlinks are. ";
				if ($counter > 0){
					$result .="\nIt means you are losing your $counter points.";
					// $halloffame = ExercisesResults();
					// to add to $verbatimresult to motivate playing again
				}
				$result .="\n\nTry to redeem yourself by [[$pagename?action=Exercises&type=$type|trying another time]]. ";
			}
			break;
		case "historyvisualization":
			if ( md5($sourcepage) === $answer ) {
				$result .="Excellent! [[$sourcepage]] indeed has that history of editions.";
				if ( $counter > 0) {
					$counter++;
					$result .="\n\nSee if you can [[$pagename?action=Exercises&type=$type&counter=$counter|solve yet another one]]. ";
				} else {
					$result .="\n\nSee if you can [[$pagename?action=Exercises&type=$type&counter=1|solve yet another one]]. ";
				}
			} else {
				$result .="No, it was the visualization of [[$sourcepage]] history of editions.";
				// display it again
				if ($counter > 0) $result .="\nIt means you are losing your $counter points.";
				$result .="\n\nTry to redeem yourself by [[$pagename?action=Exercises&type=$type|trying another time]]. ";
			}
			break;
		case "pagehasexpression":
		// XXX does not work
			if ( md5($sourcepage) === $answer ) {
				$result .="Excellent! [[$sourcepage]] indeed has that expression.";
				if ( $counter > 0) {
					$counter++;
					$result .="\n\nSee if you can [[$pagename?action=Exercises&type=$type&counter=$counter|solve yet another one]]. ";
				} else {
					$result .="\n\nSee if you can [[$pagename?action=Exercises&type=$type&counter=1|solve yet another one]]. ";
				}
			} else {
				$result .="No, it was the expression of [[$sourcepage]].";
				// display it again
				if ($counter > 0) $result .="\nIt means you are losing your $counter points.";
				$result .="\n\nTry to redeem yourself by [[$pagename?action=Exercises&type=$type|trying another time]]. ";
			}
			break;
		case "expressioninpage":
			if ( md5($sourcepage) === $answer ) {
				$result .="Excellent! that expression did come from [[$sourcepage]].";
				if ( $counter > 0) {
					$counter++;
					$result .="\n\nSee if you can [[$pagename?action=Exercises&type=$type&counter=$counter|solve yet another one]]. ";
				} else {
					$result .="\n\nSee if you can [[$pagename?action=Exercises&type=$type&counter=1|solve yet another one]]. ";
				}
			} else {
				$result .="No, [[$sourcepage]] was the page that expression came from.";
				// display it again
				if ($counter > 0) $result .="\nIt means you are losing your $counter points.";
				$result .="\n\nTry to redeem yourself by [[$pagename?action=Exercises&type=$type|trying another time]]. ";
			}
			break;

		default:
			$result .= "Exercise type unknown, this most likely indicate that the checking for the solution has not yet been implemented, please consider notifying the author. For now try [[$pagename?action=Exercises&type=random|a random type of exercise]].";
	}
	$result .= "\n\n%center%[-Generated by [[http://fabien.benetou.fr/MemoryRecalls/ImprovingPIM#PIMBasedExercises|PIM Based Exercises]].-]%%";
	$renderedresult = MarkupToHTML($pagename, $result);
	print $renderedresult.$verbatimresult;
	if ( ( isset($Author) ) && ( $counter > $score_lower_threshold ) ) {
		$score = "$counter,$Author,$type,".time()."\n";
		$write_result = file_put_contents($activityfile,$score, FILE_APPEND | LOCK_EX);
		if ($write_result < 1)
			print "There seems to be an error updating the score file, please check that $activityfile has write permission for httpd.";
	}
}

Markup("exercisesresults", "directives", "/\(:exercisesresults:\)/", ExercisesResults());
# markup to display results
#	default to all participant, not just the currently logged in user
#		hall of fame could be useful in collaborative learning wikis
#	consider first sparklines for visuals
#	remove failed before top score, try to group sessions and avoid results for the same
function ExercisesResults(){
	global $ScriptUrl, $FarmD, $Author;
	$availableexercisetypes = array("pagelink", "expressioninpage", "pagehasexpression", "historyvisualization");
	$activityfile = $FarmD."/wiki.d/.exercisesscores";
        if (file_exists($activityfile)) {
		$raw_scores = file_get_contents($activityfile);
		$allscores = explode("\n",$raw_scores);
		rsort($allscores,SORT_NUMERIC);
		//dirty, but array_filter with an lambda to test for current exercise didn't work 
		foreach ($allscores as $score ) {
			list($counter,$author,$type,$time) = explode(",",$score);
			if ($counter.$author.$type.$time != "")
				$structuredanswers[] = array("counter"=>$counter,"author"=>$author,"type"=>$type,"time"=>$time);
		}
		// could instead filter by exercise and sort by highest score, listing the top3 score per per exercise
		$past_scores .= "<table><tr>";
		foreach ($availableexercisetypes as $exercisetype){
			$past_scores .= "<td><a href=\"n=AllPages.AllPages&action=Exercises&type=$exercisetype\">$exercisetype</a></td>";
		}
		$past_scores .= "</tr><tr>";
		foreach ($availableexercisetypes as $exercisetype){
			$past_scores .= "<td valign=\"top\">";
			$answers_added = 1;
			foreach ($structuredanswers as $sa){
				if ($sa["type"]==$exercisetype && $answers_added<4){
					$past_scores .= "".$sa["counter"]." done by ".$sa["author"]." (".date("H:m m/d/y",$sa["time"]).")<br/>";
					$answers_added++;
				}
			}
			$past_scores .= "</td>";
		}
		$past_scores .= "</tr></table>";
	} else {
		$past_scores = "Currently no scores have been recorded. Consider starting an exercise first";
		// print "Go above the minimum threshold of good answers while being logged in.";
		// removed for now since the threshold is at 1
	}
	return $past_scores;
}

?>
