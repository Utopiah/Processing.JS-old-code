<?php

# return coeditions for a wholewiki
# http://fabien.benetou.fr/MemoryRecalls/ImprovingPIM#TimelyCoeditions

function Coeditions($groupname){

        global $ScriptUrl, $PubDir, $FarmD;
	$cache_time = 0; # set to 0 to disable cache, practical for testing
	$cache_time = 3600;
	$cachefile = "/pub/coedits.txt";
	$timewindow = 360; # in seconds
        $pagelist = ListPages("/$groupname\./e");
	// consider removing structural pages (headers, PmWiki, Site, ...)
	// 	mostly case by default since those remains into wikilib.d unless modified

	//unset($pagelist[array_search("$groupname.Template",$pagelist)]);

	#  cache since its costly
	if (!file_exists($FarmD.$cachefile)){
		if (!touch($FarmD.$cachefile))
			print "Creation of the cache file fails, check write permissions for pmWiki and $cachefile.";
	# if cache exist
	} else {
		# cache has been generated recently
		if (( (time() - filemtime($FarmD.$cachefile)) < $cache_time ) && $groupname == "")
			#return it
			return unserialize(file_get_contents($FarmD.$cachefile));
	}

	foreach ($pagelist as $pagename){
		$page = ReadPage($pagename);
		if (!$page) return;
		krsort($page); reset($page);

		foreach($page as $k=>$v) {
			if (!preg_match("/^diff:(\d+):(\d+):?([^:]*)/",$k,$match)) continue;
			$diff = intval($match[1]);
			$edits[] = array ("diff" => $diff, "name" => $pagename);
			$diffs[] = $diff;
			$names[] = $pagename;
			#automatic index since there might multiple edits with the same diff
		}
	}
	# numeric sort $edits by ascending diff (either regenerate or discard index)
	array_multisort($diffs,SORT_ASC,SORT_NUMERIC,$names);
	//var_dump($diffs,$names);
	$numberofedits = count($diffs);
	for ($j=0;$j<$numberofedits;$j++){
		if (($diffs[$j+1]-$diffs[$j])<$timewindow){
			$target = $names[$j];
			$dest = $names[$j+1];
			$coedits["$target"]["$dest"]++ ;
			# dirty since it's a string, not a proper table but will do for tests
		}
	}
	foreach ($coedits as &$ce){
		arsort($ce,SORT_NUMERIC);
		//$scoedits[] = $ce;
	}
	//var_dump($coedits);

        # should highlight the highest coedition that is not self
	# also remove under a certain threshold, e.g. 1 or rather a sigma of the distribution
	# most result are... from the same page
		# it could thus be noted then filtered
	# also the timewindow gives only short-term result
		# a second processing could check over long-term editions between pages

	#write cache
	if ($groupname == "")
		$write_result = file_put_contents($FarmD.$cachefile,serialize($coedits));	
	return $coedits;
}

?>
