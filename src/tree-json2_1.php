<?php
include_once "php_init.php";

if ( isset($__dbg_cmdline) && ! $__dbg_cmdline )
if (! isset($_SESSION['account']))
{
	echo ('<a href="login.php" class="btn btn-lg btn-success btn-block">not login yet</a>');
	exit;
}
	
function jsonElement( $level, $key, $val, $isComma, $isText) {
	$lineTail = "\r\n";
	return str_repeat("   ", $level)  . '"' . $key. '": ' . ($isText ? '"' : "")  . $val . ($isText ? '"' :"") .($isComma ? "," : ""). $lineTail ;
}

function getTree2( $link, $stmt, $level, &$tag, $treeTable, &$treeroot, $where = "") {
	$lineTail = "\r\n";
	
	$params = array();
    $options =  array( "Scrollable" => SQLSRV_CURSOR_KEYSET );
	$rs = sqlsrv_query($link, $stmt, $params, $options);
	$has = sqlsrv_has_rows( $rs );
	
	$element = "";
	
	if ($has) {
		$element = $element . str_repeat("   ", $level) . '[' . $lineTail;
	}
	else {
		return "";
	}

	$category = $treeroot['category'];
	
	$num_rows = sqlsrv_num_rows ( $rs );
	//echo "num_rows: $num_rows";
	$level++;
	$sublevel = 0;
	$uptag = $tag;
	
    while($row = sqlsrv_fetch_array($rs))
    {	
		$num_rows--;
		
		$element =  $element .  str_repeat("   ", $level -1) . '{' . $lineTail;
		$levelCode = $row[$category[$level -1]];
		
		if ( $level == 1 ) {
			$levelType = $treeroot["classtype"];
			$filterfield = $treeroot["filterfield"];
		}

		$levelName = $levelCode;
		$rectag = $levelCode;
		$text = $levelName;
		
		$element = $element . jsonElement( $level, 'id', $level . $num_rows , true, false);
		
		$haschildren = "";
		$currwhere = $where;
		if ( $level < sizeof($category)) {
			$oldWhere = $where;
			
			$where = $where . " and {$category[$level -1]} = '{$levelCode}'" ; //. $levelCode ; //($levelCode == "NULL" ? " is NULL " : "= '{$levelCode}'");
			$field = $category[$level -1];

			//$stmt1 = "select * from tree where upperLevelCode = '" . $levelCode . "'";
			$stmt1 =<<<QUERY
				select {$category[$level]} from {$treeTable} 
				where 1 = 1 {$where}
				group by {$category[$level]}
QUERY;
			//echo "level: " .  $level . ", stmt:" .  $stmt1 . $lineTail;
			
			$haschildren = getTree2($link, $stmt1, $level, $rectag, $treeTable, $treeroot, $where);
			$where = $oldWhere;
		}
		else {
			$field = $category[sizeof($category) - 1];
		}
		if ( $level == 1) {
			$element = $element . jsonElement( $level, 'levelType', $levelType , true, true );
			$element = $element . jsonElement( $level, 'filterfield', $filterfield , true, true );
			
		}
		if ( $haschildren != "") {
			$rectag = preg_replace('/^,/', "", $rectag);
			$element = $element . jsonElement( $level, 'where',  $currwhere , true, true );
			$element = $element . jsonElement( $level, 'field',  $field , true, true );
			$element = $element . jsonElement( $level, 'text',  $text , true, true );
			
			$element = $element . jsonElement( $level, 'children', '', false , false);		
			$element = $element . $haschildren;
		}
		else {
			$element = $element . jsonElement( $level, 'where',  $where , true, true );
			$element = $element . jsonElement( $level, 'field',  $field , true, true );
			$element = $element . jsonElement( $level, 'text', $text , false , true);
		}

		$element = $element . str_repeat("   ", $level -1) . '}';
		$uptag = $uptag . ($rectag == "" ? "" : ("," . $rectag));
		
		if ($num_rows > 0 )
			$element = $element . ", " . $lineTail;
    }
	
	if ($has) {
		$element = $element  .']';
		$element = $element .  $lineTail;
		$tag = $uptag;
		return  $element;
			
	}
	return "";
}	

