<?php
require __DIR__ . '/vendor/autoload.php';
include_once "php_init.php";
include_once "db_connection.php";
//include_once "db.php";
//include_once "method.php";
//include_once "IMethod.php";
//include_once "methodUtils.php";
include_once "utilsDb.php";

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

class meter implements IMethod  {
	 
	use methodUtils;
	use utilsDB;

	protected $meterName;
	protected $link;
	protected $db;

    function __construct($db) {

        $this->db = $db;
    }


	function setlink($l) {
		$this->link = $l;
	}
	function setMeterName($name='') {
		$this->meterName = $name;
	}
	function getTree2( $link, $stmt, $level, &$tag, $treeTable, &$treeroot, $where = "") {
		$lineTail = "\r\n";

		$params = array();
		$options =  array( "Scrollable" => SQLSRV_CURSOR_KEYSET );

		//echo $stmt;

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
			//echo "levelName: $levelName \r\n";
			$rectag = $levelCode;
			//$text = $levelName;
			//echo $this->meterName;
			//var_dump($row);
			$text = $levelName . " " .$row[$this->meterName]; //$levelName;

			$element = $element . jsonElement( $level, 'id', $level . $num_rows , true, false);

			$haschildren = "";
			$currwhere = $where;
			//echo "levelCode: $levelCode"  , ",   level: $level" , ",  sizeof(categroy): " . sizeof($category) . "\r\n";
			if ( $level < sizeof($category)) {
				$oldWhere = $where;

				//$where = $where . " and {$category[$level -1]} = '{$levelCode}'" ; //. $levelCode ; //($levelCode == "NULL" ? " is NULL " : "= '{$levelCode}'");
				//$where = $where . " and master_no = '{$levelCode}'" ; //. $levelCode ; //($levelCode == "NULL" ? " is NULL " : "= '{$levelCode}'");
				$where = " and master_no = '{$levelCode}'" ; //. $levelCode ; //($levelCode == "NULL" ? " is NULL " : "= '{$levelCode}'");
				$field = $category[$level -1];

				//$stmt1 = "select * from tree where upperLevelCode = '" . $levelCode . "'";
				$stmt1 =<<<QUERY
SELECT {$category[$level]}, {$this->meterName} , group_no
FROM {$treeTable} 
WHERE 1 = 1 {$where}
GROUP BY {$category[$level]}, {$this->meterName}, group_no
ORDER BY group_no
QUERY;
				//echo "level: " .  $level . ", stmt:" .  $stmt1 . $lineTail . $lineTail;

				$haschildren = $this->getTree2($link, $stmt1, $level, $rectag, $treeTable, $treeroot, $where);
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
				$element = $element . jsonElement( $level, 'levelcode',  $levelCode , true, true );
				$element = $element . jsonElement( $level, 'where',  $currwhere , true, true );
				$element = $element . jsonElement( $level, 'field',  $field , true, true );
				$element = $element . jsonElement( $level, 'text',  $text , true, true );

				$element = $element . jsonElement( $level, 'children', '', false , false);		
				$element = $element . $haschildren;
			}
			else {
				$element = $element . jsonElement( $level, 'levelcode',  $levelCode , true, true );
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
	function getTree($root, $trees, $table){
		for ( $i = 0; $i < count($trees); $i++) {

			$level = 0;
			$tag = ""; //""$category[$level];
			$category = $trees[$i]['category'];

			$stmt = <<<QUERY
SELECT {$category[0]}, {$this->meterName}, group_no
FROM {$table} 
WHERE {$category[0]} = '{$trees[$i]['name']}' 
GROUP BY {$category[0]}, {$this->meterName}, group_no
ORDER BY group_no
QUERY;


			$jsonStr = $this->getTree2( $this->link, $stmt , $level, $tag, $table, $trees[$i]);
			$ary1= json_decode( $jsonStr);

			if ( $ary1 ) {
				if ( $trees[$i]['underroot'] )
					array_push( $root[0]['children'], $ary1[0]);
				else {
					array_push( $root, $ary1[0]);
				}
			}
		}
		return $root;
	}
	function rowTrees($r, $name='watermeter_no', $underroot = true) {
		$trees = [];
		foreach( $r as $e) {
			//var_dump($e);
			//$a[] = $e['watermeter_no'];
			//$category = $e['watermeter_no'] == 'WM100' ? [$name, $name, $name]	: [$name, $name];
			$category = $e[ $name ] == 'WM100' ? [$name, $name, $name]	: [$name, $name];
			$tree = [
				'name' 			=> $e[ $name],
				"category" 		=> $category,
				"classtype" 	=> '',
				"filterfield" 	=> '',
				'underroot' 	=> $underroot
				];
			array_push($trees, $tree);
		}
		//var_dump($trees);
		return $trees;
	}
	function rootMeterTree( $table = 'watermer') {

		$stmt = <<<STMT
SELECT * 
FROM 
	{$table}
WHERE 
  --level_no = '0' and (
  master_no is null or master_no = ''
  --)
STMT;

		$trees = [];

		$r = $this->getRows($stmt);

		if ($r['status'] == 'OK' && sizeof($r['rows']) > 0 ) {
			$name = $table . '_no';
			//echo $name;
			$trees = $this->rowTrees($r['rows'], $name);	
		}
		return $trees;
	}
	function getMeterTree($table) {

		//echo $table . "\r\n";
		$root = array (
				"id"		=>  0,
				"levelType"	=>  "ROOT",
				"tag"		=>	"ROOT",
				"text"		=>  "系統架構",
				"where"		=>  "",
				"field"		=>  "category0",
				"children"	=> array()
		);
		//$table = 'watermeter';
		$trees = $this->rootMeterTree($table);
		$meter_name = $table . "_name";
		$this->setMeterName($meter_name);

		$root = array($root);

		$res =  $this->getTree($root, $trees, $table);
		//var_dump($res);
		return $res;
	}

	function getWaterMeterTree($d=null) {

		return $this->getMeterTree('watermeter');		

	}
	function getPowerMeterTree($d=null) {
		//echo 'powermeter';
		return $this->getMeterTree('powermeter');		
	}
	/*
	// orignal: $this->getWaterMeterTree():
	function getWaterMeterTree2($d=null) {

		$tree1 = array(
					'name' 			=> 'WM100',
					"category" 		=> array('watermeter_no','watermeter_no', 'watermeter_no'),
					"classtype" 	=> 'KWH',
					"filterfield" 	=> '',
					'underroot' 	=> true
					);

		$tree2 = array(
					'name' 			=> 'WM101',
					"category" 		=> array('watermeter_no','watermeter_no'),
					"classtype" 	=> 'KWH,BTU',
					"filterfield" 	=> 'category3',
					'underroot' 	=> true
					);
		$tree3 = array(
					'name' 			=> 	'WM23',
					"category" 		=>	array('watermeter_no','watermeter_no'),
					"classtype" 	=> 	'LIGHT,SOCKET,EQUIP_POWER,FAN,AIR_APPORTION',
					"filterfield" 	=> 	'category3',
					'underroot' 	=> 	true
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

		$this->setMeterName('watermeter_name');

		$root = array($root);
		$trees = array( $tree1, $tree2, $tree3);
		//$trees = array( $tree1);
		$table = 'watermeter';

		$res =  $this->getTree($root, $trees, $table);
		//var_dump($res);
		return $res;
	}
	*/

	/*
	function getPowerMeterTree2($d=null) {
		//powermeter_no	powermeter_name
		//PM_H101	H1一舍B1F總電氣室MVCB盤
		//PM_J01	J棟B1F總電氣室MVCB盤
		//PM_XH01	MVCB總盤
		$tree1 = array(
					'name' 			=> 'PM_H101',
					"category" 		=> array('powermeter_no','powermeter_no'),
					"classtype" 	=> 'KWH',
					"filterfield" 	=> '',
					'underroot' 	=> true
					);

		$tree2 = array(
					'name' 			=> 'PM_J01',
					"category" 		=> array('powermeter_no','powermeter_no'),
					"classtype" 	=> 'KWH,BTU',
					"filterfield" 	=> 'category3',
					'underroot' 	=> true
					);
		$tree3 = array(
					'name' 			=> 	'PM_XH01',
					"category" 		=>	array('powermeter_no','powermeter_no'),
					"classtype" 	=> 	'LIGHT,SOCKET,EQUIP_POWER,FAN,AIR_APPORTION',
					"filterfield" 	=> 	'category3',
					'underroot' 	=> 	true
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

		$this->setMeterName('powermeter_name');

		$root = array($root);
		$trees = array( $tree1, $tree2, $tree3);
		$table = 'powermeter';
		
		$res =  $this->getTree($root, $trees, $table);
		return $res;
	}
	*/

	function allMethod() {
    	return 
    		[ 
    			["method" => "getWaterMeterTree", "format" =>'json'],
               	["method" => "getPowerMeterTree", "format" =>'json'],
                ["method"=> 'notrun']

            ];

	}
	function method($rq) {
		//echo $rq . "\r\n";
		return $this->runMethod($rq);
	}
}


$cls = new meter($db);

//var_dump($_REQUEST);
//echo $_REQUEST['method'];
//echo 'xx';

$method = isset( $_REQUEST['method']) ? $_REQUEST['method'] : '';
$cls->setlink($link);

//echo $method . "\r\n";
if ($method != '') {
	return $cls->run();
}
else {
	if (1) {
		$res = $cls->getWaterMeterTree();
		echo json_encode($res);
	}
	/*
	else {
		$treeTable = "";


		$tree1 = array(
					'name' 			=> 'WM100',
					"category" 		=> array('watermeter_no','watermeter_no'),
					"classtype" 	=> 'KWH',
					"filterfield" 	=> '',
					'underroot' 	=> true
					);

		$tree2 = array(
					'name' 			=> 'WM101',
					"category" 		=> array('watermeter_no','watermeter_no'),
					"classtype" 	=> 'KWH,BTU',
					"filterfield" 	=> 'category3',
					'underroot' 	=> true
					);
		$tree3 = array(
					'name' 			=> 	'WM23',
					"category" 		=>	array('watermeter_no','watermeter_no'),
					"classtype" 	=> 	'LIGHT,SOCKET,EQUIP_POWER,FAN,AIR_APPORTION',
					"filterfield" 	=> 	'category3',
					'underroot' 	=> 	true
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
		//$tree = array( $tree1);
		$root = array($root);
		$table = 'watermeter';

		for ( $i = 0; $i < count($tree); $i++) {

			$level = 0;
			$tag = ""; //""$category[$level];
			$category = $tree[$i]['category'];

			$stmt = <<<QUERY
SELECT {$category[0]}, watermeter_name, group_no
FROM {$table} 
WHERE {$category[0]} = '{$tree[$i]['name']}' 
GROUP BY {$category[0]}, watermeter_name, group_no
ORDER BY group_no
QUERY;


			$jsonStr = getTree2( $link, $stmt , $level, $tag, $table, $tree[$i]);
			$ary1= json_decode( $jsonStr);

			if ( $ary1 ) {
				if ( $tree[$i]['underroot'] )
					array_push( $root[0]['children'], $ary1[0]);
				else {
					array_push( $root, $ary1[0]);
				}
			}
		}
		print json_encode($root);

		return;

	}
	*/
}

?>
