<?php

/**
 * File: functions_gs.php.
 * Author: Ulrich Block
 * Date: 26.01.14
 * Time: 10:46
 * Contact: <ulrich.block@easy-wi.com>
 *
 * This file is part of Easy-WI.
 *
 * Easy-WI is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Easy-WI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Easy-WI.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Diese Datei ist Teil von Easy-WI.
 *
 * Easy-WI ist Freie Software: Sie koennen es unter den Bedingungen
 * der GNU General Public License, wie von der Free Software Foundation,
 * Version 3 der Lizenz oder (nach Ihrer Wahl) jeder spaeteren
 * veroeffentlichten Version, weiterverbreiten und/oder modifizieren.
 *
 * Easy-WI wird in der Hoffnung, dass es nuetzlich sein wird, aber
 * OHNE JEDE GEWAEHELEISTUNG, bereitgestellt; sogar ohne die implizite
 * Gewaehrleistung der MARKTFAEHIGKEIT oder EIGNUNG FUER EINEN BESTIMMTEN ZWECK.
 * Siehe die GNU General Public License fuer weitere Details.
 *
 * Sie sollten eine Kopie der GNU General Public License zusammen mit diesem
 * Programm erhalten haben. Wenn nicht, siehe <http://www.gnu.org/licenses/>.
 */

if (!defined('EASYWIDIR')) {
    define('EASYWIDIR', '');
}