$treeTable = "";


	//"classtype" 	=> 'LIGHT,SOCKET,EQUIP_POWER,FAN'),
	//"classtype" 	=> '照明, 插座,  設備動力,   風機')
	//"classtype" 	=> '照明,插座,設備動力,風機,空調系統分攤')

	$tree1 = array(
				'name' 			=> '電力系統用電',
				"category" 		=> array('category1', 'category3','floor', 'space_name'),
				"classtype" 	=> 'KWH',
				"filterfield" 	=> '',
				'underroot' 	=> true
				);
	$tree2 = array(
				'name' 			=> '空調系統用電',
				"category" 		=> array('category1', 'category2', 'floor', 'space_name'),
				"classtype" 	=> 'KWH,BTU',
				"filterfield" 	=> 'category3',
				'underroot' 	=> true,
                'parent'        => 0,
				);
	$tree3 = array(
				'name' 			=> 	'空間架構',
				"category" 		=>	array('category1', 'category2', 'category4', 'floor', 'space_name'),
				"classtype" 	=> 	'LIGHT,SOCKET,EQUIP_POWER,FAN,AIR_APPORTION',
				"filterfield" 	=> 	'category3',
				'underroot' 	=> 	false
				);

	$root = array (
			"id"		=>  0,
			"levelType"	=>  "ROOT",
			"tag"		=>	"ROOT",
			"text"		=>  "系統架構",
			"where"		=>  "",
			"field"		=>  "category0",
			"children"	=> array()
	);

	$tree = array( $tree1, $tree2, $tree3);
	$root = array($root);
	$table = 'equip';

    $treeArray = [];
	for ( $i = 0; $i < count($tree); $i++) {
		
		$level = 0;
		$tag = ""; //""$category[$level];
		$category = $tree[$i]['category'];

		$stmt = <<<QUERY
	SELECT {$category[0]} 
	FROM {$table} 
	WHERE {$category[0]} = '{$tree[$i]['name']}' 
	GROUP BY {$category[0]}
QUERY;
        //echo $stmt . "\r\n";

		$jsonStr = getTree2( $link, $stmt , $level, $tag, $table, $tree[$i]);
		$ary1= json_decode( $jsonStr,true);
        //echo gettype($ary1) . "\r\n";
        //var_dump($ary1[0]);
		//echo $jsonStr . "\r\n";
		if ( $ary1 ) {
			if ( $tree[$i]['underroot'] ) {
                
				//array_push( $root[0]['children'], $ary1[0]);
                
                if (!isset($tree[$i]['parent'])) {
				    //array_push( $root[0]['children'], $ary1[0]);
                }
                else {
                    // 2018/04/16 modified:
                    // 這裡是寫死的:
                    // 當 $tree[$i]['parent'] 有設定值,
                    // 而且 $tree[$i]['underroot'] 為 true 時 :
                    // 因為 $ary1 會存到 $treeArray,
                    // 將'underroot'下的兩個tree,
                    // $children: 將'current tree'($ary1) merge 到 $parent['children'] 中
                    // 然後將 $children 指定給 $parent[0]['children]
                    // 再將$parent[0] 掛到　$root[0]['children']
                    $parentNumber = $tree[$i]['parent'];
                    $parent = $treeArray[$parentNumber];
                    $parentChildren = $parent[0]['children'];
                    $currentTree = $ary1;
                    $children = array_merge($parentChildren, $currentTree);
                    
                    $parent[0]['children'] = $children;
                    array_push( $root[0]['children'], $parent[0]);
                }
                
                
            }
			else {
				array_push( $root, $ary1[0]);
			}
            array_push($treeArray, $ary1);
		}
        /*
		if ( $ary1 ) {
			if ( $tree[$i]['underroot'] )
				array_push( $root[0]['children'], $ary1[0]);
			else {
				array_push( $root, $ary1[0]);
			}
		}
        */
	}
    //echo "treeArray length: " . count($treeArray) . "\r\n";
	print json_encode($root);
		
	return $root;

?>
