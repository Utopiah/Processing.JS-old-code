<?php

//Horizontal tree to be drawn with ProcessingJS
// http://processingjs.org/learning/topic/tree
// note that once it has been done that way, the MindMap equivalent should be easy

Markup("PhylogenyVisualization","fulltext","/\\(:phylogeny:\\)(.*?)\\(:phylogenyend:\\)/mse","PhyloGenerate(PSS('$1'))");

function PhyloGenerate($sourcetree=""){
	//$sourcetree = array_reverse(explode("\n",trim($sourcetree)));
	$sourcetree = explode("\n",trim($sourcetree));
	$previous_leaf= "root";
	$previous_depth = 0;
	foreach ($sourcetree as $leaf) {
		$leaf_depth=0;
		for ($i=0;$i<10;$i++){
			//try regex on starting contiguous identical chars then count it
			// what's the difference with $leaf{$i} ?
			if ($leaf[$i]=="*") $leaf_depth++;
		}
		$proper_name = trim(substr($leaf,$leaf_depth));
		$tree["$proper_name"]["name"] = $proper_name;
		$tree["$proper_name"]["parent"] = "root";
		$tree["$proper_name"]["depth"] = $leaf_depth;
		
		if ($previous_leaf != "root"){
			//go up the tree until one finds a leaf with depth = current depth-1
			if ($leaf_depth != $tree["$previous_leaf"]["depth"]+1) {
				for ($d=count($tree)-1;$d>0;$d--){
					if ($leaf_depth == $tree[$d]+1)
						$tree["$proper_name"]["parent"] = $tree[$d]["name"];
				}
				if ($tree["$proper_name"]["parent"] == "")
					$tree["$proper_name"]["parent"] = "root";
			} else {
				$tree["$proper_name"]["parent"] = "$previous_leaf";
			}
		}
		$previous_leaf = $proper_name;
	}
	//var_dump($tree);
	return "resulting tree: ".implode("->",$tree);
}


/* Examples of phylogeny
copied to http://fabien.benetou.fr/Wiki/Visualization#Phylogeny

(:phylogeny:)
* Darwin's blind selection
** Campbell's blind selection retention
*** Chavalarias' Science phylogeny
* Bernard's homeostasis
** Turner's extended homeostasis
* Landauer's physical information
(:phylogenyend:)

*/

?>
