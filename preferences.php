<?php 
//====================================================================================
// OCS INVENTORY REPORTS
// Copyleft Pierre LEMMET 2005
// Web: http://ocsinventory.sourceforge.net
//
// This code is open source and may be copied and modified as long as the source
// code is always made freely available.
// Please refer to the General Public Licence http://www.gnu.org/ or Licence.txt
//====================================================================================
//Modified on $Date: 2007-02-08 15:53:24 $$Author: plemmet $($Revision: 1.22 $)

error_reporting(E_ALL & ~E_NOTICE);
@session_start();

define("UTF8_DEGREE", 1 );				// 0 For non utf8 database, 1 for utf8
define("GUI_VER", "4100");				// Version of the GUI
define("MAC_FILE", "files/oui.txt");	// File containing MAC database
define("TAG_LBL", "Tag");				// Name of the tag information
define("DB_NAME", "ocsweb");
define("SADMIN", 1);
define("LADMIN", 2);   
define("ADMIN", 3);
define("PC_PAR_PAGE", 15); 				// default computer / page value
define("TAG_NAME", "TAG"); 				// do NOT change
define("LOCAL_SERVER", $_SESSION["SERVEUR_SQL"]); // adress of the server handler used for local import
$_SESSION["SERVER_READ"] = $_SESSION["SERVEUR_SQL"];
//DO NOT COMMIT
$_SESSION["SERVER_WRITE"] =  $_SESSION["SERVEUR_SQL"];
unset( $_SESSION["debug"] );
$_SESSION["debug"]  = false ;
//FIN DO NOT COMMIT

if( ! function_exists ( "utf8_decode" )) {
	function utf8_decode($st) {
		return $st;
	}
}

if( !in_array( $_GET["multi"], array(20,21,26,22,24,27)) ) {
	unset( $_SESSION["queryString"] );
	foreach( $_GET as $key=>$val )
		$_SESSION["queryString"] .= "&".$key."=".$val;
}

require('dbconfig.inc.php');
require("fichierConf.class.php");

if( isset($_GET["uid"]) ) {
	setcookie( "DefNetwork", $_GET["uid"], time() + 3600 * 24 * 15 );
}

if(isset($_GET["lang"])) {
	unset( $_SESSION["fichLang"]  );
	$_SESSION["langueFich"] = "languages/".$_GET["lang"].".txt";
	unset($_SESSION["availFieldList"], $_SESSION["currentFieldList"]);
	setcookie( "lang", $_GET["lang"], time() + 3600 * 24 * 365 ); //expires in 365 days	

	if( isset( $_COOKIE["col"] ) ) {		
		foreach( $_COOKIE["col"] as $key=>$val ) {
			setcookie( "col[$key][value]", FALSE, time() - 3600 ); // deleting corresponding cookie
			setcookie( "col[$key][rang]", FALSE, time() - 3600 ); // deleting corresponding cookie			
		}
		unset( $_COOKIE["col"] );
	}
}

if( ! isset( $_SESSION["fichLang"] ) ) {
	if( isset($_COOKIE["lang"]) )
		$_SESSION["fichLang"] = new FichierConf($_COOKIE["lang"]);
	else 
		$_SESSION["fichLang"] = new FichierConf(getBrowserLang());
}
$l = $_SESSION["fichLang"];

dbconnect();
if(!isset($_SESSION["rangCookie"])) $_SESSION["rangCookie"] = 0;