if (!function_exists('gsrestart')) {

    function gsrestart($switchID, $action, $aeskey, $reseller_id) {


        if (!class_exists('EasyWiFTP')) {
            include(EASYWIDIR . '/stuff/class_ftp.php');
        }

        global $sql;

        $tempCmds = array();
        $stopped = 'Y';

        $query = $sql->prepare("SELECT g.*,g.`id` AS `switchID`,AES_DECRYPT(g.`ppassword`,:aeskey) AS `decryptedppass`,AES_DECRYPT(g.`ftppassword`,:aeskey) AS `decryptedftppass`,s.*,AES_DECRYPT(s.`uploaddir`,:aeskey) AS `decypteduploaddir`,AES_DECRYPT(s.`webapiAuthkey`,:aeskey) AS `dwebapiAuthkey`,g.`pallowed`,t.`modfolder`,t.`gamebinary`,t.`binarydir`,t.`shorten`,t.`appID`,t.`workShop` AS `tWorkShop` FROM `gsswitch` g INNER JOIN `serverlist` s ON g.`serverid`=s.`id` INNER JOIN `servertypes` t ON s.`servertype`=t.`id` WHERE g.`active`='Y' AND g.`id`=:serverid AND g.`resellerid`=:reseller_id  AND t.`resellerid`=:reseller_id LIMIT 1");
        $query->execute(array(':aeskey' => $aeskey, ':serverid' => $switchID, ':reseller_id' => $reseller_id));
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $serverid = $row['serverid'];
            $anticheat = $row['anticheat'];
            $servertemplate = $row['servertemplate'];
            $protected = $row['protected'];
            $upload = $row['upload'];
            $uploaddir = $row['decypteduploaddir'];
            $shorten = $row['shorten'];
            $tvenable = $row['tvenable'];
            $gsip = $row['serverip'];
            $port = $row['port'];
            $port2 = $row['port2'];
            $port3 = $row['port3'];
            $port4 = $row['port4'];
            $port5 = $row['port5'];
            $minram = ($row['minram'] > 0) ? $row['minram'] : 512;
            $maxram = ($row['maxram'] > 0) ? $row['maxram'] : 1024;
            $gamebinary = $row['gamebinary'];
            $binarydir = $row['binarydir'];
            $eacallowed = $row['eacallowed'];
            $fps = $row['fps'];
            $slots = $row['slots'];
            $map = $row['map'];
            $mapGroup = $row['mapGroup'];
            $tic = $row['tic'];
            $rootid = $row['rootID'];
            $modfolder = $row['modfolder'];
            $ftppass = $row['decryptedftppass'];
            $decryptedftppass = $row['decryptedppass'];
            $cmd = $row['cmd'];
            $modcmd = $row['modcmd'];
            $pallowed = $row['pallowed'];
            $user_id = $row['userid'];

            $query = $sql->prepare("SELECT `cname` FROM `userdata` WHERE `id`=? LIMIT 1");
            $query->execute(array($user_id));
            $customer = $query->fetchColumn();
            if ($row['newlayout'] == 'Y') {
                $customer .= '-' . $row['switchID'];
            }


            $cores = ($row['taskset'] == 'Y') ? $row['cores'] : '';
            $maxcores = count(preg_split("/\,/", $cores, -1,PREG_SPLIT_NO_EMPTY));
            if ($maxcores == 0) {
                $maxcores = 1;
            }

            $folder = ($servertemplate > 1 and $protected == 'N') ? $shorten . '-' . $servertemplate : $shorten;

            if ($protected == 'Y') {
                $pserver = '';
                $absolutepath = '/home/' . $customer . '/pserver/' . $gsip . '_' . $port . '/' . $folder;
            } else {
                $pserver = 'server/';
                $absolutepath = '/home/' . $customer . '/server/' . $gsip . '_' . $port . '/' . $folder;
            }
            $bindir = $absolutepath. '/' . $binarydir;
            $cvarprotect = array();
            if ($gamebinary == 'hlds_run' and $tvenable == 'Y') {
                $slots++;
            }
            $modsCmds = array();
            $cvars = array('%binary%', '%tickrate%', '%tic%', '%ip%', '%port%', '%tvport%', '%port2%', '%port3%', '%port4%', '%port5%', '%slots%', '%map%', '%mapgroup%', '%fps%', '%minram%', '%maxram%', '%maxcores%', '%folder%', '%user%', '%absolutepath%');

            $query2 = $sql->prepare("SELECT `cmd`,`modcmds`,`configedit` FROM `servertypes` WHERE `shorten`=? AND `resellerid`=? LIMIT 1");
            $query2->execute(array($shorten, $reseller_id));

            foreach ($query2->fetchAll(PDO::FETCH_ASSOC) as $row2) {

                foreach (explode("\r\n", $row2['configedit']) as $line) {

                    if (preg_match('/^(\[[\w\/\.\-\_]{1,}\]|\[[\w\/\.\-\_]{1,}\] (xml|ini|cfg|lua|json))$/', $line)) {
                        $ex = preg_split("/\s+/", $line, -1,PREG_SPLIT_NO_EMPTY);
                        $cvartype = (isset($ex[1])) ? $ex[1] : 'cfg';
                        $config = substr($ex[0], 1, strlen($ex[0]) - 2);
                        $cvarprotect[$config]['type'] = $cvartype;

                    } else if (isset($config)) {
                        unset($splitline);
                        if ($cvarprotect[$config]['type'] == 'cfg') {
                            $splitline = preg_split("/\s+/", $line, -1, PREG_SPLIT_NO_EMPTY);
                        } else if ($cvarprotect[$config]['type'] == 'ini') {
                            $splitline = preg_split("/\=/", $line, -1, PREG_SPLIT_NO_EMPTY);
                        } else if ($cvarprotect[$config]['type'] == 'lua') {
                            $splitline = preg_split("/\=/", $line, -1, PREG_SPLIT_NO_EMPTY);
                        } else if ($cvarprotect[$config]['type'] == 'json') {
                            $splitline = preg_split("/:/", $line, -1, PREG_SPLIT_NO_EMPTY);
                        } else if ($cvarprotect[$config]['type'] == 'xml') {
                            $ex1 = explode('>', $line);
                            if (isset($ex1[1])) {
                                $c = str_replace('<', '', $ex1[0]);
                                list($v) = explode('<', $ex1[1]);
                                $splitline= array($c, $v);
                            }
                        }

                        if (isset($splitline[1])) {

                            $replace = array($gamebinary, $tic, $tic, $gsip, $port, $port2, $port2, $port3, $port4, $port5, $slots, $map, $mapGroup, $fps, $minram, $maxram, $maxcores, $folder, $customer, $absolutepath);
                            $cvar = str_replace($cvars, $replace, $splitline[1]);

                            foreach (customColumns('G', $switchID) as $cu) {
                                $cvar = str_replace("%${cu['name']}%", $cu['value'], $cvar);
                            }

                            $cvarprotect[$config]['cvars'][$splitline[0]] = $cvar;

                        }
                    }
                }

                foreach (explode("\r\n", $row2['modcmds']) as $line) {

                    if (preg_match('/^(\[[\w\/\.\-\_\= ]{1,}\])$/', $line)) {

                        $name = trim($line,'[]');
                        $ex = preg_split("/\=/", $name, -1,PREG_SPLIT_NO_EMPTY);
                        $name = trim($ex[0]);

                        if (isset($ex[1]) and trim($ex[1]) == 'default' and ($modcmd === null or $modcmd == '')) {
                            $modcmd = trim($ex[0]);
                        }

                        if (!isset($modsCmds[$name])) {
                            $modsCmds[$name] = array();
                        }

                    } else if (isset($name) and isset ($modsCmds[$name]) and $line!='') {
                        $modsCmds[$name][] = $line;
                    }
                }

                if ($row['owncmd'] == 'N') {
                    $cmd = $row2['cmd'];
                }

                // https://github.com/easy-wi/developer/issues/205
                // In case Workshop is on we need to remove workgroup
                if ($row['workShop'] == 'Y' AND $row['tWorkShop'] == 'Y') {
                    $cmd = str_replace(array('%mapgroup%', ' +mapgroup'), '', $cmd);
                }
            }
            if ($gamebinary == 'srcds_run' and $tvenable == 'N') {
                $cmd .= ' -nohltv -tvdisable';
            }
            if (($protected == 'N' and ($gamebinary == 'hlds_run' or $gamebinary == 'srcds_run') and ($anticheat == 2 or $anticheat == 3 or $anticheat == 4 or $anticheat == 5 or $anticheat == 6)) or (($protected == 'Y' and ($anticheat == 3 or $anticheat == 4 or $anticheat == 5 or $anticheat == 6)) and ($gamebinary == 'srcds_run' or $gamebinary == 'hlds_run') and $eacallowed == 'Y')) {
                $cmd .= ' -insecure';
            }

            $installedaddons = array();
            $rmarray = array();

            $query2=($protected == 'Y') ? $sql->prepare("SELECT `addonid` FROM `addons_installed` WHERE `userid`=? AND `serverid`=? AND `paddon`='Y' AND `resellerid`=?") : $sql->prepare("SELECT `addonid` FROM `addons_installed` WHERE `userid`=? AND `serverid`=? AND `paddon`='N' AND `resellerid`=?");
            $query3 = $sql->prepare("SELECT `cmd`,`rmcmd`,`addon`,`type` FROM `addons` WHERE `id`=? AND `resellerid`=? AND `active`='Y' LIMIT 1");
            $query2->execute(array($user_id, $serverid, $reseller_id));
            foreach ($query2->fetchAll(PDO::FETCH_ASSOC) as $row2) {
                $query3->execute(array($row2['addonid'], $reseller_id));
                foreach ($query3->fetchAll(PDO::FETCH_ASSOC) as $row3) {
                    if ($row3['type'] == 'tool') {
                        $installedaddons[] = $row3['addon'];
                    }
                    if ($row3['cmd'] != null) {
                        $cmd .= ' ' . $row3['cmd'];
                    }
                    if ($row3['rmcmd'] != null) {
                        foreach (preg_split("/\r\n/", $row3['rmcmd'], -1, PREG_SPLIT_NO_EMPTY) as $rm) {
                            $rmarray[] = $rm;
                        }
                    }
                }
            }

            foreach ($rmarray as $rm) {
                $cmd = str_replace($rm, '', $cmd);
            }

            $query2 = $sql->prepare("SELECT `rcon`,`password`,`slots`,AES_DECRYPT(`ftpuploadpath`,?) AS `decyptedftpuploadpath` FROM `lendedserver` WHERE `serverid`=? AND `servertype`='g' AND `resellerid`=? LIMIT 1");
            $query2->execute(array($aeskey, $serverid, $reseller_id));
            foreach ($query2->fetchAll(PDO::FETCH_ASSOC) as $row2) {

                $slots = $row2['slots'];

                if ($row2['decyptedftpuploadpath'] != null and $row2['decyptedftpuploadpath'] != '' and $row2['decyptedftpuploadpath'] != 'ftp://username:password@1.1.1.1/demos') {
                    $ftpupload = $row2['decyptedftpuploadpath'];
                }

                if ($gamebinary == 'srcds_run') {
                    $cmd .= ' +rcon_password ' .$row2['rcon'] . ' +sv_password ' . $row2['password']. ' +tv_enable 1 +tv_autorecord 1';
                } else if ($gamebinary == 'hlds_run') {
                    $cmd .= ' +rcon_password ' . $row2['rcon'] . ' +sv_password ' . $row2['password'];
                } else if ($gamebinary == 'cod4_lnxded') {
                    $cmd .= ' +set rcon_password ' . $row2['rcon'] . ' +set g_password ' . $row2['password'];
                }
            }

            if (isset($modcmd) and isset($modsCmds[$modcmd]) and is_array($modsCmds[$modcmd])) {
                foreach ($modsCmds[$modcmd] as $singleModADD) {
                    $cmd .= ' ' . $singleModADD;
                }
            }

            if ($row['workShop'] == 'Y' AND $row['tWorkShop'] == 'Y' and isid($row['workshopCollection'], 10) and wpreg_check($row['dwebapiAuthkey'], 32) and strlen($row['dwebapiAuthkey']) > 0 and $row['workshopCollection'] > 0) {
                $cmd .= ' -nodefaultmap +host_workshop_collection ' . $row['workshopCollection'] . ' +workshop_start_map ' . $map . ' -authkey ' . $row['dwebapiAuthkey'];
                $cmd = preg_replace('/[\s\s+]{1,}\+map[\s\s+]{1,}[\w-_!%]{1,}/', '', $cmd);
            }

            $rdata = serverdata('root', $rootid, $aeskey);
            $sship = $rdata['ip'];
            $ftpport = $rdata['ftpport'];
            $serverFolder = $gsip . '_' . $port . '/' . $folder;
            $binaryFolder = $serverFolder . '/' . $binarydir;
            $replace = array($gamebinary, $tic, $tic, $gsip, $port, $port2, $port2, $port3, $port4, $port5, $slots, $map, $mapGroup, $fps, $minram, $maxram, $maxcores, $folder, $customer, $absolutepath);
            $startline = str_replace($cvars, $replace, $cmd);

            foreach (customColumns('G', $switchID) as $cu) {
                $startline = str_replace("%${cu['name']}%", $cu['value'], $startline);
            }

            if ($protected == 'Y' and $pallowed == 'Y') {
                $customerUnprotected = $customer;
                $customer .= '-p';
                $ftppass = $decryptedftppass;
            } else if ($protected == 'N' and $pallowed == 'Y') {
                $customerProtected = $customer . '-p';
            }

            if ($action != 'du' and $eacallowed == 'Y' and in_array($anticheat, array(3, 4, 5, 6)) and ($gamebinary == 'srcds_run' or $gamebinary == 'hlds_run')) {

                if ($action == 'so' or $action == 'sp') {
                    $rcon = '';
                    eacchange('remove', $serverid, $rcon, $reseller_id);

                } else if ($action == 're') {

                    if ($gamebinary == 'srcds_run') {
                        $config = $modfolder . '/cfg/server.cfg';
                    } else if ($gamebinary == 'hlds_run') {
                        $config = $modfolder . '/server.cfg';
                    } else {
                        $config = 'main/server.cfg';
                    }

                    $ftpObect = new EasyWiFTP($sship, $ftpport, $customer, $ftppass);

                    if ($ftpObect->loggedIn === true) {

                        $ftpObect->downloadToTemp($pserver . $serverFolder . '/' . $config);
                        $configfile = $ftpObect->getTempFileContent();

                        $configfile = str_replace(array("\0","\b","\r","\Z"), '', $configfile);
                        $lines = explode("\n", $configfile);
                        $lines = preg_replace('/\s+/', ' ', $lines);

                        foreach ($lines as $singeline) {

                            if (preg_match("/\w/", substr($singeline, 0, 1))) {

                                if (preg_match("/\"/", $singeline)) {
                                    $split = explode('"', $singeline);
                                    $cvar = str_replace(' ', '', $split[0]);
                                    $value = $split[1];

                                    if ($cvar == 'rcon_password') {
                                        $rcon = $value;
                                    }

                                } else {
                                    $split = explode(' ', $singeline);

                                    if (isset($split[0])) {
                                        $cvar = $split[0];
                                        $value=(isset($split[1])) ? $split[1] : '';

                                        if ($cvar == 'rcon_password') {
                                            $rcon = $value;
                                        }
                                    }
                                }
                            }
                        }

                        if (isset($rcon)) {
                            eacchange('change', $serverid, $rcon, $reseller_id);
                        }
                    }
                }

            } else if ($action!='du' and $eacallowed == 'Y' and ($gamebinary == 'srcds_run' or $gamebinary == 'hlds_run') and ($anticheat == 1 or $anticheat == 2)) {
                $rcon = '';
                eacchange('remove', $serverid, $rcon, $reseller_id);
            }

            $protectedString = ($protected == 'N') ? 'unprotected' : 'protected';

            if ($action == 'so' or $action == 'sp') {

                $stopped = 'Y';

                if ($action == 'so') {
                    $tempCmds[]="sudo -u ${customer} ./control.sh gstop $customer \"$binaryFolder\" $gamebinary $protectedString";
                    if ((isset($ftpupload) and $gamebinary == 'srcds_run')) {
                        $tempCmds[]="sudo -u ${customer} ./control.sh demoupload \"$bindir\" \"$ftpupload\" \"$modfolder\"";
                    }
                } else {
                    $tempCmds[]="sudo -u ${customer} ./control.sh stopall";
                }

            } else if ($action == 're') {

                $stopped = 'N';

                if ($protected == 'N' and count($installedaddons)>0) {
                    $tempCmds[] = "sudo -u ${customer} ./control.sh addonmatch $customer \"$binaryFolder\" \"".implode(' ', $installedaddons)."\"";
                }
                $restartCmd = "sudo -u ${customer} ./control.sh grestart $customer \"$binaryFolder\" \"$startline\" $protectedString $gamebinary \"$cores\"";
            }

            if (!isset($ftpupload) and $gamebinary == 'srcds_run' and isurl($uploaddir)) {

                if ($upload==2) {
                    $uploadcmd = "./control.sh demoupload \"$bindir\" \"$uploaddir\" \"$modfolder\" manual remove";
                } else if ($upload==3) {
                    $uploadcmd = "./control.sh demoupload \"$bindir\" \"$uploaddir\" \"$modfolder\" manual keep";
                } else if ($upload==4) {
                    $uploadcmd = "./control.sh demoupload \"$bindir\" \"$uploaddir\" \"$modfolder\" auto remove";
                } else if ($upload==5) {
                    $uploadcmd = "./control.sh demoupload \"$bindir\" \"$uploaddir\" \"$modfolder\" auto keep";
                }
                if (($action == 'du' or ($action != 'so' and $action!='sp')) and isset($uploadcmd)) {
                    $tempCmds[]="sudo -u ${customer} $uploadcmd";
                }
                if ($action == 'du' and isset($uploadcmd)) {
                    $stopped = 'N';
                }
            }

            foreach ($cvarprotect as $config => $values) {
                if (!isset($values['cvars']) or count($values['cvars']) == 0) {
                    unset($cvarprotect[$config]);
                }
            }
            if (count($cvarprotect) > 0 and $action != 'du') {

                if (!isset($ftpObect)) {
                    $ftpObect = new EasyWiFTP($sship, $ftpport, $customer, $ftppass);
                }

                if ($ftpObect->loggedIn === true) {

                    foreach ($cvarprotect as $config => $values) {

                        $cfgtype = $values['type'];

                        if ($gamebinary == 'srcds_run' or $gamebinary == 'hlds_run') {
                            $config = $modfolder . '/' . $config;
                        }

                        $split_config = preg_split('/\//', $config, -1,PREG_SPLIT_NO_EMPTY);
                        $folderfilecount = count($split_config)-1;

                        $i = 0;
                        $folders = '/' . $pserver . $serverFolder;

                        while ($i<$folderfilecount) {
                            $folders .= '/' . $split_config[$i];
                            $i++;
                        }

                        $uploadfile = $split_config[$i];

                        $ftpObect->downloadToTemp($folders . '/' . $uploadfile);

                        $configfile = $ftpObect->getTempFileContent();


                        $ftpObect->tempHandle = null;

                        $configfile = str_replace(array("\0","\b","\r","\Z"),"", $configfile);

                        $lines = explode("\n", $configfile);
                        $linecount = count($lines) - 1;
                        $i = 0;

                        foreach ($lines as $singeline) {

                            $edited = false;
                            $lline = strtolower($singeline);

                            foreach ($values['cvars'] as $cvar => $value) {

                                if ($cfgtype == 'cfg' and preg_match("/^(.*)" . strtolower($cvar) . "\s+(.*)$/", $lline)) {

                                    $edited = true;

                                    $splitline = preg_split("/$cvar/", $singeline, -1,PREG_SPLIT_NO_EMPTY);

                                    $ftpObect->writeContentToTemp((isset($splitline[1])) ? $splitline[0] . $cvar . '  ' . $value : $cvar . '  ' . $value);

                                } else if ($cfgtype == 'ini' and preg_match("/^(.*)" . strtolower($cvar) . "[\s+]{0,}\=[\s+]{0,}(.*)$/", $lline)) {

                                    $edited = true;

                                    $ftpObect->writeContentToTemp($cvar . '=' . $value);

                                } else if ($cfgtype == 'lua' and preg_match("/^(.*)" . strtolower($cvar) . "[\s+]{0,}\=[\s+]{0,}(.*)[\,]$/", $lline)) {

                                    $edited = true;

                                    $splitline = preg_split("/$cvar/", $singeline, -1,PREG_SPLIT_NO_EMPTY);

                                    $ftpObect->writeContentToTemp((isset($splitline[1])) ? $splitline[0] . $cvar. ' = ' .$value : $cvar . '=' . $value);

                                } else if ($cfgtype == 'json' and preg_match("/^(.*)" . strtolower($cvar) . "[\s+]{0,}:[\s+]{0,}(.*)[\,]{0,1}$/", $lline)) {

                                    $edited = true;

                                    $splitline = preg_split("/$cvar/", $singeline, -1,PREG_SPLIT_NO_EMPTY);

                                    $ftpObect->writeContentToTemp((isset($splitline[1])) ? $splitline[0] . $cvar. ' : ' .$value : $cvar . ':' . $value);

                                } else if ($cfgtype == 'xml' and preg_match("/^(.*)<" . strtolower($cvar) . ">(.*)<\/" . strtolower($cvar) . ">(.*)$/", $lline)) {

                                    $edited = true;

                                    $splitline = preg_split("/\<$cvar/", $singeline, -1,PREG_SPLIT_NO_EMPTY);

                                    $ftpObect->writeContentToTemp((isset($splitline[1])) ? $splitline[0] . '<' .$cvar . '>' . $value . '</' . $cvar . '>' : '<' . $cvar . '> ' . $value . '</' . $cvar . '>');
                                }
                            }

                            if ($edited == false) {
                                $ftpObect->writeContentToTemp($singeline);
                            }

                            if ($i < $linecount) {
                                $ftpObect->writeContentToTemp("\r\n");
                            }

                            $i++;
                        }

                        $ftpObect->uploadFileFromTemp($folders, $uploadfile, false);

                    }
                }
            }

            if (isset($ftpObect)) {
                $ftpObect->logOut();
            }

            $query = $sql->prepare("UPDATE `gsswitch` SET `stopped`=? WHERE `id`=? AND `resellerid`=? LIMIT 1");
            $query->execute(array($stopped, $switchID, $reseller_id));
            $cmds = array();

            if ($pallowed == 'Y' and $protected == 'Y') {
                $cmds[]="sudo -u ${customerUnprotected} ./control.sh stopall";
            } else if ($pallowed == 'Y' and $protected == 'N') {
                $cmds[]="sudo -u ${customerProtected} ./control.sh stopall";
            }

            $cmds[] = 'sudo -u ' . $customer . ' ./control.sh addserver ' . $customer . ' 1_' . $shorten . ' ' . $gsip . '_' . $port;

            if (isset($restartCmd)) {
                $cmds[] = $restartCmd;
            }

            foreach ($tempCmds as $c) {
                $cmds[] = $c;
            }

            return $cmds;
        }

        return false;
    }

    function eacchange($what, $serverid, $rcon, $reseller_id) {

        global $sql;
        $subfolder = '';
        $parameter = '';

        $query = $sql->prepare("SELECT `active`,`cfgdir` FROM `eac` WHERE `resellerid`=? LIMIT 1");
        $query->execute(array($reseller_id));
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cfgdir = $row['cfgdir'];
            $active = $row['active'];

            $query = $sql->prepare("SELECT g.`serverip`,g.`port`,s.`anticheat`,t.`shorten` FROM `gsswitch` g LEFT JOIN `serverlist` s ON g.`serverid`=s.`id` LEFT JOIN `servertypes` t ON s.`servertype`=t.`id` WHERE g.`id`=? AND g.`resellerid`=? LIMIT 1");
            $query->execute(array($serverid, $reseller_id));
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $gsip = $row['serverip'];
                $gsport = $row['port'];

                if ($row['anticheat'] == 3) {
                    $parameter = '';
                } else if ($row['anticheat'] == 4) {
                    $parameter = '-2';
                } else if ($row['anticheat'] == 5) {
                    $parameter = '-1';
                } else if ($row['anticheat'] == 6) {
                    $parameter = '-3';
                }

                if ($row['shorten'] == 'cstrike' or $row['shorten'] == 'czero') {
                    $subfolder = 'hl1';
                } else if ($row['shorten'] == 'css' or $row['shorten'] == 'tf') {
                    $subfolder = 'hl2';
                } else if ($row['shorten'] == 'csgo') {
                    $subfolder = 'csgo';
                }

                $file = $cfgdir . '/' . $subfolder . '/' . $gsip . '-' . $gsport;
                $file = preg_replace('/\/\//', '/', $file);

                if ($what == 'change') {
                    $ssh2cmd = 'echo "'.$gsip . ':' . $gsport . '-' . $rcon . $parameter . '" > '.$file;
                } else if ($what == 'remove') {
                    $ssh2cmd='rm -f '.$file;
                }

                if (isset($ssh2cmd) and $active == 'Y') {
                    if (!function_exists('ssh2_execute')) {
                        include(EASYWIDIR . '/stuff/functions_ssh_exec.php');
                    }
                    if (isset($ssh2cmd)) {
                        ssh2_execute('eac', $reseller_id, $ssh2cmd);
                    }
                }
            }
        }
    }

    function normalizeName ($value) {

        // control characters
        $value = str_replace(array("\r", "\n"), '', $value);

        // COD colors
        $value = preg_replace('/\^[0-9]/i', '', $value);

        // Unreal Tournament Colors
        $value = preg_replace('/\x1B...|\^\d/', '', $value);

        // Minecraft Motd Colors
        $value = preg_replace('/\\[u]00A7[\w]/i', '', $value);

        // Minecraft standard colors
        $value = preg_replace('/§[0-9a-f]/i', '', $value);

        return $value;

    }
}