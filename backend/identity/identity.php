<?php
/*
 * Copyright 2005-2016 OCSInventory-NG/OCSInventory-ocsreports contributors.
 * See the Contributors file for more details about them.
 *
 * This file is part of OCSInventory-NG/OCSInventory-ocsreports.
 *
 * OCSInventory-NG/OCSInventory-ocsreports is free software: you can redistribute
 * it and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * OCSInventory-NG/OCSInventory-ocsreports is distributed in the hope that it
 * will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OCSInventory-NG/OCSInventory-ocsreports. if not, write to the
 * Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */
require_once(BACKEND . 'require/connexion.php');
$list_methode = array(0 => "local.php");
//$list_methode=array(0=>"ldap.php");
if (!isset($_SESSION['OCS']["lvluser"])) {
    $i = 0;
    //methode pour le calcul des droits
    while ($list_methode[$i]) {
        require_once('methode/' . $list_methode[$i]);
        //on garde les erreurs présentes
        //entre chaque méthode
        if (isset($ERROR)) {
            $tab_error[$list_methode[$i]] = $ERROR;
            unset($ERROR);
        }
        //on garde les tags qu'a le droit de voir l'utilisateur
        if (isset($list_tag)) {
            $tab_tag[$list_methode[$i]] = $list_tag;
            unset($list_tag);
        }
        $i++;
    }
}

if (!isset($tab_tag) && $restriction != 'NO') {
    $LIST_ERROR = "";
    foreach ($tab_error as $script => $error) {
        $LIST_ERROR .= $error;
        addLog('ERROR_IDENTITY', $error);
    }
    $_SESSION['OCS']["mesmachines"] = "NOTAG";
} elseif (isset($tab_tag)) {
    foreach ($list_methode as $prio => $script) {
        if (isset($tab_tag[$script])) {
            foreach ($tab_tag[$script] as $tag => $lbl) {
                $list_tag[$tag] = $tag;
                $lbl_list_tag[$tag] = $lbl;
            }
        }
    }
    if (is_array($list_tag) && !empty($list_tag)) {
        $mesMachines = "a.TAG IN ('" . implode("','", $list_tag) . "') ";
    } else {
        $mesMachines = null;
    }
    $_SESSION['OCS']["mesmachines"] = $mesMachines;
    $_SESSION['OCS']["mytag"] = $lbl_list_tag;
    $_SESSION['OCS']['TAGS'] = $list_tag;
}

if (isset($lvluser))
    $_SESSION['OCS']["lvluser"] = $lvluser;
?>