// available columns
if( ! isset($_SESSION["availFieldList"]) ) {
	$_SESSION["availFieldList"] = array();
	
	$translateFields = array ( "a.".TAG_NAME=>TAG_LBL, "h.lastdate"=>$l->g(46), "h.name"=>$l->g(23), 
	"h.userid"=>$l->g(24), 	"h.osname"=>$l->g(25), "h.memory"=>$l->g(568), "h.processors"=>$l->g(569),
	"h.workgroup"=>$l->g(33), "h.osversion"=>$l->g(275), "h.oscomments"=>$l->g(286), "h.processort"=>$l->g(350), "h.processorn"=>$l->g(351),
	"h.swap"=>"Swap", "lastcome"=>$l->g(352), "h.quality"=>$l->g(353), "h.fidelity"=>$l->g(354),"h.description"=>$l->g(53), 
	"h.wincompany"=>$l->g(355), "h.winowner"=>$l->g(356), "h.useragent"=>$l->g(357), "b.smanufacturer"=>$l->g(64),
	"b.bmanufacturer"=>$l->g(284),"b.ssn"=>$l->g(36),"b.smodel"=>$l->g(65),"b.bversion"=>$l->g(209),"h.ipaddr"=>$l->g(34), "h.userdomain"=>$l->g(557));
	
	$reqAc = "SHOW COLUMNS FROM accountinfo";
	$reqB  = "SHOW COLUMNS FROM bios";
	$reqHw = "SHOW COLUMNS FROM hardware";	
	
	$resAc = mysql_query($reqAc, $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"]));
	$resB  = mysql_query($reqB, $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"]));
	$resHw = mysql_query($reqHw, $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"]));
	
	$lesCols = array();
	
	while($colname=mysql_fetch_array($resAc))
		$lesCols[] = Array( "pref"=>"a", "val"=>strtolower( $colname["Field"] ));
	while($colname=mysql_fetch_array($resB))
		$lesCols[] = Array( "pref"=>"b", "val"=>strtolower( $colname["Field"] ));
	while($colname=mysql_fetch_array($resHw))
		$lesCols[] = Array( "pref"=>"h", "val"=>strtolower( $colname["Field"] ));
	
	foreach( $lesCols as $laCol ) {
		if( in_array( $laCol["val"] , array("deviceid","id","checksum","hardware_id","etime","type") ))
			continue;

		if( isset( $translateFields[ $laCol["pref"].".".$laCol["val"] ] ) )
			$translated = $translateFields[ $laCol["pref"].".".$laCol["val"] ];
		else
			$translated = ucfirst( $laCol["val"] );
		$_SESSION["availFieldList"][$laCol["pref"].".".$laCol["val"]] = $translated;
	}
	array_multisort($_SESSION["availFieldList"]);
	
	// Registry
	/*
	$reqReg = "SELECT DISTINCT name FROM registry";
	$resReg = mysql_query($reqReg, $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"]));		
	while($colname=mysql_fetch_array($resReg)) {
		$_SESSION["availRegistry"][] = $colname["name"];
	}
	array_multisort($_SESSION["availRegistry"]);
	*/
}

if(!isset($_SESSION["currentFieldList"])) {// gui just launched
	if( isset( $_COOKIE["col"] ) ) { // columns cookie set ?
		// YES: load it
		foreach( $_COOKIE["col"] as $key=>$val ) { //sorting cookies by "rang"
			$cookSort[] = array("rang"=>$val["rang"],"name"=>$key, "value"=>urldecode($val["value"]));
			if( $val["rang"] > $_SESSION["rangCookie"] ) $_SESSION["rangCookie"] = $val["rang"];
		}
		
		sort($cookSort);
		foreach( $cookSort as $val ) { // loading ordered values
			$_SESSION["currentFieldList"][$val["name"]] = stripslashes($val["value"]);
		}
		$loadedFromCookie = true;
	}
	else {// load default values
		$_SESSION["currentFieldList"] = array("a.".TAG_NAME=>TAG_LBL,"h.lastdate"=>$l->g(46),"h.name"=>$l->g(23),
		"h.userid"=>$l->g(24),"h.osname"=>$l->g(25),"h.memory"=>$l->g(568),"h.processors"=>$l->g(569));
		$_SESSION["rangCookie"] = 0;
		foreach( $_SESSION["currentFieldList"] as $key=>$val ) {				
			setcookie( "col[$key][value]", $val, time() + 3600 * 24 * 365 ); //expires in 365 days
			setcookie( "col[$key][rang]", $_SESSION["rangCookie"], time() + 3600 * 24 * 365 ); //expires in 365 days	
			$_SESSION["rangCookie"]++;
		}
	}
		
}

if(isset($_GET["suppCol"])) { // a column must be removed
	$keyName = array_search(  stripslashes($_GET["suppCol"]), $_SESSION["currentFieldList"] ); // look for the column's key
	
	if( $keyName ) { // found
		setcookie( "col[$keyName][value]", FALSE, time() - 3600 ); // deleting corresponding cookie
		setcookie( "col[$keyName][rang]", FALSE, time() - 3600 ); // deleting corresponding cookie
		unset( $_SESSION["storedRequest"]->select[$keyName] );
		unset($_SESSION["currentFieldList"][ $keyName ]);
		if( ! isset($_COOKIE["col"]) ) { // no cookie was previously set
			$_SESSION["rangCookie"] = 0;
			foreach( $_SESSION["currentFieldList"] as $key=>$val ) {				
				setcookie( "col[$key][value]", $val, time() + 3600 * 24 * 365 ); //expires in 365 days
				setcookie( "col[$key][rang]", $_SESSION["rangCookie"], time() + 3600 * 24 * 365 ); //expires in 365 days	
				$_SESSION["rangCookie"]++;
			}
		}
	}
}
if( isset($_GET["resetcolumns"])) {
	$_SESSION["currentFieldList"] = array("a.".TAG_NAME=>TAG_LBL,"h.lastdate"=>$l->g(46),"h.name"=>$l->g(23),
	"h.userid"=>$l->g(24),"h.osname"=>$l->g(25),"h.memory"=>$l->g(568),"h.processors"=>$l->g(569));
	
	foreach( $_SESSION["availFieldList"] as $key=>$val ) {
		setcookie( "col[$key][value]", FALSE, time() - 3600 ); // deleting corresponding cookie
		setcookie( "col[$key][rang]", FALSE, time() - 3600 ); // deleting corresponding cookie
	}
		
	$_SESSION["rangCookie"] = 0;
	foreach( $_SESSION["currentFieldList"] as $key=>$val ) {				
		setcookie( "col[$key][value]", $val, time() + 3600 * 24 * 365 ); //expires in 365 days
		setcookie( "col[$key][rang]", $_SESSION["rangCookie"], time() + 3600 * 24 * 365 ); //expires in 365 days	
		$_SESSION["rangCookie"]++;
	}
}
else if(isset($_GET["newcol"])) { // new column
	$valName = stripslashes($_GET["newcol"]);
	//if( ! ereg("$Registry (.*)", $valName, $res) ) {
		$keyName = array_search( $valName, $_SESSION["availFieldList"] ); // look for the column's key
		if( $keyName ) { // found					
			$_SESSION["rangCookie"]++;		
			$value = $_SESSION["availFieldList"][$keyName];
			setcookie("col[$keyName][value]", $value, time() + 3600 * 24 * 365 ); //expires in 365 days
			setcookie("col[$keyName][rang]", $_SESSION["rangCookie"], time() + 3600 * 24 * 365 ); //expires in 365 days
			$_SESSION["currentFieldList"] = array_merge($_SESSION["currentFieldList"], array( $keyName=>$value));
			$_SESSION["storedRequest"]->select[$keyName] = $value;
		} 			
	/*}
	else {
		$_SESSION["currentRegistry"][] = $res[1];
	}*/
}

//Check current field list
if(isset( $_SESSION["currentFieldList"] ) && $loadedFromCookie ) {
	$reqAccount = mysql_query("SHOW COLUMNS FROM accountinfo", $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"]));
	while($valAccount=mysql_fetch_array($reqAccount)) {
		$fieldsAvailable[] = strtoupper($valAccount["Field"]);		
	}	
	foreach( $_SESSION["currentFieldList"] as $keyCur=>$valCur ) {
		$accField = strtolower( substr( stristr( $keyCur, "a." ), 2, strlen($keyCur) ) );
		if( $accField ) {
			if( ! in_array( strtoupper($accField), $fieldsAvailable ) ) {
				unset( $_SESSION["currentFieldList"][$keyCur] );
				setcookie( "col[$keyCur][value]", FALSE, time() - 3600 ); // deleting corresponding cookie
				setcookie( "col[$keyCur][rang]", FALSE, time() - 3600 ); // deleting corresponding cookie	
			}
		}
	}
}

$boutOver="onmouseover=\"this.style.background='#FFFFFF';\" onmouseout=\"this.style.background='#C7D9F5'\"";

function dbconnect() {
	$db = DB_NAME;
	
	$link=@mysql_connect($_SESSION["SERVER_READ"],$_SESSION["COMPTE_BASE"],$_SESSION["PSWD_BASE"]);
	if(!$link) {
		echo "<br><center><font color=red><b>ERROR: MySql connection problem<br>".mysql_error()."</b></font></center>";
		die();
	}
	if( ! @mysql_select_db($db,$link)) {
		require('install.php');
		die();
	}
		
	$link2=@mysql_connect($_SESSION["SERVER_WRITE"],$_SESSION["COMPTE_BASE"],$_SESSION["PSWD_BASE"]);
	if(!$link2) {
		echo "<br><center><font color=red><b>ERROR: MySql connection problem<br>".mysql_error($link2)."</b></font></center>";
		die();
	}

	if( ! @mysql_select_db($db,$link2)) {
		require('install.php');
		die();
	}
	
	$_SESSION["writeServer"] = $link2;	
	$_SESSION["readServer"] = $link;
	return $link2;
}

function ShowResults($req,$sortable=true,$modeCu=false,$modeRedon=false,$deletableP=true,$registrable=false,$teledeploy=false, $affect=false)
{
		global $l, $_GET;				
		$deletable = ($_SESSION["lvluser"]==SADMIN) && $_GET["multi"]!=2 && $deletableP;		
		
		global $pcparpage;
		$columneditable = $req->columnEdit;		
		
		if( ! $affect )
			unset( $_SESSION["saveRequest"] );
		$ind=0;
		$var="option".$ind;		

		while(isset($_POST[$var]))
		{					
			if($req->isNumber[$ind]) 	
				$_POST[$var]=0+$_POST[$var];// si un nombre est attendu, on transforme en nombre
			
			$req->where=str_replace("option$ind",$_POST[$var],$req->where); 
			// on remplace les strings "optionX" de la requete par leurs valeurs pr�sentes dans les variables en POST
			$ind++;
			$var="option".$ind;			
		}			
			
		if(    isset($_SESSION["storedRequest"])   && (  (isset($_GET["c"])&&$_GET["c"]) || $_GET["av"] == 1 || $_GET["suppCol"] == 1 || isset($_GET["newCol"]))   ) 
		{
			if($_SESSION["c"]==$_GET["c"]) { // If same column is sorted again
				if( $_GET["rev"] == 1 )
					$_GET["a"]= $_GET["a"] ? 0 : 1 ;
			}
			else {
				$_SESSION["c"]=$_GET["c"];
				$_GET["a"]= 1;
			}

			$suffixe = $_GET["a"] ? " ASC" : " DESC";		

			$count = getCount( $_SESSION["storedRequest"] );				
			$pcParPage = $modeCu ? $count : $pcparpage ;
			$pcParPage = $pcParPage>0 ? $pcParPage : PC_PAR_PAGE;
			$numPages = ceil($count/$pcParPage);	

			if( $numPages == 0 )
				$numPages++;
			
			if( $_SESSION["pageCur"] > $numPages ){
				$_SESSION["pageCur"] = $numPages ;
			}
			
			$beg = ($_SESSION["pageCur"]-1) * $pcParPage;				
		
			if ( $_GET["c"] ) {
				$ord = stripslashes($_GET["c"]).$suffixe;
				$_SESSION["storedRequest"]->order = $ord;
				$toExec = getQuery( $_SESSION["storedRequest"], " {$beg},".$pcParPage ) ;
			}
			else {
				$toExec = getQuery( $_SESSION["storedRequest"], " {$beg},".$pcParPage ) ;
			}
		}
		else
		{
			$count = getCount($req);
			$pcParPage = $modeCu ? $count : $pcparpage ;	
			$pcParPage = $pcParPage>0 ? $pcParPage : PC_PAR_PAGE;			
			$numPages = ceil($count/$pcParPage);	

			if( $numPages <= 0 )
				$numPages++;
			
			if( $_SESSION["pageCur"] > $numPages || ! $_SESSION["pageCur"]){
				$_SESSION["pageCur"] = $numPages ;
			}

			if( $columneditable && ! $req->order )
				$orderDefault = isset($_SESSION["currentFieldList"]["h.lastdate"]) ? " h.lastdate DESC" : " 1 ASC";
			
			$beg = ($_SESSION["pageCur"]-1) * $pcParPage;
			if( ! $req->order ) $req->order = $orderDefault;
			
			$toExec = getQuery( $req, " {$beg},".$pcParPage ) ;
			$_SESSION["storedRequest"]=$req;
		}
		
		$result = mysql_query( $toExec, $_SESSION["readServer"] ) or die(mysql_error($_SESSION["readServer"]));	
				
		//les GET a rajouter
		$pref="";
		foreach ($_GET as $gk=>$gv) {
			if($gk=="page" || $gk=="rev" || $gk == "logout" || $gk=="c"|| $gk=="direct"|| $gk=="supp" || $gk=="a"|| $gk=="suppCol" || $gk=="newcol" || $gk=="resetcolumns") continue;
			$pref .= "&{$gk}=".urlencode($gv);
		}
		
		//les GET globaux a rajouter
		$prefG="";
		$hiddens ="";
		foreach ($_GET as $gk=>$gv){
		
			if( $gk=="rev"|| $gk=="suppCol"|| $gk=="supp" || $gk == "logout" || $gk=="newcol" || $gk=="page" || $gk=="resetcolumns") continue;
			
			if( $gk =="page" && ($gv==-1 || $gv==-2)) {
				$gv = $_SESSION["pageCur"];
			}

			$prefG .= "&{$gk}=".urlencode(stripslashes($gv));				
			$hiddens .= "<input type='hidden' name='$gk' value='".stripslashes($gv)."'>\n";
		}		

		if( !$modeCu && $count > 0) {
			$largeur = $columneditable ? 33 : 50;
			echo "<br><center><table width='72%' border='0'><tr><td align='center' width='$largeur%'><b>".$count." ".$l->g(90)."</b>";		
					
			echo "<br>&nbsp;&nbsp;<a href=ipcsv.php target=_blank>(".$l->g(183).")</a></td>";
				
			$machNmb = array(5,10,15,20,50,100);			
			
			echo "<td align='center' align='center' width='$largeur%'><form name='pcp' method='GET' action='index.php'>$hiddens".$l->g(340).
			":&nbsp;<select name='pcparpage' OnChange='pcp.submit();'>";
			
			foreach( $machNmb as $nbm ) {
				$countHl++;
				echo "<option".($countHl%2==1?" class='hi'":"").($_SESSION["pcparpage"] == $nbm ? " selected" : "").">$nbm</option>";
			}
			
			echo "</select></form></font></td>";
			
			if( $columneditable) {
				echo "<td align='center' align='center' width='$largeur%'><form name='addCol' method='GET' action='index.php'>";
				echo $hiddens;							
				echo "<select name='newcol' OnChange='addCol.submit();'>";			
				echo "<option>".$l->g(349)."</option>";
				
				foreach( $_SESSION["availFieldList"] as $nomField=>$valField ) {
					if( ! in_array($valField,$_SESSION["currentFieldList"])	) {
						$countHl++;
						echo "<option".($countHl%2==1?" class='hi'":"").">$valField</option>";						
					}
				}
				/*foreach( $_SESSION["availRegistry"] as $nomField=>$valField ) {
					if( ! in_array($valField,$_SESSION["currentRegistry"])	) {
						$countHl++;
						echo "<option".($countHl%2==1?" class='hi'":"").">Registry $valField</option>";						
					}
				}*/
				echo "</select><input type='submit' name='resetcolumns' value='".$l->g(396)."'></form></td>";
			}
			
			echo "</tr></table></center>";
		}
		
		if($modeRedon) {
			echo "<form id='idredon' name='redon' action='index.php?multi=6&c=".urlencode(stripslashes($_GET["c"]))."&a=".urlencode($_GET["a"])."' method='POST'>
				<p align='center'><input name='subredon' value='".$l->g(177)."' type='submit'></p>";
		}
				
		$cpt=1;
		printNavigation( $prefG, $numPages);	
		echo "<table BGCOLOR='#C7D9F5' BORDER='0' WIDTH = '95%' ALIGN = 'Center' CELLPADDING='0' BORDERCOLOR='#9894B5'>
		<tr BGCOLOR='#C7D9F5'>";		
		if($modeRedon)
			echo "<td>&nbsp;</td>";
		
		if(!isset($_GET["c"])) {			
			$_SESSION["storedRequest"]=$req;	
		}
		else
			$_SESSION["c"]=$_GET["c"];		
			
		if($deletable)
		{?>
			<script language=javascript>
				function confirme(did, typ, nam)
				{
					if(confirm("<?php echo $l->g(119)?> "+(nam!=""?nam:did)+" ?"))
						if(typ == 1)
							window.location="index.php?<?php echo $pref?>&c=<?php echo (isset($_GET["c"])?urlencode($_GET["c"]):"1")?>&a=<?php echo (isset($_GET["a"])?urlencode($_GET["a"]):0); ?>&page=<?php echo urlencode($_GET["page"])?>&suppnet="+did;
						else
							window.location="index.php?<?php echo $pref?>&c=<?php echo (isset($_GET["c"])?urlencode($_GET["c"]):"1")?>&a=<?php echo (isset($_GET["a"])?urlencode($_GET["a"]):0); ?>&page=<?php echo urlencode($_GET["page"])?>&supp="+did;
				}
			</script>
		<?php 

		}
		if( $req->countId == "h.id" ) //computer in devices
			echo "<td width='15px'>&nbsp;</td>";
			
		while($colname=mysql_fetch_field($result)) // On r�cup�re le nom de toutes les colonnes		
		{
			if($colname->name!="h.id"&&$colname->name!="deviceid")
			{							
				$a = ( isset($_GET["a"]) ? $_GET["a"] : 0 ) ;
				$isDate[$colname->name] = ($colname->type == "date" ? 1 : 0);
				$isDateTime[$colname->name] =($colname->type == "datetime" || $colname->type == "timestamp" ? 1 : 0);

				if($sortable) {	
					if( $_SESSION["storedRequest"]->countId == "h.id" && ($_SESSION["storedRequest"]->group == "" || ! in_array($_SESSION["storedRequest"]->group, array("h.id","s.name","a.tag") ) )) { // NO group by clause
						
						$vraiNomChamp = array_search( $colname->name, $_SESSION["currentFieldList"] );
						
						/*if( ! $vraiNomChamp ) {
							$ind = array_search( $colname->name, $_SESSION["currentRegistry"] );
							$vraiNomChamp = urlencode("\"".$_SESSION["currentRegistry"][$ind]."\"");
						}*/
								
						if( ! $vraiNomChamp )
							$vraiNomChamp = array_search( $colname->name, $_SESSION["storedRequest"]->select );							
						}
					else					
						$vraiNomChamp = "\"".$colname->name."\"";
					//echo "if( $vraiNomChamp == ".$_SESSION["c"]." || \"\'\".".$colname->name.".\"\'\" == ".$_SESSION["c"]." ) \";";
					echo "<td align='center'><table><tr><td>";
					$hrefSort = "<a href=index.php?$pref&c=".urlencode($vraiNomChamp)."&a=$a&rev=1&page=1>";
					if( isset($_SESSION["c"]) && ($vraiNomChamp == $_SESSION["c"] || "\'".$colname->name."\'" == $_SESSION["c"]) ) {
						if($a == 1 ) 
							echo "$hrefSort<img src='image/down.png'></a></td>";
						else 
							echo "$hrefSort<img src='image/up.png'></a></td>";
					}
					else {
						ereg( "\"? *([^\"]*)\"? (DESC|ASC)", $_SESSION["storedRequest"]->order, $res);
						//echo "colname:".$colname->name."vrainomchamp:".$vraiNomChamp;
						//var_dump( $res );	
						
						if( $res[1] == $colname->name || $res[1] == $vraiNomChamp )
							if( $res[2] == "ASC" ) 
								echo "$hrefSort<img src='image/down.png'></a></td>";
							else 
								echo "$hrefSort<img src='image/up.png'></a></td>";
					}
					echo "<td><B>{$hrefSort}{$colname->name}</b></a></td>";
					
					
					if( sizeof($_SESSION["storedRequest"]->select)>3 && $columneditable && in_array( $colname->name , $_SESSION["currentFieldList"]))						
						echo "<td><a href=index.php?page=1&$prefG&suppCol=".urlencode($colname->name).">&nbsp;<img src=image/supp.png></td>";
					echo "</tr></table></td>"; // Affichage en tete colonne*/
					
				}
				else
				   echo "<td><CENTER><B>$colname->name</CENTER></td>"; // Affichage en tete colonne
				
				$tabChamps[$cpt]=$colname->name;
			}			
			$cpt++;
		}
		
		if( ($deletable||$modeRedon) && $req->countId == "h.id" ) //computer in devices
		{
			echo "<td>&nbsp;</td>";
		}
		if( $teledeploy ) {
			echo "<td align='center' width='50px'><b>".$l->g(432)."</b></td>
			<td width='50px' align='center'><b>".$l->g(572)."</b></td>
			<td width='50px' align='center'><b>".$l->g(573)."</b></td>
			<td align='center'><b>".$l->g(574)."</b></td>
			<td align='center'><b>".$l->g(431)."</b></td>
			<td>&nbsp;</td>";	
		}
		else if( $affect ) {
			if( $affect == 2 )
				echo "<td><b>".$l->g(122)."</b></td>";
			if( $affect != 2 )
				echo "<td align='center'><b>".$l->g(433)."</b></td>";
		}

		echo "</tr>";
		$x=-1; $nb=0;
		$uneMachine=false;

		while($item = mysql_fetch_array($result)) // Parcour de toutes les lignes r�sultat
		{	
			flush();
			echo "<TR height=20px ". ($x == 1 ? "bgcolor='#FFFFFF'" : "bgcolor='#F2F2F2'") .">";	// on alterne les couleurs de ligne
			$x = ($x == 1 ? 0 : 1) ;	
			$nb++;
			if($modeRedon) {
				echo "<td align=center><input type=checkbox name='ch$nb' value='".urlencode($item["h.id"])."'></td>";
			}		
					
			if( $req->countId == "h.id" ) { //computer in devices
					$resDev = @mysql_query("SELECT * FROM devices WHERE (name<>'IPDISCOVER' || ivalue<>1) AND hardware_id = ".$item["h.id"], $_SESSION["readServer"]);
					/*if( mysql_num_rows( $resDev ) > 0)
						echo "<td align='center' valign='center'><img width='15px' src='image/red.png'></td>";*/
					if( mysql_num_rows( $resDev ) > 0) {
						// Red button tooltip
						unset( $optTooltip );
						while($valTooltip=mysql_fetch_array($resDev,MYSQL_ASSOC)) {
							$optTooltip[ $valTooltip["NAME"] ][ "IVALUE" ] = $valTooltip["IVALUE"];
							$optTooltip[ $valTooltip["NAME"] ][ "TVALUE" ] = $valTooltip["TVALUE"];
						}
						$ttText = "";						
						if( isset( $optTooltip["FREQUENCY"] )) {
							$ttText .= " - ".$l->g(494);
							/*if( $optTooltip["FREQUENCY"]["IVALUE"]==0 ) $ttText .= $l->g(485);
							else if( $optTooltip["FREQUENCY"]["IVALUE"]==-1 ) $ttText .= $l->g(486);
							else $ttText .= $td3.$l->g(495)." ".$optPerso["FREQUENCY"]["IVALUE"]." ".$l->g(496);*/
						}
						if( isset( $optTooltip["DOWNLOAD"] )) {
							$ttText .= " - ".$l->g(558);
						}
						if( isset( $optTooltip["IPDISCOVER"] )) {
							$ttText .= " - ".$l->g(557);
						}
						$ttText = strtr(htmlspecialchars( $ttText ), "\"","'");
						echo "<td align='center' valign='center'><img width='15px' title=\"$ttText\" alt=\"$ttText\" src='image/red.png'></td>";
					}
					else
						echo "<td>&nbsp;</td>";
			}			
	
			foreach($tabChamps as $chmp) {// Affichage de toutes les valeurs r�sultats
				echo "<td align='center'>";								
				if($chmp==TAG_LBL)
				{
					$leCuPrec=$item[$chmp];					
				}
				else if($chmp==$l->g(23) && isset($item["h.id"]))
				{					
					echo "<a href=\"machine.php?sessid=".session_id()."&systemid=".urlencode($item["h.id"])."\" target=\"_new\" onmouseout=\"this.style.color = 'blue';\" onmouseover=\"this.style.color = '#ff0000';\">";
					$uneMachine=true;
				}
				else if($chmp==$l->g(28))
				{
					echo "<a href='?cuaff=$leCuPrec'>";
				}
				
				if( $isDate[$chmp] )
					echo dateFromMysql($item[$chmp])."</span></a></font></td>\n";
				else if( $isDateTime[$chmp] )
					echo dateTimeFromMysql($item[$chmp])."</span></a></td>\n";				

				else if(!$toutAffiche)
					echo $item[$chmp]."</span></a></font></td>\n";
				
			}
			
			if( $deletable && isset($item["h.id"]) ) {				
				echo "<td align=center><a href='#' OnClick='confirme(\"".$item["h.id"]."\",0,".(isset($item[$l->g(23)])?"\"".htmlentities($item[$l->g(23)])."\"":"\"\"").");'><img src=image/supp.png></a></td>";
			}
			else if( $deletable && isset($item[$l->g(95)]) ) {
				echo "<td align=center><a href='#' OnClick='confirme(\"".$item[$l->g(95)]."\",1,\"\");'><img src=image/supp.png></a></td>";
			}
			
			if( $teledeploy ) {
				$resNot = mysql_query("SELECT COUNT(id) as 'nb' FROM devices d, download_enable e WHERE e.fileid='".$item["Timestamp"]."'
 AND e.id=d.ivalue AND name='DOWNLOAD' AND tvalue IS NULL", $_SESSION["readServer"]);
				$resSucc = mysql_query("SELECT COUNT(id) as 'nb' FROM devices d, download_enable e WHERE e.fileid='".$item["Timestamp"]."'
 AND e.id=d.ivalue AND name='DOWNLOAD' AND tvalue LIKE 'SUCCESS%'", $_SESSION["readServer"]);
				$resErr = mysql_query("SELECT COUNT(id) as 'nb' FROM devices d, download_enable e WHERE e.fileid='".$item["Timestamp"]."'
 AND e.id=d.ivalue AND name='DOWNLOAD' AND tvalue LIKE 'ERROR%'", $_SESSION["readServer"]);
				$resTot = mysql_query("SELECT COUNT(id) as 'nb' FROM devices d, download_enable e WHERE e.fileid='".$item["Timestamp"]."'
 AND e.id=d.ivalue AND name='DOWNLOAD'", $_SESSION["readServer"]);
 
				$valNot = mysql_fetch_array( $resNot );
				$valSucc = mysql_fetch_array( $resSucc );
				$valErr = mysql_fetch_array( $resErr );
				$valTot = mysql_fetch_array( $resTot );
				
				echo "<td align='center'>".$valNot["nb"]."</td><td align='center'><font color='green'>".$valSucc["nb"]."</font></td><td align='center'><font color='red'>".$valErr["nb"]."</font></td>";
				echo "<td align='center'>";
				if( $valTot["nb"] > 0 )
					echo "<a OnClick='window.open(\"tele_stats.php?sessid=".session_id()."&stat=".$item[0]."\",\"fenstat".$item[0]."\",\"location=0,status=0,scrollbars=0,menubar=0,resizable=0,width=850,height=600\")' 
 href='javascript:void(0);'><img src='image/cal.gif'></a>";
				else
					echo "<b>-</b>";
					
				echo "</td><td align='center'><a href='index.php?multi=21&actpack=".$item[0]."'><img src='image/Gest_admin1.png'></a></td><td align='center'><a href=# OnClick='javascript:ruSure(\"index.php?multi=21&suppack=".$item[0]."\")'><img src=image/supp.png></a></td>";	
			}
			else if( $affect ) {
				if( $affect == 2 ) {
					echo "<td align='center'><a href=# OnClick='javascript:ruSure(\"index.php?multi=".($affect==2?26:24)."&suppack=".$item[0]."\")'><img src=image/supp.png></a></td>";
					//echo "<td align='center'><a href=# OnClick='javascript:ruSure(\"index.php?multi=".($affect==2?26:24)."&suppack=".$item[0]."&nonnot=1\")'><img src=image/suppv.png></a></td>";
				}
				if( $affect != 2)
					echo "<td align='center'><a href=# OnClick='javascript:ruSure(\"index.php?".($affect==3?"systemid=".$_GET["systemid"]."&":"")."multi=24&affpack=".$item[0]."\")'><img src='image/Gest_admin1.png'></a></td>";	
			}
						
			if( $registrable &&  isset($item["mac"]) )
				echo "<td align=center><a href=index.php?multi=3&mode=8&mac=".$item["mac"]."><img src='image/Gest_admin1.png'></a></td>";
			echo "</tr>";
		}	
		
		echo"</td></tr></table>";
		if($modeRedon) {
			echo "<input name='maxredon' type='hidden' value='$nb'></form>";
		}
		
		if($x==-1)
		{
			echo "<div align=center><b>".$l->g(42)."</b></div>";
		}			
		
		if( $uneMachine ) {			
			if( $_SESSION["lvluser"]==SADMIN ) {
				echo "<br><center><b>".$l->g(430).":</b>";			
				echo "&nbsp;&nbsp;<b><a href='index.php?multi=22' target=_top>".$l->g(429)."</a></b>";
				//echo "&nbsp;&nbsp;<b><a href='index.php?multi=23' target=_top>".$l->g(312)."</a></b>";
				echo "&nbsp;&nbsp;<b><a href='index.php?frompref=1&multi=24' target=_top>".$l->g(428)."</a></b>";
				echo "&nbsp;&nbsp;<b><a href='index.php?multi=27' target=_top>".$l->g(122)."</a></b>";			
				echo "</center>";
			}
		}		
		printNavigation( $prefG, $numPages);
		return $nb;
}

function getCount( $req ) {

	$ech = $_SESSION["debug"];
	$reqCount = "SELECT count(distinct ".$req->countId.") AS cpt FROM ".$req->from.($req->fromPrelim?",":"").$req->fromPrelim;
	if( $req->where )
		$reqCount .= " WHERE ".$req->where;
		
	if($ech) echo "<br><font color='red'><b>$reqCount</b></font><br><br>";
	$resCount = mysql_query($reqCount, $_SESSION["readServer"]);
	$valCount = mysql_fetch_array($resCount);
	
	return $valCount["cpt"];
}

function getPrelim(  $req, $limit=NULL ) {
	$ech = $_SESSION["debug"];
	$rac = "LEFT JOIN accountinfo a ON a.hardware_id=h.id";
	$selectReg = "";
	//	$selectFin = $req->getSelect();
	//$fromFin = $req->from;
	$cpt = 1;
	/*if( is_array($_SESSION["currentRegistry"]) )
		foreach( $_SESSION["currentRegistry"] as $regist ) {
			$selectReg .= ", regAff{$cpt}.regvalue AS \"$regist\"";
			$fromReg.= "LEFT JOIN registry regAff{$cpt} ON regAff{$cpt}.hardware_id=h.id";
			if( $cpt > 1 )
				$whereReg .= " AND ";
			$whereReg .= "regAff{$cpt}.name='".$regist;		
			$cpt ++;
		}*/	
	
	$selPrelim = $req->getSelectPrelim();
	$fromPrelim = $req->from;	
	
	$reqPrelim = "SELECT $selPrelim FROM ".$fromPrelim.($req->fromPrelim?",":"").$req->fromPrelim;
	if( $req->where ) $reqPrelim .= " WHERE ".$req->where; 
	if( $req->group ) $reqPrelim .= " GROUP BY ".$req->group;
	
	// bidouille
	if( strstr( $req->order, "ipaddr" ) ) {
		if( strstr( $req->order, "DESC" ) ) {
			$order = "inet_aton(h.ipaddr) DESC";
		}
		else {
			$order = "inet_aton(h.ipaddr) ASC";
		}
	}
	else
		$order = $req->order;
		
	if( $req->order ) $reqPrelim .= " ORDER BY ".$order;
	
	
	if( $limit ) $reqPrelim .= " LIMIT ".$limit;
	
	if($ech) echo "<br><font color='green'><b>$reqPrelim</b></font><br><br>";
	flush();
	return $reqPrelim;
}

function getQuery( $req, $limit ) {
	
	$ech = $_SESSION["debug"];
	$resPrelim = mysql_query( getPrelim( $req, $limit ) , $_SESSION["readServer"]);
	
	$selFin = $req->getSelect();
	$fromFin = $req->from ;	
	
	$toExec = "SELECT ".$selFin." FROM ".$fromFin;
	$prem = true;
	
	while( $valPrelim = mysql_fetch_array($resPrelim) ) {
		if( !$prem) $lesIn .= ",";

		$lesIn .= "'".addslashes($valPrelim[$req->linkId])."'";
		$prem = false;
	}
	
	if( !$prem ) {
		$toExec .= " WHERE ".$req->whereId." IN($lesIn) ";	
		if( $req->selFinal )
			$toExec .= $req->selFinal;
	}
	else
		$toExec .= " WHERE 1=0";
	
	if( $req->group ) $toExec .= " GROUP BY ".$req->group;
	// bidouille
	if( strstr( $req->order, "ipaddr" ) ) {
		if( strstr( $req->order, "DESC" ) ) {
			$order = "inet_aton(h.ipaddr) DESC";
		}
		else {
			$order = "inet_aton(h.ipaddr) ASC";
		}
	}
	else
		$order = $req->order;
		
	if( $req->order ) $toExec .= " ORDER BY ".$order;
	
	if($ech) echo "<br><font color='blue'><b>$toExec</b></font><br><br>";
	flush();
	return $toExec;
}

function printEnTete($ent) {
	echo "<br><table border=1 class= \"Fenetre\" WIDTH = '62%' ALIGN = 'Center' CELLPADDING='5'>
	<th height=40px class=\"Fenetre\" colspan=2><b>".$ent."</b></th></table>";
}

function dateOnClick($input, $checkOnClick=false) {
	global $l;
	$dateForm = $l->g(269) == "%m/%d/%Y" ? "MMDDYYYY" : "DDMMYYYY" ;
	if( $checkOnClick ) $cOn = ",'$checkOnClick'";
	$ret = "OnClick=\"javascript:NewCal('$input','$dateForm',false,24{$cOn});\"";
	return $ret;
}

function datePick($input, $checkOnClick=false) {
	global $l;
	$dateForm = $l->g(269) == "%m/%d/%Y" ? "MMDDYYYY" : "DDMMYYYY" ;
	if( $checkOnClick ) $cOn = ",'$checkOnClick'";
	$ret = "<a href=\"javascript:NewCal('$input','$dateForm',false,24{$cOn});\">";
	$ret .= "<img src=\"image/cal.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"Pick a date\"></a>";
	return $ret;
}

function dateFromMysql($v) {
	global $l;
	
	if( $l->g(269) == "%m/%d/%Y" )
		$ret = sprintf("%02d/%02d/%04d", $v[5].$v[6], $v[8].$v[9], $v);
	else	
		$ret = sprintf("%02d/%02d/%04d", $v[8].$v[9], $v[5].$v[6], $v);
	return $ret;
}

function dateTimeFromMysql($v) {
	global $l;
	
	if( $l->g(269) == "%m/%d/%Y" )
		$ret = sprintf("%02d/%02d/%04d %02d:%02d:%02d", $v[5].$v[6], $v[8].$v[9], $v, $v[11].$v[12],$v[14].$v[15],$v[17].$v[18]);
	else	
		$ret = sprintf("%02d/%02d/%04d %02d:%02d:%02d", $v[8].$v[9], $v[5].$v[6], $v, $v[11].$v[12],$v[14].$v[15],$v[17].$v[18]);
	return $ret;
}

function dateToMysql($date_cible) {

	global $l;
	if(!isset($date_cible)) return "";
	
	$dateAr = explode("/", $date_cible);
	
	if( $l->g(269) == "%m/%d/%Y" ) {
		$jour  = $dateAr[1];
		$mois  = $dateAr[0];
	}
	else {
		$jour  = $dateAr[0];
		$mois  = $dateAr[1];
	}

	$annee = $dateAr[2];
	return sprintf("%04d-%02d-%02d", $annee, $mois, $jour);	
}

function getBrowser() {
	$bro = $_SERVER['HTTP_USER_AGENT'];
	if( strpos ( $bro, "MSIE") === false ) {
		return "MOZ";
	}
	return "IE";
}

function getBrowserLang() {
	$bro = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
	if (strpos( $bro,"de") === false) {
           // Not german
           if (strpos( $bro,"es") === false) {
               // Not spanish
               if (strpos( $bro,"fr") === false) {
                   // Not french
                   if (strpos( $bro,"it") === false) {
                       // Not italian
                       if (strpos( $bro,"pt-br") === false) {
                           // Not brazilian portugueuse
                           if (strpos( $bro,"pt") === false) {
                               // Not portugueuse
                               if (strpos( $bro,"pl") === false) {
                                  // Not polish
                                  // Use english default language
	                           return "english";
                               }
                               else
                                  // Polish
                                  return "polish";
                           }
                           else
                               // Portuguese
		                 return "portuguese";
                       }
                       else
                           // Brazilian portuguese
		             return "brazilian_portuguese";
                   }
                   else
                       // Italian
		         return "italian";
               }
               else
                   // French
		     return "french";
           }
           else
               // Spanish
               return "spanish";
       }
       else
           // German
	    return "german";
}



function printNavigation( $lesGets, $numPages) {
				
		$prefG = "<a href=index.php?".stripslashes($lesGets)."&page=";
		echo "<p align='center'>";
		if( $numPages > 1 ) {			
			if( $_SESSION["pageCur"] == 1) {				
				echo "&nbsp;&nbsp;";//voir gris�
				echo "&nbsp;&nbsp;1&nbsp;..";							
			} else {
				echo "&nbsp;&nbsp;{$prefG}-1><img src='image/prec24.png'></a>";
				echo "&nbsp;{$prefG}1>1</a>&nbsp;..";			
			}
			
			if( $_SESSION["pageCur"] && $_SESSION["pageCur"]>1 && $_SESSION["pageCur"]!=$numPages ) {
				echo  "&nbsp;".$_SESSION["pageCur"]."&nbsp;";
			}
			
			if( $_SESSION["pageCur"] >= $numPages) {
				echo "..&nbsp;&nbsp;$numPages&nbsp;";
				//echo "<img src='image/proch24.png'>&nbsp;&nbsp;"; voir gris�
			} else {
				echo "..&nbsp;{$prefG}$numPages>$numPages</a>&nbsp;";
				echo "{$prefG}-2><img src='image/proch24.png'></a>&nbsp;&nbsp;";
			}
		}
		echo "</p><br>";
}

function deleteNet($id) {
	mysql_query("DELETE FROM network_devices WHERE macaddr='$id';", $_SESSION["writeServer"]);
}

function deleteDid($id, $checkLock = true, $traceDel = true) {
	global $l;
	
	if( ! $checkLock || lock($id) ) {
	
		$resId = mysql_query("SELECT deviceid FROM hardware WHERE id='$id'",$_SESSION["readServer"]) or die(mysql_error());
		$valId = mysql_fetch_array($resId);
		$idHard = $id;
		$did = $valId["deviceid"];
		if( $idHard ) {	
			if( strpos ( $did, "NETWORK_DEVICE-" ) === false ) {
				$resNetm = @mysql_query("SELECT macaddr FROM networks WHERE hardware_id=$idHard", $_SESSION["writeServer"]) or die(mysql_error());
				while( $valNetm = mysql_fetch_array($resNetm)) {
					@mysql_query("DELETE FROM netmap WHERE mac='".$valNetm["macaddr"]."';", $_SESSION["writeServer"]) or die(mysql_error());
				}		
			}
			
			$tables=Array("accesslog","accountinfo","bios","controllers","drives",
			"inputs","memories","modems","monitors","networks","ports","printers","registry",
			"slots","softwares","sounds","storages","videos","devices","download_history");	
			
			echo "<center><font color=red><b>$did ".$l->g(220)."</b></font></center>";
			
			foreach ($tables as $table) {
				mysql_query("DELETE FROM $table WHERE hardware_id=$idHard;", $_SESSION["writeServer"]) or die(mysql_error());		
			}
			mysql_query("DELETE FROM hardware WHERE id=$idHard;", $_SESSION["writeServer"]) or die(mysql_error());		
			//TRACE_DELETED
			if($traceDel && mysql_num_rows(mysql_query("SELECT IVALUE FROM config WHERE IVALUE>0 AND NAME='TRACE_DELETED'", $_SESSION["writeServer"]))){
				mysql_query("insert into deleted_equiv(DELETED,EQUIVALENT) values('$did',NULL)", $_SESSION["writeServer"]) or die(mysql_error());
			}
		}
		if( $checkLock ) 
			unlock($id);
	}
	else
		errlock();
}

function lock($id) {
	//echo "<br><font color='red'><b>LOCK $id</b></font><br>";
	$reqClean = "DELETE FROM locks WHERE unix_timestamp(since)<(unix_timestamp(NOW())-60)";
	$resClean = mysql_query($reqClean, $_SESSION["writeServer"]) or die(mysql_error());
	
	$reqLock = "INSERT INTO locks(hardware_id) VALUES ('$id')";
	if( $resLock = mysql_query($reqLock, $_SESSION["writeServer"]) or die(mysql_error()))
		return( mysql_affected_rows ( $_SESSION["writeServer"] ) == 1 );
	else return false;
}

function unlock($id) {
	//echo "<br><font color='green'><b>UNLOCK $id</b></font><br>";
	$reqLock = "DELETE FROM locks WHERE hardware_id='$id'";
	$resLock = mysql_query($reqLock, $_SESSION["writeServer"]) or die(mysql_error());
	return( mysql_affected_rows ( $_SESSION["writeServer"] ) == 1 );
}

function errlock() {
	global $l;
	echo "<br><center><font color=red><b>".$l->g(376)."</b></font></center><br>";
}

function incPicker() {

	global $l;
	echo "<script language=\"javascript\">
	var MonthName=[";
	
	for( $mois=527; $mois<538; $mois++ )
		echo "\"".$l->g($mois)."\",";
	echo "\"".$l->g(538)."\"";
	
	echo "];
	var WeekDayName=[";
	
	for( $jour=539; $jour<545; $jour++ )
		echo "\"".$l->g($jour)."\",";
	echo "\"".$l->g(545)."\"";	
	
	echo "];
	</script>	
		<script language=\"javascript\" type=\"text/javascript\" src=\"js/datetimepicker.js\">
	</script>";
}

	function loadMac() {
		if( $file=@fopen(MAC_FILE,"r") ) {			
			while (!feof($file)) {				 
				$line  = fgets($file, 4096);
				if( preg_match("/^((?:[a-fA-F0-9]{2}-){2}[a-fA-F0-9]{2})\s+\(.+\)\s+(.+)\s*$/", $line, $result ) ) {
					$_SESSION["mac"][strtoupper(str_replace("-",":",$result[1]))] = $result[2];
				}				
			}
			fclose($file);			
		}
	}
	
	function getConstructor( $mac ) {	
		$beg = strtoupper(substr( $mac, 0, 8 ));
		return ( ucwords(strtolower( $_SESSION["mac"][ $beg ])) );
	}
	
	function textDecode( $txt ) {
		for( $i=0; $i<UTF8_DEGREE; $i++ ) {
			$txt = utf8_decode( $txt );
		}
		return $txt;
	}


?>
