<?php
/*********************************************************************
 *
 * $Id: index.php 23775 2016-04-06 07:49:51Z seb $
 *
 * Yoctopuce sensors visualisation application
 *
 * - - - - - - - - - License information: - - - - - - - - -
 *
 *  Copyright (C) 2011 and beyond by Yoctopuce Sarl, Switzerland.
 *
 *  Yoctopuce Sarl (hereafter Licensor) grants to you a perpetual
 *  non-exclusive license to use, modify, copy and integrate this
 *  file into your software for the sole purpose of interfacing
 *  with Yoctopuce products.
 *
 *  You may reproduce and distribute copies of this file in
 *  source or object form, as long as the sole purpose of this
 *  code is to interface with Yoctopuce products. You must retain
 *  this notice in the distributed source file.
 *
 *  You should refer to Yoctopuce General Terms and Conditions
 *  for additional information regarding your rights and
 *  obligations.
 *
 *  THE SOFTWARE AND DOCUMENTATION ARE PROVIDED "AS IS" WITHOUT
 *  WARRANTY OF ANY KIND, EITHER EXPRESS OR IMPLIED, INCLUDING
 *  WITHOUT LIMITATION, ANY WARRANTY OF MERCHANTABILITY, FITNESS
 *  FOR A PARTICULAR PURPOSE, TITLE AND NON-INFRINGEMENT. IN NO
 *  EVENT SHALL LICENSOR BE LIABLE FOR ANY INCIDENTAL, SPECIAL,
 *  INDIRECT OR CONSEQUENTIAL DAMAGES, LOST PROFITS OR LOST DATA,
 *  COST OF PROCUREMENT OF SUBSTITUTE GOODS, TECHNOLOGY OR
 *  SERVICES, ANY CLAIMS BY THIRD PARTIES (INCLUDING BUT NOT
 *  LIMITED TO ANY DEFENSE THEREOF), ANY CLAIMS FOR INDEMNITY OR
 *  CONTRIBUTION, OR OTHER SIMILAR COSTS, WHETHER ASSERTED ON THE
 *  BASIS OF CONTRACT, TORT (INCLUDING NEGLIGENCE), BREACH OF
 *  WARRANTY, OR OTHERWISE.
 *
 *********************************************************************/

// This script PHP will allow you to create your very own visualisation cloud
// to control Yoctopuce sensors. Basically you will be able to interactively
// build a user interface with graphs showing your Yoctopuce data history.
//
// Installation:
// 1/ Copy all seven php files on your PHP server, preferably in a sub folder
// 2/ Create a data sub-folder, make sure the php server has write access to it
//
// Configuration
// 1/ With a web browser, Open your YoctoHub/VirtualHub configuration. in the
//    "Outgoing callbacks" section, define a "Yocto-API"  callback pointing to
//    the index.php file you just copied on your server. Test the callback with
//    the "test" button, you should get a message with "Done." at the end.
//    If everything is fine, just save and close the configuration
// 2/ With your browser, open  the URL pointing on your index.php and
//    add "?edit" at the end of the URL, this will make a edition menu
//    appear, you can start to add a graph with "new..>chart".
//
// Usage:
//    For consultation only, just open the URL without the ?edit parameter
//
// Feeds
//    The application can handle different sets of sensors, just use add the
//    parameter &feed=ArbitraryName on all URLs, including the one defined
//    in the Hubs callback configuration
//
// More detail about this application our blog:
//  www.yoctopuce.com/EN/article/a-yoctopuce-web-based-app-to-draw-sensors-graphs
//
// You'll probably want to remove that annoying pop-up notice about Highcharts.
// Just create an empty file named YesIKnowThatHighStockLibraryIsNotFree.txt
// next to index.php and the pop-up will be gone. That being said, if you
// plan to use that application for anything else but personal or non-profit
// project, you should buy a HighStock license. These  guys at HighSoft made
// an amazing work, they deserve their money.
//
include("yocto_api.php");
include("yocto_network.php");
include("yocto_wakeupschedule.php");
include("yocto_wakeupmonitor.php");
include("yocto_relay.php");
include("yocto_anbutton.php");
date_default_timezone_set('Europe/Paris');
$logdata          = '';
$LOG_FILE_MAXSIZE = 128 * 1024;
$FEED             = 'default';
$now              = time();
$appReady         = 0;
$shownotice       = false;
$snow             = gmdate("Y-m-d\TH:i:s\z", $now);
define("MAX_DATASRC_PER_GRAPH",       4);
define("MAX_YAXIS_PER_GRAPH",         2);
define("MAX_YBAND_PER_GRAPH",         3);
define("MAX_VARIABLE_PER_DECO",       6);
define("FIRST_READ_POINT_COUNT",   2000);
define("REFRESH_READ_POINT_COUNT", 1000);
define("GRAPH_REFRESH_CHUNK_SIZE",  100);
define("RAWDATACLUSTERSIZE",       2000);
define("TRIMBLOCKSIZE",            5000);
define("PHPDECOREFRESHDELAY",     10000);
define('DATAFOLDER',             'data');
define("IFRAMEHEADER","<HTML><head><meta http-equiv='Content-Type' content='text/html; charset=windows-1252'></head><BODY>");

$bin="eJxlkMtOxDAMRX/Fu2xm2v2orQQseAjBghUrlKZuYyaNq8Sl9O9xO+IhsUmsm/he+1wj2MUmB"
    ."PFW9ECoLPiEfW28yHQqy2VZCk+Dd94myYXjsZwSd7OTXG56FnZn09x9lxCoTTatVWkbmDN2QF"
    ."GNKUOfUNOmKZCzQhxBtaptIsv+tI1RlW1TwCvP4GzcuoEE5thh2me7SaidH1rwOHLMcCWSqJ0"
    ."3t+MTx03G5MgGeCSHUfs1oOcEE6bMUXWto37WFXq11usddZNL7n0Pq0ZTFIwdCEPHYOOqw8cB"
    ."MKjbQuIvy/zZ47B3Zc9z6P7hU3na+WXuZaf3A63wMgbTtPMKVqld5uUeflE+vGwUC6gmyLIGr"
    ."I3gpxxtoCGekn4T02ggdbUZqBUDLtica4MdyZtiEY4GmufzAW4VMkmx2VXl1HwBRRyyrQ==";

class sensor
{
    public $data = array();
    public $name = '';
    public $hname = '';
    public $unit = '';
    public $resolution = 0;
    function __construct($filename)
    {
        $fh = fopen(DATAFOLDER . '/' . $filename, "rb");
        if ($fh === FALSE)
            die("can't open " . DATAFOLDER . '/' . $filename);
        $header = fread($fh, 512);
        fclose($fh);
        $data = unpack("a*", $header);
        $ini  = parse_ini_string($data[1], true);
        foreach ($ini as $key => $section) {
            if ($key == 'sensor') {
                $this->name       = $ini[$key]['logicalname'];
                $this->hname      = $ini[$key]['hardwarename'];
                $this->unit       = $ini[$key]['unit'];
                $this->resolution = $ini[$key]['resolution'];
            }
        }
    }
}
function logmsg($msg)
{
    global $logdata;
    print("$msg\r\n");
    $logdata .= $msg . "\r\n";
}
function abort($msg)
{
    global $logdata;
    global $logfile;
    global $LOG_FILE_MAXSIZE;
    logmsg($msg);
    $data = '';
    if (file_exists($logfile)) {
        $data = file_get_contents($logfile);
    }
    $data .= $logdata;
    if (strlen($data) > $LOG_FILE_MAXSIZE) {
        $data = substr($data, -$LOG_FILE_MAXSIZE);
    }
    file_put_contents($logfile, $data);
    die();
}
function GetDataFileHeader($sensor)
{
    global $now;
    global $snow;
    if (!is_null($sensor)) {
        $name       = $sensor->get_friendlyName();
        $hname      = $sensor->get_hardwareId();
        $unit       = method_exists($sensor, 'get_unit') ? $sensor->get_unit() : '';
        $resolution = method_exists($sensor, 'get_resolution') ? -intVal(round(log10($sensor->get_resolution()))) : 0;
        if ($resolution < 0)
            $resolution = 0;
    } else {
        $name       = "lname";
        $hname      = "hname";
        $unit       = "unit";
        $snow       = "N/A";
        $resolution = 0;
    }

  $header="[sensor]\r\n"
         ."lastupdate=\"$snow\"\r\n"
         ."logicalname=\"$name\"\r\n"
	     ."hardwarename=\"$hname\"\r\n"
	     ."unit=\"$unit\"\r\n"
	     ."resolution=\"$resolution\"\r\n"
	     .chr(26);
    $header .= str_repeat(chr(0), 512 - strlen($header));
    return pack("a*", $header);
}
function getHeaderSize()
{
    return strlen(GetDataFileHeader(null));
}
function getRecordData($current, $min, $max)
{
    global $now;
    if (is_null($current)) {
        $current = 0.0;
        $max     = 0.0;
        $min     = 0.0;
    }
    return pack("Lddd", $now, $current, $max, $min);
}
function getRecordSize()
{
    return strlen(getRecordData(null, null, null));
}
function InitdataFiletrim($filename, $maxRecordCount)
{
    if (!file_exists($filename))
        return;
    $tmpfile = substr($filename, 0, strlen($filename) - 4) . '.TMP';
    if (file_exists($tmpfile)) {
        return;
    }
    logmsg("Init trimming of $filename");
    $fsize = filesize($filename);
    $rsize = getRecordSize();
    $count = floor(($fsize - 512) / $rsize);
    if ($fsize <= 512 + $rsize * $maxRecordCount)
        return;
    $fh = fopen($filename, "rb");
    if ($fh === false) {
        logmsg("can't open $filename, aborting");
        return;
    }
    $header = fread($fh, 512);
    $toread = $maxRecordCount;
    if ($toread > TRIMBLOCKSIZE)
        $toread = TRIMBLOCKSIZE;
    fseek($fh, -$maxRecordCount * $rsize, SEEK_END);
    $block = fread($fh, $toread * $rsize);
    fclose($fh);
    $fh = fopen($tmpfile, "wb");
    if ($fh === false) {
        logmsg("can't create $tmpfile, aborting");
        return;
    }
    fwrite($fh, $header);
    fwrite($fh, $block);
    fclose($fh);
}
function RunTrimProcess()
{
    global $FEED;
    $datafileNames = glob(DATAFOLDER . "/data-$FEED-*.TMP");
    if ($datafileNames === FALSE)
        return;
    if (sizeof($datafileNames) <= 0)
        return;
    $filename         = $datafileNames[0];
    $originalFilename = substr($filename, 0, strlen($filename) - 4) . '.bin';
    logmsg("Trimming processing for $originalFilename ");
    if (!file_exists($originalFilename)) {
        unlink($filename);
        return;
    }
    if (filesize($originalFilename) < filesize($filename)) {
        unlink($filename);
        return;
    }
    $recordsize = getRecordSize();
    $fth        = fopen($filename, "r+b");
    if ($fth === false) {
        logmsg("can't open $filename, aborting");
        return;
    }
    fseek($fth, -$recordsize, SEEK_END);
    $lastRecord          = unpack("Lkey/dcur/dmax/dmin", fread($fth, $recordsize));
    $originalRecordCount = floor((filesize($originalFilename) - 512) / $recordsize);
    $foh                 = fopen($originalFilename, "rb");
    if ($foh === false) {
        fclose($fth);
        logmsg("can't open $originalFilename, aborting");
        return;
    }
    $header              = fread($foh, 512);
    $firstOriginalRecord = unpack("Lkey/dcur/dmax/dmin", fread($foh, $recordsize));
    fseek($foh, -$recordsize, SEEK_END);
    $lastOriginalRecord = unpack("Lkey/dcur/dmax/dmin", fread($foh, $recordsize));
    $index              = lookForTimeIndex($foh, $lastRecord['key'], 0, $firstOriginalRecord['key'], $originalRecordCount - 1, $lastOriginalRecord['key'], $recordsize);
    if ($index < 0) {
        fclose($foh);
        fclose($fth);
        logmsg("Trim file error, aborting");
        return;
    }
    $dataleft = 0;
    $toread   = $originalRecordCount - ($index + 1);
    if ($toread > 0) {
        if ($toread > TRIMBLOCKSIZE)
            $toread = TRIMBLOCKSIZE;
        logmsg("Copying a $toread records block");
        $dataleft = $originalRecordCount - ($index + 1) - $toread;
        logmsg("There will be $dataleft records left");
        fseek($foh, 512 + ($index + 1) * $recordsize);
        $block = fread($foh, $toread * $recordsize);
        if ($block === FALSE) {
            fclose($foh);
            fclose($fth);
            logmsg("can't read data block from $originalFilename, aborting");
            return;
        }
        fseek($fth, 0);
        fwrite($fth, $header);
        fseek($fth, 0, SEEK_END);
        fwrite($fth, $block);
    } else
        logmsg('Already at end of file, nothing left to copy');
    if (fclose($foh) === FALSE)
        logmsg("failed to close $originalFilename");
    if (fclose($fth) === FALSE)
        logmsg("failed to close $filename");
    if ($dataleft <= 0) {
        if (copy($filename, $originalFilename) === TRUE)
            unlink($filename);
    }
}
function openNoticeMessage()
{
    global $appReady;
    global $shownotice;
    $f = glob("*");
    for ($i = 0; $i < sizeof($f); $i++)
        if (strtoupper(md5($f[$i])) == "6A04E84B79FE67FFAFA00539FD0EDF3B") {
            $appReady++;
            ob_start();
            return;
        }
    $shownotice = true;
}
function printNoticeMessage()
{   $blob='b'.'i'.'n';
    global $appReady;
    global $shownotice;
    global $$blob;
    Print("<!--");
    $text = gzuncompress(base64_decode($$blob));
    Print("-->");
    Print($text==FALSE ? base64_decode("Q29kZSBjb3JydXB0ZWQ="):$text);
    if (!$shownotice) $appReady++;
}
function closeNoticeMessage()
{
    global $shownotice;
    if (!$shownotice)
        ob_end_clean();
}
function handleSensor($sensor, $dataTrimSize, $dataMaxSize)
{
    global $FEED;
    global $now;
    global $LOG_FILE_MAXSIZE;
    $name  = $sensor->get_friendlyName();
    $hname = $sensor->get_hardwareId();
    if (is_a($sensor, 'YSensor')) {
        $current = $sensor->get_currentValue();
        $max     = $sensor->get_highestValue();
        $min     = $sensor->get_lowestValue();
        $unit    = $sensor->get_unit();
        logmsg(gmdate("Y-m-d\TH:i:s\Z", $now) . " Sensor $name =" . $current . $unit);
        $sensor->set_highestValue($current);
        $sensor->set_lowestValue($current);
    } else if (is_a($sensor, 'YAnButton')) {
        $current = $sensor->get_calibratedValue();
        $max     = $current;
        $min     = $current;
        logmsg(gmdate("Y-m-d\TH:i:s\Z", $now) . " AnButton $name =" . $current);
    } else if (is_a($sensor, 'YRelay')) {
        $current = ($sensor->get_state() == Y_STATE_B) ? 1 : 0;
        $max     = $current;
        $min     = $current;
        logmsg(gmdate("Y-m-d\TH:i:s\Z", $now) . " Relay $name =" . $current);
    } else
        return; //unsupported object type
    $filename = DATAFOLDER . "/data-$FEED-$hname.bin";
    $flag     = "r+b";
    if (!file_exists($filename))
        $flag = "w+b";
    $fh = fopen($filename, $flag);
    if ($fh === FALSE)
        die("can't open $filename");
    fwrite($fh, GetDataFileHeader($sensor));
    fseek($fh, 0, SEEK_END);
    fwrite($fh, GetRecordData($current, $min, $max));
    fclose($fh);
    $recordsize = getRecordSize();
    if ($dataMaxSize > 0)
        if ((filesize($filename) - 512) / $recordsize > $dataMaxSize)
            InitdataFiletrim($filename, $dataTrimSize);
}
function lookForTimeIndex($fh, $time, $posA, $timeA, $posB, $timeB, $recordsize)
{
    $t       = 'N/A';
    $prevPos = -1;
    do {
        if ($timeA == $time) {
            return $posA;
        }
        if ($timeB == $time) {
            return $posB;
        }
        if ($posA == $posB) {
            return $posA;
        }
        $newpos = intVal(($posA + $posB) / 2);
        fseek($fh, 512 + $recordsize * $newpos);
        $data = unpack("Lkey/dcur/dmax/dmin", fread($fh, $recordsize));
        $t    = $data['key'];
        if ($t == $time) {
            return $newpos;
        }
        if ($newpos == $prevPos) {
            logmsg("internal error: Timestamp $time not found");
            return -1;
        }
        $prevPos = $newpos;
        if ($t < $time) {
            $posA  = $newpos;
            $timeA = $t;
        } else {
            $posB  = $newpos;
            $timeB = $t;
        }
    } while (true);
}
function loadLatestData($feed)
{
    printf(IFRAMEHEADER);
    $datafileNames = glob(DATAFOLDER . "/data-$feed-*.bin");
    asort($datafileNames);
    if (sizeof($datafileNames) <= 0)
        die('no data');
    $data          = Array();
    $recordsize    = getRecordSize();
    $lastTimeStamp = 0;
    $first         = true;
    $jscode        = 'window.parent.updateDecoValue([';
    for ($i = 0; $i < sizeof($datafileNames); $i++) {
        $filesize = filesize($datafileNames[$i]);
        if (filesize($datafileNames[$i]) > 512) {
            $fh = fopen($datafileNames[$i], "r");
            if ($fh === FALSE)
                printf("can't open {$datafileNames[$i]}<br>\n");
            else {
                $header = fread($fh, 512);
                fseek($fh, -$recordsize, SEEK_END);
                $lastrecord = fread($fh, $recordsize);
                fclose($fh);
                $headerdata     = unpack("a*", $header);
                $ini            = parse_ini_string($headerdata[1], true);
                $lastRecorddata = unpack("Lkey/dcur/dmax/dmin", $lastrecord);
                $jscode .= sprintf("\n%s['%s',%d,%f,%s]", $first ? '' : ',', $ini['sensor']['hardwarename'], $lastRecorddata['key'], $lastRecorddata['cur'], json_encode($ini['sensor']['unit']));
                $first = false;
            }
        }
    }
    $jscode .= "]);\n";
    addJsCode($jscode);
    die('done.</BODY></HTML>');
}
function loadGraphData($feed, $id, $indexes, $names, $timestamps)
{
    Print(IFRAMEHEADER."<SCRIPT>\n");
    $indexes   = explode(',', $indexes);
    $names     = explode(',', $names);
    $timeStamp = explode(',', $timestamps);
    $precision = 0;
    if (sizeof($indexes) != sizeof($names))
        die("length of names and index don't match");
    for ($i = 0; $i < sizeof($indexes); $i++) {
        $name = $names[$i];
        if ($name != '') {
            $index      = $indexes[$i];
            $start      = $timeStamp[2 * $i];
            $end        = $timeStamp[2 * $i + 1];
            $recordsize = getRecordSize();
            $filename   = DATAFOLDER . "/data-$feed-$name.bin";
            if (file_exists($filename)) {
                $mincode  = '';
                $maxcode  = '';
                $curcode  = '';
                $first    = true;
                $filesize = filesize($filename);
                $fh       = fopen($filename, "rb");
                if ($fh === FALSE)
                    die("can't open $filename");
                $header      = fread($fh, 512);
                $data        = unpack("a*", $header);
                $ini         = parse_ini_string($data[1], true);
                $resolution  = $ini['sensor']['resolution'];
                $unit        = $ini['sensor']['unit'];
                $readok      = true;
                $toread      = 0;
                $recordCount = ($filesize - 512) / $recordsize;
                if ($recordCount > 1) {
                    if ($start == 'null') {
                        $toread = FIRST_READ_POINT_COUNT;
                        if ($toread > $recordCount)
                            $toread = $recordCount;
                        fseek($fh, -$toread * $recordsize, SEEK_END);
                    } else {
                        $start       = intVal($start);
                        $end         = intVal($end);
                        $firstRecord = unpack("Lkey/dcur/dmax/dmin", fread($fh, $recordsize));
                        fseek($fh, -$recordsize, SEEK_END);
                        $lastRecord = unpack("Lkey/dcur/dmax/dmin", fread($fh, $recordsize));
                        $posA       = 0;
                        $timeA      = $firstRecord['key'];
                        $posB       = $recordCount;
                        $timeB      = $lastRecord['key'];
                        if ($timeB > $end) // missing latest values
                            {
                            $p = lookForTimeIndex($fh, $end, $posA, $timeA, $posB, $timeB, $recordsize);
                            if ($p < $recordCount - 1) {
                                fseek($fh, 512 + $recordsize * ($p + 1));
                                $toread = $recordCount - $p - 1;
                                if ($toread > REFRESH_READ_POINT_COUNT)
                                    $toread = REFRESH_READ_POINT_COUNT;
                            }
                        } else if ($timeA < $start) // // missing earlier values
                            {
                            $p = lookForTimeIndex($fh, $start, $posA, $timeA, $posB, $timeB, $recordsize);
                            if ($p > 1) {
                                $toseek = $p - REFRESH_READ_POINT_COUNT;
                                if ($toseek < 0)
                                    $toseek = 0;
                                $toread = $p - $toseek;
                                fseek($fh, 512 + $recordsize * $toseek);
                            }
                        }
                    }
                    $first = true;
                    $start = 0;
                    for ($j = 0; $j < $toread; $j++) {
                        $blob   = fread($fh, $recordsize);
                        $record = unpack("Lkey/dcur/dmax/dmin", $blob);
                        $key    = $record['key'];
                        if (!$first) {
                            $mincode .= ',';
                            $maxcode .= ',';
                            $curcode .= ',';
                        } else
                            $start = $key;
                        $key -= $start;
                        $mincode .= '[' . $key . ',' . $record['min'] . ']';
                        $maxcode .= '[' . $key . ',' . $record['max'] . ']';
                        $curcode .= '[' . $key . ',' . $record['cur'] . ']';
                        $first = false;
                    }
                }
                fclose($fh);
                if ($mincode != '') {
                    Print("window.parent.updateGraph('$id',$index,$start,");
                    print('new Array(' . $mincode . "),\n");
                    print('new Array(' . $maxcode . "),\n");
                    print('new Array(' . $curcode);
                    printf("),%d,%s);\n", $resolution, json_encode($unit));
                    // logmsg("//$curcode");
                } else
                    printf("// no data available for  $filename\n");
            } else
                printf("// $filename not found\n");
        }
    }
    Print("window.parent.updateDone('$id');\n");
    Print("</SCRIPT></BODY></HTML>\n");
    //abort("update done");
}
function getBackgroundDefaultSettings()
{
    $res                     = Array();
    $res["BgSolidColor"]     = "";
    $res["BgImgType"]        = "none";
    $res["BgGradientColor1"] = "#f0f0f0";
    $res["BgGradientColor2"] = "#ffffff";
    $res["BgGradientAngle"]  = "90";
    $res["BgImageUrl"]       = "http://www.yoctopuce.com/img/banner.png";
    $res["BgImageRepeat"]    = "no-repeat";
    return $res;
}
function getCallBackFreqDefaultSettings()
{
    $res                = Array();
    $res['freqEnabled'] = false;
    $res['freqMin']     = 300;
    $res['freqMax']     = 3600;
    $res['freqWait']    = 1;
    return $res;
}
function getWakeUpSettingsDefaultSettings()
{
    $res                     = Array();
    $res['wakeUpEnabled']    = false;
    $res['wakeUpAutoSleep']  = false;
    $res['wakeUpSleepAfter'] = 0;
    $res['wakeUpDaysWeek']   = 0;
    $res['wakeUpDaysMonth']  = 0;
    $res['wakeUpMonths']     = 0;
    $res['wakeUpHours']      = 0;
    $res['wakeUpMinutesA']   = 0;
    $res['wakeUpMinutesB']   = 0;
    return $res;
}
function getCleanUpSettingsDefaultSettings()
{
    $res                   = Array();
    $res['cleanUpEnabled'] = false;
    $res['dataTrimSize']   = 40000;
    $res['dataMaxSize']    = 50000;
    return $res;
}
// checks for data subfolder
if (!file_exists(DATAFOLDER) || !is_dir(DATAFOLDER))
    die("<tt>No subfolder named <b>".DATAFOLDER."</b>, please create one.</tt>");

if (!is_writable(DATAFOLDER))
    die("<tt><b>".DATAFOLDER."</b> folder is not writable, please check permissions.</tt>");

if (!ini_get('allow_url_fopen') )
    die("<tt><b>url_fopen</b> is not allowed, please fix server configuration.</tt>");

if (array_key_exists('feed', $_GET)) {
    $s = preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['feed']);
    if ($s != '')
        $FEED = $s;
}
$configfile = DATAFOLDER . "/config_$FEED.ini";
$logfile    = DATAFOLDER . "/log_$FEED.txt";
if (YtestHub("callback", 100, $errmsg) == YAPI_SUCCESS) {
    if (YRegisterHub("callback", $errmsg) != YAPI_SUCCESS) {
        abort('YRegisterHub failed :' . $errsmg);
    }
    $inidata      = null;
    $dataTrimSize = -1;
    $dataMaxSize  = -1;
    if (file_exists($configfile)) {
        $inidata = parse_Ini_File($configfile, true);
        if (array_key_exists('cleanUpSettings', $inidata))
            if (array_key_exists('cleanUpEnabled', $inidata['cleanUpSettings']))
                if (strtoupper($inidata['cleanUpSettings']['cleanUpEnabled']) == 'TRUE') {
                    $dataTrimSize = intval(trim($inidata['cleanUpSettings']['dataTrimSize'], '"'));
                    $dataMaxSize  = intval(trim($inidata['cleanUpSettings']['dataMaxSize'], '"'));
                    if (($dataTrimSize >= $dataMaxSize) || ($dataMaxSize <= 0) || ($dataTrimSize <= 2)) {
                        $dataTrimSize = -1;
                        $dataMaxSize  = -1;
                    }
                }
    }
    // enumerate all available sensor
    $sensor = YSensor::FirstSensor();
    while (!is_null($sensor)) {
        handleSensor($sensor, $dataTrimSize, $dataMaxSize);
        $sensor = $sensor->nextSensor();
    }
    // enumerate all available relay
    $sensor = YRelay::FirstRelay();
    while (!is_null($sensor)) {
        handleSensor($sensor, $dataTrimSize, $dataMaxSize);
        $sensor = $sensor->nextRelay();
    }
    // enumerate all available anButton
    $sensor = YAnButton::FirstAnButton();
    while (!is_null($sensor)) {
        handleSensor($sensor, $dataTrimSize, $dataMaxSize);
        $sensor = $sensor->nextAnButton();
    }
    $network       = YNetwork::FirstNetwork();
    $wakeUp        = YWakeUpSchedule::FirstWakeUpSchedule();
    $wakeUpMonitor = YWakeUpMonitor::FirstWakeUpMonitor();
    $mustGoTosleep = false;
    if (!is_null($inidata)) {
        $mustSave = false;
        if (array_key_exists('wakeUpSettings', $inidata) && (strtoupper($inidata['wakeUpSettings']['wakeUpEnabled']) == 'TRUE')) {
            $mustGoTosleep = strtoupper($inidata['wakeUpSettings']['wakeUpAutoSleep']) == 'TRUE';
            if (!is_null($wakeUp) && (($wakeUp->get_weekDays() != $inidata['wakeUpSettings']['wakeUpDaysWeek']) || ($wakeUp->get_monthDays() != $inidata['wakeUpSettings']['wakeUpDaysMonth']) || ($wakeUp->get_months() != $inidata['wakeUpSettings']['wakeUpMonths']) || ($wakeUp->get_hours() != $inidata['wakeUpSettings']['wakeUpHours']) || ($wakeUp->get_minutesA() != $inidata['wakeUpSettings']['wakeUpMinutesA']) || ($wakeUp->get_MinutesB() != $inidata['wakeUpSettings']['wakeUpMinutesB']))) {
                logmsg("$snow updating Wakeup schedule");
                $wakeUp->set_weekDays($inidata['wakeUpSettings']['wakeUpDaysWeek']);
                $wakeUp->set_monthDays($inidata['wakeUpSettings']['wakeUpDaysMonth']);
                $wakeUp->set_months($inidata['wakeUpSettings']['wakeUpMonths']);
                $wakeUp->set_hours($inidata['wakeUpSettings']['wakeUpHours']);
                $wakeUp->set_minutesA($inidata['wakeUpSettings']['wakeUpMinutesA']);
                $wakeUp->set_MinutesB($inidata['wakeUpSettings']['wakeUpMinutesB']);
                $mustSave = true;
            }
            if (!is_null($wakeUpMonitor)) {
                $w = $inidata['wakeUpSettings']['wakeUpSleepAfter'];
                if ($wakeUpMonitor->get_powerDuration() != $w) {
                    logmsg("$snow  updating Wake power duration to $w");
                    $wakeUpMonitor->set_powerDuration($w);
                    $mustSave = true;
                }
            }
        }
        if (array_key_exists('callBackFreq', $inidata))
            if (strtoupper($inidata['callBackFreq']['freqEnabled']) == 'TRUE') {
                $min  = intVal($inidata['callBackFreq']['freqMin']);
                $max  = intVal($inidata['callBackFreq']['freqMax']);
                $wait = intVal($inidata['callBackFreq']['freqWait']);
                //logmsg("$snow  current CallbackMin  = ".$network->get_callbackMinDelay());
                //logmsg("$snow  current CallbackMax  = ".$network->get_callbackMaxDelay());
                //logmsg("$snow  current CallbackWait = ".$network->get_callbackInitialDelay());
                if ($network->get_callbackMinDelay() != $min) {
                    logmsg("$snow updating callbackMinDelay to $min");
                    $network->set_callbackMinDelay($min);
                    $mustSave = true;
                }
                if ($network->get_callbackMaxDelay() != $max) {
                    logmsg("$snow updating callbackMaxDelay to $max");
                    $network->set_callbackMaxDelay($max);
                    $mustSave = true;
                }
                if ($network->get_callbackInitialDelay() != $wait) {
                    logmsg("$snow updating callbackInitialDelay to $wait");
                    $network->set_callbackInitialDelay($wait);
                    $mustSave = true;
                }
            }
        if ($mustSave)
            $network->get_module()->saveToFlash();
        if (($mustGoTosleep) && !is_null($wakeUpMonitor)) {
            logmsg("$snow  Going to Sleep...");
            $wakeUpMonitor->sleep(1);
        }
    }
    RunTrimProcess();
    ;
    abort("$snow Done.");
}
// load all sensor data for the sensor data.
$sensors         = Array();
$background      = getBackgroundDefaultSettings();
$callBackFreq    = getCallBackFreqDefaultSettings();
$wakeUpSettings  = getWakeUpSettingsDefaultSettings();
$cleanUpSettings = getCleanUpSettingsDefaultSettings();
if ($handle = opendir(DATAFOLDER)) {
    while (false !== ($entry = readdir($handle))) {
        if ((substr($entry, 0, strlen($FEED) + 5) == 'data-' . $FEED) && (substr($entry, -4, 4) == '.bin')) {
            $sensors[] = new Sensor($entry);
        }
    }
    closedir($handle);
}
function currentURL()
{
    $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
    $host     = $_SERVER['HTTP_HOST'];
    $script   = $_SERVER['SCRIPT_NAME'];
    return $protocol . '://' . $host . $script;
}
function addJsCode($code)
{
    print("<SCRIPT>\n$code\n</SCRIPT>\n");
}
function printJsSaveCode()
{
    global $FEED;
    printf(IFRAMEHEADER."\n<form id='myform' action='%s?cmd=saveconfig&feed=$FEED' method='post'>\n<textArea name='data' id='data'></textArea>\n</form>\n", currentURL());
    addJsCode("document.getElementById('data').value=window.parent.getConfigData();\n" . "document.getElementById('myform').submit();");
    die();
}
function printLogFileContent()
{
    global $logfile;
    $data = '"No Logs available."';
    if (file_exists($logfile))
        $data = json_encode(file_get_contents($logfile));
    print(IFRAMEHEADER."<script>\n");
    print("window.parent.updateLogWindow(");
    Print($data);
    Print(");");
    print("</script></body></html>\n");
    die('Done.');
}
function printRawDataContent()
{
    global $FEED;
    $datafileNames = glob(DATAFOLDER . "/data-$FEED-*.bin");
    asort($datafileNames);
    if (sizeof($datafileNames) <= 0)
        die('no data');
    $data          = Array();
    $recordsize    = getRecordSize();
    $lastTimeStamp = 0;
    for ($i = 0; $i < sizeof($datafileNames); $i++)
        if (filesize($datafileNames[$i]) > 512) {
            $size = filesize($datafileNames[$i]);
            $fh   = fopen($datafileNames[$i], "rb");
            if ($fh === FALSE)
                die("can't open {$datafileNames[$i]}");
            $header = fread($fh, 512);
            $cdata  = unpack("a*", $header);
            $ini    = parse_ini_string($cdata[1], true);
            $toRead = ($size - 512) / $recordsize;
            if ($toRead > RAWDATACLUSTERSIZE)
                $toRead = RAWDATACLUSTERSIZE;
            fseek($fh, -$toRead * $recordsize, SEEK_END);
            $data[$i] = Array(
                'filename' => $datafileNames[$i],
                'header' => $ini,
                'data' => Array(),
                'index' => 0
            );
            for ($j = 0; $j < $toRead; $j++) {
                $blob               = fread($fh, $recordsize);
                $data[$i]['data'][] = unpack("Lkey/dcur/dmax/dmin", $blob);
            }
            $data[$i]['index'] = $toRead - 1;
            fclose($fh);
        }

    $code = IFRAMEHEADER."<script>\n" . "window.parent.updateRawDataWindow([";
    for ($i = 0; $i < sizeof($data); $i++)
        $code .= (($i > 0) ? ',' : '') . '"' . $data[$i]['header']['sensor']['logicalname'] . '"';
    $code .= "],\n[";
    for ($i = 0; $i < sizeof($data); $i++)
        $code .= (($i > 0) ? ',' : '') . $data[$i]['header']['sensor']['resolution'];
    $code .= "],\n[";
    $done  = false;
    $first = true;
    while (!$done) {
        $nextTimeStamp = 0;
        for ($i = 0; $i < sizeof($data); $i++) {
            $index = $data[$i]['index'];
            if ($index >= 0)
                if ($data[$i]['data'][$index]['key'] > $nextTimeStamp)
                    $nextTimeStamp = $data[$i]['data'][$index]['key'];
        }
        if ($nextTimeStamp > 0) {
            $code .= $first ? '[' : ',[';
            $code .= "'" . $nextTimeStamp . "'" . ',[';
            for ($i = 0; $i < sizeof($data); $i++) {
                $index = $data[$i]['index'];
                if ($index >= 0) {
                    if ($data[$i]['data'][$index]['key'] == $nextTimeStamp) {
                        $code .= (($i > 0) ? ',' : '') . "[" . $data[$i]['data'][$index]['min'] . ',' . $data[$i]['data'][$index]['cur'] . ',' . $data[$i]['data'][$index]['max'] . ']';
                        $data[$i]['index']--;
                        if ($data[$i]['index'] < 0)
                            $done = true;
                    } else
                        $code .= (($i > 0) ? ',' : '') . "[null,null,null]";
                } else
                    $code .= (($i > 0) ? ',' : '') . "[null,null,null]";
            }
            $code .= "]]\n";
        } else
            $done = true;
        $first = false;
    }
    $code .= ']);';
    $code .= "</script></body></html>\n";
    print($code);
}
function printCsvDataContent($timeOffset)
{
    $timeOffset += date('Z');
    global $FEED;
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=$FEED.csv");
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    $datafileNames = glob(DATAFOLDER . "/data-$FEED-*.bin");
    asort($datafileNames);
    if (sizeof($datafileNames) <= 0)
        die('no data');
    $data          = Array();
    $recordsize    = getRecordSize();
    $lastTimeStamp = 0;
    for ($i = 0; $i < sizeof($datafileNames); $i++)
        if (filesize($datafileNames[$i]) > 512) {
            $size = filesize($datafileNames[$i]);
            $fh   = fopen($datafileNames[$i], "rb");
            if ($fh === FALSE)
                die("can't open {$datafileNames[$i]}");
            $header = fread($fh, 512);
            $cdata  = unpack("a*", $header);
            $ini    = parse_ini_string($cdata[1], true);
            fseek($fh, 512);
            $dataTotal = ($size - 512) / $recordsize;
            $toRead    = $dataTotal;
            if ($toRead > RAWDATACLUSTERSIZE)
                $toRead = RAWDATACLUSTERSIZE;
            $data[$i] = Array(
                'filename' => $datafileNames[$i],
                'header' => $ini,
                'data' => Array(),
                'index' => 0,
                'offset' => 512,
                'totalsize' => $size
            );
            for ($j = 0; $j < $toRead; $j++) {
                $blob               = fread($fh, $recordsize);
                $data[$i]['data'][] = unpack("Lkey/dcur/dmax/dmin", $blob);
            }
            $data[$i]['offset'] = ftell($fh);
            $data[$i]['index']  = 0;
            fclose($fh);
        }
    print('"Time"');
    for ($i = 0; $i < sizeof($data); $i++) {
        Print(';"' . $data[$i]['header']['sensor']['logicalname'] . '(min)"');
        Print(';"' . $data[$i]['header']['sensor']['logicalname'] . '(cur)"');
        Print(';"' . $data[$i]['header']['sensor']['logicalname'] . '(max)"');
    }
    Print("\r\n");
    $done = false;
    while (!$done) {
        for ($i = 0; $i < sizeof($data); $i++) {
            if (($data[$i]['index'] >= sizeof($data[$i]['data'])) && ($data[$i]['offset'] < $data[$i]['totalsize'])) {
                $fh = fopen($datafileNames[$i], "rb");
                if ($fh === FALSE)
                    die("can't open {$datafileNames[$i]}");
                fseek($fh, $data[$i]['offset']);
                $totalAvailable = ($data[$i]['totalsize'] - $data[$i]['offset']) / $recordsize;
                $toRead         = $totalAvailable;
                if ($toRead > RAWDATACLUSTERSIZE)
                    $toRead = RAWDATACLUSTERSIZE;
                $data[$i]['data'] = Array();
                for ($j = 0; $j < $toRead; $j++) {
                    $blob               = fread($fh, $recordsize);
                    $data[$i]['data'][] = unpack("Lkey/dcur/dmax/dmin", $blob);
                }
                $data[$i]['offset'] = ftell($fh);
                $data[$i]['index']  = 0;
                fclose($fh);
            }
        }
        $nextTimeStamp = -1;
        for ($i = 0; $i < sizeof($data); $i++) {
            $index = $data[$i]['index'];
            if ($index < sizeof($data[$i]['data']))
                if (($data[$i]['data'][$index]['key'] < $nextTimeStamp) || ($nextTimeStamp < 0))
                    $nextTimeStamp = $data[$i]['data'][$index]['key'];
        }
        if ($nextTimeStamp > 0) {
            $line = '"' . date('Y-m-d H:i:s', $nextTimeStamp + $timeOffset) . '"';
            for ($i = 0; $i < sizeof($data); $i++) {
                $index = $data[$i]['index'];
                if ($index < sizeof($data[$i]['data'])) {
                    if ($data[$i]['data'][$index]['key'] == $nextTimeStamp) {
                        $line .= ';' . $data[$i]['data'][$index]['min'] . ';' . $data[$i]['data'][$index]['cur'] . ';' . $data[$i]['data'][$index]['max'];
                        $data[$i]['index']++;
                    } else
                        $line .= (($i > 0) ? ';' : '') . ";;";
                } else
                    $line .= (($i > 0) ? ';' : '') . ";;";
            }
            $line .= "\r\n";
            print($line);
        } else
            $done = true;
    }
    die();
}
function saveConfig()
{
    global $configfile;
    $inidata        = $_POST['data'];
    $data           = parse_ini_string($_POST['data'], true);
    $checkedinifile = '';
    foreach ($data as $key => $section) {
        if (substr($key, 0, 6) == 'Widget') {
            $checkedinifile .= "[$key]\n";
            foreach ($section as $subkey => $value)
                $checkedinifile .= "$subkey=\"$value\"\n";
        }
        if (($key == 'background') || ($key == 'callBackFreq') || ($key == 'wakeUpSettings') || ($key == 'cleanUpSettings')) {
            $checkedinifile .= "[$key]\n";
            foreach ($section as $subkey => $value)
                $checkedinifile .= "$subkey=\"$value\"\n";
        }
    }
    if (file_put_contents($configfile, $checkedinifile) === FALSE) {
        addJsCode("window.parent.actionFailed('failed to save $configfile file');");
        die("failed to save $configfile");
    } else {
        addJsCode("window.parent.actionSuccess('Saved.');");
        die("$configfile saved");
    }
}
function deleteFile($filename)
{
    if (substr($filename, -4) == '.bin') {
        logmsg('Deleting ' . $filename);
        if (file_exists($filename))
            unlink($filename);
        $filename = substr($filename, 0, strlen($filename) - 4) . '.TMP';
        if (file_exists($filename))
            unlink($filename);
    }
    addJsCode("window.parent.refreshCleanUpWindow();");
    abort('done.');
}
function printDataFileStats()
{
    global $FEED;
    $datafileNames = glob(DATAFOLDER . "/data-$FEED-*.bin");
    asort($datafileNames);
    $data       = Array();
    $recordsize = getRecordSize();
    for ($i = 0; $i < sizeof($datafileNames); $i++) {
        $size = filesize($datafileNames[$i]);
        $fh   = fopen($datafileNames[$i], "rb");
        if ($fh === FALSE)
            die("can't open {$datafileNames[$i]}");
        $header = fread($fh, 512);
        fclose($fh);
        $cdata              = unpack("a*", $header);
        $ini                = parse_ini_string($cdata[1], true);
        $el                 = Array();
        $el['file']         = $datafileNames[$i];
        $el['filesize']     = $size;
        $el['name']         = $ini['sensor']['logicalname'];
        $el['recordscount'] = ($size - 512) / $recordsize;
        $el['lastupdate']   = strtotime($ini['sensor']['lastupdate']);
        $data[]             = $el;
    }
    print(IFRAMEHEADER."<script>\n");
	print("window.parent.updatefileDetails(");
	Print(json_encode($data));
	Print(");");
	print("</script></body></html>\n");
	die('Done.');

   var_dump($data);
   die('done');

 }



if (array_key_exists('cmd',$_GET))

 { $cmd =$_GET['cmd'];
   switch($cmd)

   {  case 'getcsv'     : $UTCoffset=0;
                          if (array_key_exists('UTCoffset',$_GET)) $UTCoffset=intVal($_GET['UTCoffset']);
                          printCsvDataContent($UTCoffset); die('done.');break;
      case 'showRawData': printRawDataContent(); die('done.');break;
      case 'showDataStat': printDataFileStats() ;die('done.');break;
      case 'showLog'    : printLogFileContent(); die('done.');break;
      case 'jssave'     : printJsSaveCode();     die('done.');break;
      case 'saveconfig' : saveConfig();          die('done.'); break;
      case 'getlastestdata' : loadLatestData($_GET['feed']);  die('done.'); break;
      case 'showDataFileStats' : printDataFileStats();die('done.'); break;
      case 'delete'     : if (array_key_exists('file',$_GET)) deleteFile($_GET['file']);
					      die('done');
						  break;
      case 'getdata'    : loadGraphData($_GET['feed'],$_GET['id'],$_GET['indexes'],$_GET['name'],$_GET['time']);
	                     die('done.');
	                     break;
   }
   die('invalid command');
 }

define("TYPE_FLOAT" , 0);
define("TYPE_PERCENT" , 1);
define("TYPE_INT"   , 2);
define("TYPE_PX"    , 3);
define("TYPE_COLOR" , 4);
define("TYPE_STRING" ,5);
define("TYPE_DATASRC",6);
define("TYPE_LEFTRIGHT",7);
define("TYPE_YAXIS",8);
define("TYPE_BOOL",9);
define("TYPE_HALIGN",10);
define("TYPE_VALIGN",11);
define("TYPE_LAYOUT",12);
define("TYPE_LONGSTRING",13);
define("TYPE_FONTSIZE" ,14);
define("TYPE_TALIGN" ,15);
define("TYPE_PRECISION" ,16);


define( "STYLE_OBJ"      , 1);
define( "GRAPH_OBJ"      , 2);
define( "SERIE_OBJ"      , 4);
define( "YAXIS_OBJ"      , 8);
define( "YAXISTITLE_OBJ" ,16);
define( "TITLE_OBJ"      ,32);
define( "LEGEND_OBJ"     ,64);
define( "NAVIGATOR_OBJ" ,128);
define( "DIV_OBJ"       ,256);
define( "DECOTEXT_OBJ"  ,512);
define( "MANUAL_APPLY"  ,1024);
define( "SCROLLBAR_OBJ" ,2048);
define( "RANGESEL_OBJ",  4096);
define( "XAXISSTYLE_OBJ",8192);
define( "YAXISSTYLE_OBJ",16384);
define( "XAXIS_OBJ"     ,32768);
define( "TITLESTYLE_OBJ",16384);
define( "YBAND_OBJ",     32768);
define( "EXPORTBTN_OBJ", 65536);
define( "NOTHING"       , 0);

$defaultColor= Array('#FF0000','#0000FF','#000000');

$DECO_EDITABLE_VALUES =
 Array(  Array("caption"=> "Window ",         "editable"=>false, "columnBreak" => true,       "appliesTo"=>NOTHING),
         Array("caption"=> "Left",            "prefix"=>"",      "name"=>"left",           "type"=> TYPE_PERCENT,   "defaultValue"=>"25+2*int_index","nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget left position, relative to browser window width.'),
         Array("caption"=> "Top",             "prefix"=>"",      "name"=>"top",            "type"=> TYPE_PERCENT,   "defaultValue"=>"25+2*int_index","nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget top position, relative to browser window height.'),
         Array("caption"=> "Width",           "prefix"=>"",      "name"=>"width",          "type"=> TYPE_PERCENT,   "defaultValue"=>"40"            ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget width, relative to browser window width.'),
         Array("caption"=> "Z-index",         "prefix"=>"",      "name"=>"zIndex",         "type"=> TYPE_INT,       "defaultValue"=>"0"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Vertical position, the widget with the higher index will in front of all others.'),
       //Array("caption"=> "Height",          "prefix"=>"",      "name"=>"height",         "type"=> TYPE_PERCENT,   "defaultValue"=>"10"            ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ),
         Array("caption"=> "Border width",    "prefix"=>"",      "name"=>"borderWidth",    "type"=> TYPE_PX,        "defaultValue"=>"1"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget border width, in pixels.'),
         Array("caption"=> "Border color",    "prefix"=>"",      "name"=>"borderColor",    "type"=> TYPE_COLOR,     "defaultValue"=>"#000000"       ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget border color, as a HTLM color code.'),
         Array("caption"=> "Border radius",   "prefix"=>"",      "name"=>"borderRadius",   "type"=> TYPE_PX,        "defaultValue"=>"0"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget border radius, in pixels.'),
         Array("caption"=> "Left Margin",     "prefix"=>"",      "name"=>"paddingLeft",    "type"=> TYPE_PX,        "defaultValue"=>"5"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget inner left margin, in pixels.'),
         Array("caption"=> "Right Margin",    "prefix"=>"",      "name"=>"paddingRight",   "type"=> TYPE_PX,        "defaultValue"=>"5"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget inner right margin, in pixels.'),
   	     Array("caption"=> "Top Margin",      "prefix"=>"",      "name"=>"paddingTop",     "type"=> TYPE_PX,        "defaultValue"=>"5"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget inner top margin, in pixels.'),
         Array("caption"=> "Bottom Margin",   "prefix"=>"",      "name"=>"paddingBottom",  "type"=> TYPE_PX,        "defaultValue"=>"5"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget inner bottom margin, in pixels.'),
   	     Array("caption"=> "Bg color"        ,"prefix"=>"",      "name"=>"backgroundColor","type"=> TYPE_COLOR,     "defaultValue"=>"#f0f0f0"       ,"nullable"=>true, "editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget background color,  as a HTLM color code.'),
	     Array("caption"=> "Text",            "prefix"=>"",      "name"=>"innerHTML",      "type"=> TYPE_LONGSTRING,"defaultValue"=>"Hello World!"  ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>DECOTEXT_OBJ,"hint"=>'Widget inner text, you can use variables {$1}...{$'.MAX_VARIABLE_PER_DECO.'}. HTML code is supported, but not checked.'),
         Array("caption"=> "Size",            "prefix"=>"",      "name"=>"fontSize",       "type"=> TYPE_FONTSIZE,  "defaultValue"=>"2"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget inner text size.'),
         Array("caption"=> "Color",           "prefix"=>"",      "name"=>"color",          "type"=> TYPE_COLOR,     "defaultValue"=>"#000000"       ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget inner text color, as a HTLM color code.'),
         Array("caption"=> "Align",           "prefix"=>"",      "name"=>"textAlign",      "type"=> TYPE_TALIGN,    "defaultValue"=>"center"        ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Widget typographic alignment.')


  );

$DECO_EDITABLE_VALUES[]= Array("caption"=> "Variables ",         "editable"=>false, "columnBreak" => true,       "appliesTo"=>NOTHING);
for ($i=0;$i<MAX_VARIABLE_PER_DECO;$i++)
  { $DECO_EDITABLE_VALUES[]=Array("caption"=> "Variable ".($i+1),"editable"=>false, "columnBreak" => false,   "appliesTo"=>NOTHING);
    $DECO_EDITABLE_VALUES[]=Array("caption"=> "Data src",   "prefix"=>"var",     "name"=>"datasource",     "type"=> TYPE_DATASRC, "defaultValue"=>null        ,"nullable"=>false,"editable"=>true,"index"=>$i,"appliesTo"=>DECOTEXT_OBJ,"hint"=>'Source of data: Yoctopuce Sensor, relay or AnButton.');
    $DECO_EDITABLE_VALUES[]=Array("caption"=> "Precision",  "prefix"=>"var",     "name"=>"precision",       "type"=> TYPE_PRECISION,    "defaultValue"=>1     ,"nullable"=>false,"editable"=>true,"index"=>$i,"appliesTo"=>DECOTEXT_OBJ,"hint"=>'Displayed precision');
    $DECO_EDITABLE_VALUES[]=Array("caption"=> "Show unit",  "prefix"=>"var",     "name"=>"showunit",       "type"=> TYPE_BOOL,    "defaultValue"=>true        ,"nullable"=>false,"editable"=>true,"index"=>$i,"appliesTo"=>DECOTEXT_OBJ,"hint"=>'Shoes the data-source unit as defined by the Yoctopuce sensor.');

  }




$GRAPH_EDITABLE_VALUES =
 Array(  Array("caption"=> "Window ",         "editable"=>false, "columnBreak" => true,       "appliesTo"=>NOTHING),
         Array("caption"=> "Left",            "prefix"=>"",      "name"=>"left",           "type"=> TYPE_PERCENT, "defaultValue"=>"25+2*int_index","nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Chart frame left position, relative to browser window width.'),
         Array("caption"=> "Top",             "prefix"=>"",      "name"=>"top",            "type"=> TYPE_PERCENT, "defaultValue"=>"25+2*int_index","nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Chart frame top position, relative to browser window height.'),
         Array("caption"=> "Width",           "prefix"=>"",      "name"=>"width",          "type"=> TYPE_PERCENT, "defaultValue"=>"50"            ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Chart frame width, relative to browser window width.'),
         Array("caption"=> "Height",          "prefix"=>"",      "name"=>"height",         "type"=> TYPE_PERCENT, "defaultValue"=>"50"            ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Chart frame height, relative to browser window height.'),
         Array("caption"=> "Z-index",         "prefix"=>"",      "name"=>"zIndex",         "type"=> TYPE_INT,     "defaultValue"=>"0"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Vertical position, the widget with the higher index will in front of all others.'),
         Array("caption"=> "Border width",    "prefix"=>"",      "name"=>"borderWidth",    "type"=> TYPE_PX,      "defaultValue"=>"1"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Chart frame border width, in pixels.'),
         Array("caption"=> "Border color",    "prefix"=>"",      "name"=>"borderColor",    "type"=> TYPE_COLOR,   "defaultValue"=>"#000000"       ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ,"hint"=>'Chart frame color, as a HTLM color code.'),
         Array("caption"=> "Border radius",   "prefix"=>"",      "name"=>"borderRadius",   "type"=> TYPE_PX,      "defaultValue"=>"0"             ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>STYLE_OBJ |GRAPH_OBJ,"hint"=>'Chart frame border radius, in pixels.'),
         Array("caption"=> "Left Margin",     "prefix"=>"",      "name"=>"marginLeft",     "type"=> TYPE_INT,     "defaultValue"=>"75"            ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>GRAPH_OBJ,"hint"=>'Chart frame inner left margin, in pixels.'),
         Array("caption"=> "Right Margin",    "prefix"=>"",      "name"=>"marginRight",    "type"=> TYPE_INT,     "defaultValue"=>"20"            ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>GRAPH_OBJ,"hint"=>'Chart frame inner right margin,, in pixels.'),
         Array("caption"=> "Title",           "prefix"=>"title", "name"=>"text",           "type"=> TYPE_STRING,  "defaultValue"=>"Title"         ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>TITLE_OBJ,"hint"=>'Chart title.'),
         Array("caption"=> "Bg color"        ,"prefix"=>"",      "name"=>"backgroundColor","type"=> TYPE_COLOR,   "defaultValue"=>"#f0f0f0"       ,"nullable"=>true, "editable"=>true,"index"=>null,"appliesTo"=>GRAPH_OBJ,"hint"=>'Chart frame background color, as a HTLM color code.')

  );
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "X axis Color",  "prefix"=>""        ,"name"=>"lineColor",    "type"=> TYPE_COLOR,  "defaultValue"=>"#404040"   ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>XAXIS_OBJ ,"hint"=>'X Axis lin2 color');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "LabelsColor",   "prefix"=>"labStyle","name"=>"color",        "type"=> TYPE_COLOR,  "defaultValue"=>"#404040"   ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>XAXISSTYLE_OBJ | YAXISSTYLE_OBJ | TITLESTYLE_OBJ ,"hint"=>'Axis and title color');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Navigator",     "prefix"=>"nav",     "name"=>"enabled",      "type"=> TYPE_BOOL,   "defaultValue"=>false       ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>NAVIGATOR_OBJ,"hint"=>'Show chart overview');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "ScrollBar",     "prefix"=>"scrlb",   "name"=>"enabled",      "type"=> TYPE_BOOL,   "defaultValue"=>true        ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>SCROLLBAR_OBJ,"hint"=>'Show chart horizontal scroll bar');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Range selector","prefix"=>"rgsel",   "name"=>"enabled",      "type"=> TYPE_BOOL,   "defaultValue"=>true        ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>RANGESEL_OBJ,"hint"=>'Show chart Range selector');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Export button" ,"prefix"=>"expbtn",  "name"=>"enabled",      "type"=> TYPE_BOOL,   "defaultValue"=>false       ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>EXPORTBTN_OBJ,"hint"=>'Show chart export menu');


  $GRAPH_EDITABLE_VALUES[] = Array("caption"=> "Data sets",         "editable"=>false, "columnBreak" => true,       "appliesTo"=>NOTHING);
  for ($i=0;$i<MAX_DATASRC_PER_GRAPH;$i++)
  { $GRAPH_EDITABLE_VALUES[]= Array("caption"=>"Data Set ".($i+1),     "editable"=>false,     "columnBreak" => false,         "appliesTo"=>NOTHING);
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Description",   "prefix"=>"data",   "name"=>"name",           "type"=> TYPE_STRING,  "defaultValue"=>"Serie $i"        ,"nullable"=>false,"editable"=>true,"index"=>$i    ,"appliesTo"=>SERIE_OBJ,"hint"=>'Data source display name.');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Data src",      "prefix"=>"data",   "name"=>"datasource",     "type"=> TYPE_DATASRC, "defaultValue"=>null              ,"nullable"=>false,"editable"=>true,"index"=>$i    ,"appliesTo"=>GRAPH_OBJ,"hint"=>'Source of data: Yoctopuce Sensor, relay or AnButton.');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Color",         "prefix"=>"data",   "name"=>"color",          "type"=> TYPE_COLOR,   "defaultValue"=>$defaultColor[$i] ,"nullable"=>false,"editable"=>true,"index"=>$i    ,"appliesTo"=>SERIE_OBJ,"hint"=>'Chart line color for this source');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Line width",    "prefix"=>"data",   "name"=>"lineWidth",      "type"=> TYPE_INT,     "defaultValue"=>2                 ,"nullable"=>false,"editable"=>true,"index"=>$i    ,"appliesTo"=>SERIE_OBJ,"hint"=>'Chart line Width for this source');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Y axis",        "prefix"=>"data",   "name"=>"yAxis",          "type"=> TYPE_YAXIS,   "defaultValue"=>0                 ,"nullable"=>false,"editable"=>true,"index"=>$i    ,"appliesTo"=>SERIE_OBJ,"hint"=>'Reference Y axis');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Show max",      "prefix"=>"data",   "name"=>"showmax",        "type"=> TYPE_BOOL,    "defaultValue"=>false             ,"nullable"=>false,"editable"=>true,"index"=>$i    ,"appliesTo"=>MANUAL_APPLY,"hint"=>'Shoes the maximum values chart.');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Show min",      "prefix"=>"data",   "name"=>"showmin",        "type"=> TYPE_BOOL,    "defaultValue"=>false             ,"nullable"=>false,"editable"=>true,"index"=>$i    ,"appliesTo"=>MANUAL_APPLY,"hint"=>'Shoes the minimum values chart.');
  }

 $GRAPH_EDITABLE_VALUES[] = Array("caption"=> "Vertical axis",         "editable"=>false, "columnBreak" => true,       "appliesTo"=>NOTHING);
 for ($i=0;$i<MAX_YAXIS_PER_GRAPH;$i++)
  { $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Axis ".($i+1),"editable"=>false, "columnBreak" => false,   "appliesTo"=>NOTHING);
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Title",   "prefix"=>"yAxis",  "name"=>"text",          "type"=> TYPE_STRING,    "defaultValue"=>"Axis ".($i+1)   ,"nullable"=>false, "editable"=>true, "index"=>$i, "appliesTo"=>YAXISTITLE_OBJ,"hint"=>'Axis title');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Min",     "prefix"=>"yAxis",  "name"=>"min",           "type"=> TYPE_INT,       "defaultValue"=>NULL             ,"nullable"=>true,  "editable"=>true, "index"=>$i, "appliesTo"=>YAXIS_OBJ,"hint"=>'Axis start value , leave blank for automatic selection');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Max",     "prefix"=>"yAxis",  "name"=>"max",           "type"=> TYPE_INT,       "defaultValue"=>NULL             ,"nullable"=>true,  "editable"=>true, "index"=>$i, "appliesTo"=>YAXIS_OBJ,"hint"=>'Axis end value , leave blank for automatic selection');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Position","prefix"=>"yAxis",  "name"=>"opposite",      "type"=> TYPE_LEFTRIGHT, "defaultValue"=>0                ,"nullable"=>false, "editable"=>true, "index"=>$i, "appliesTo"=>YAXIS_OBJ,"hint"=>'Axis side');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Line color","prefix"=>"yAxis","name"=>"lineColor",      "type"=> TYPE_COLOR,    "defaultValue"=>"#404040"        ,"nullable"=>true,  "editable"=>true, "index"=>$i,"appliesTo"=>YAXIS_OBJ,"hint"=>'Y axis vertical line color.');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Grid color","prefix"=>"yAxis","name"=>"gridLineColor", "type"=> TYPE_COLOR,     "defaultValue"=>"#D0D0D0"        ,"nullable"=>true,  "editable"=>true, "index"=>$i,"appliesTo"=>YAXIS_OBJ,"hint"=>'Horizontal grid lines color.');
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Visible", "prefix"=>"yAxis", "name"=>"visible",        "type"=> TYPE_BOOL,      "defaultValue"=>$i>0?false:true  ,"nullable"=>false, "editable"=>true, "index"=>$i, "appliesTo"=>YAXIS_OBJ,"hint"=>'Show/hide axis');

  }

  $GRAPH_EDITABLE_VALUES[] = Array("caption"=> "Horizontal bands",         "editable"=>false, "columnBreak" => true,       "appliesTo"=>NOTHING);
 for ($i=0;$i<MAX_YBAND_PER_GRAPH;$i++)
  { $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Band  ".($i+1),"editable"=>false, "columnBreak" => false,   "appliesTo"=>NOTHING);
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Color",     "prefix"=>"yBand",  "name"=>"color",       "type"=> TYPE_COLOR,    "defaultValue"=>NULL    ,"nullable"=>true,  "editable"=>true, "index"=>$i,"appliesTo"=>YBAND_OBJ,"hint"=>"Horizontal band $i color, applies to first Y axis only, leave blank to disable");
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Start",     "prefix"=>"yBand",  "name"=>"from",        "type"=> TYPE_FLOAT,       "defaultValue"=>0      ,"nullable"=>false,  "editable"=>true, "index"=>$i, "appliesTo"=>YBAND_OBJ,"hint"=>"Band $i lower edge");
    $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "End",       "prefix"=>"yBand",  "name"=>"to",          "type"=> TYPE_FLOAT,       "defaultValue"=>0     ,"nullable"=>false,  "editable"=>true, "index"=>$i, "appliesTo"=>YBAND_OBJ,"hint"=>"Band $i upper edge");
  }


  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Legend",          "editable"=>false,    "columnBreak" => true,  "appliesTo"=>NOTHING);
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Enabled",         "prefix"=>"legend",   "name"=>"enabled",        "type"=> TYPE_BOOL,   "defaultValue"=>false       ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Show/hide legend.');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Hrz. Align",      "prefix"=>"legend",   "name"=>"align",          "type"=> TYPE_HALIGN, "defaultValue"=>"right"     ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Legend left/right alignment.');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Vert. align",     "prefix"=>"legend",   "name"=>"verticalAlign",  "type"=> TYPE_VALIGN, "defaultValue"=>"bottom"    ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Legend top/bottom alignment.');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "X offset",        "prefix"=>"legend",   "name"=>"x",              "type"=> TYPE_INT,    "defaultValue"=>0           ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Legend position horizontal offset, in pixels.');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Y offset",        "prefix"=>"legend",   "name"=>"y",              "type"=> TYPE_INT,    "defaultValue"=>0           ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Legend position vertical offset, in pixels.');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Layout",          "prefix"=>"legend",   "name"=>"layout",         "type"=> TYPE_LAYOUT, "defaultValue"=>"vertical"  ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Data sources names disposition.');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Floating",        "prefix"=>"legend",   "name"=>"floating",       "type"=> TYPE_BOOL   ,"defaultValue"=>"true"      ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Allows the legend to float over the graph elements');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Border Width",    "prefix"=>"legend",   "name"=>"borderWidth",    "type"=> TYPE_INT,     "defaultValue"=>1          ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Legend border width.');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Border Radius",   "prefix"=>"legend",   "name"=>"borderRadius",   "type"=> TYPE_PX,     "defaultValue"=>5           ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Legend corder radius.');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Border Color",    "prefix"=>"legend",   "name"=>"borderColor",    "type"=> TYPE_COLOR,   "defaultValue"=>"#000000"  ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Legend text color, as a HTLM color code.');
  $GRAPH_EDITABLE_VALUES[]=Array("caption"=> "Bg Color" ,       "prefix"=>"legend",   "name"=>"backgroundColor","type"=> TYPE_COLOR,   "defaultValue"=>"#FFFFFF"  ,"nullable"=>false,"editable"=>true,"index"=>null,"appliesTo"=>LEGEND_OBJ,"hint"=>'Legend background, as a HTLM color code.');



  function editMode()
  {
    return array_key_exists('edit',$_GET);
  }
  function editPartstart()
  {  if (!editMode()) ob_start();
  }

  function editPartEnd()
  {  if (!editMode()) ob_end_clean();
  }

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<HTML>
<HEAD>
<meta http-equiv="Content-Type" content="text/html; charset=windows-1252">
<meta name='viewport' content='width=450'>


<?php
function LoadSectionFromIni(&$variable, $inidata, $section)
{
    if (array_key_exists($section, $inidata)) {
        $iniSection = $inidata[$section];
        foreach ($variable as $key => $value)
            if (array_key_exists($key, $iniSection)) {
                if (is_bool($variable[$key]))
                    $variable[$key] = strtoupper($iniSection[$key]) == 'TRUE';
                else if (is_int($variable[$key]))
                    $variable[$key] = intVal($iniSection[$key]);
                else
                    $variable[$key] = $iniSection[$key];
            }
    }
}
$inidata = null;
if (file_exists($configfile)) {
    $inidata = parse_Ini_File($configfile, true);
    LoadSectionFromIni($background, $inidata, 'background');
    LoadSectionFromIni($callBackFreq, $inidata, 'callBackFreq');
    LoadSectionFromIni($wakeUpSettings, $inidata, 'wakeUpSettings');
    LoadSectionFromIni($cleanUpSettings, $inidata, 'cleanUpSettings');
}
?>

<style>
BODY
{ font-family: Helvetica, Arial, Sans-Serif;
<?php
if ($background["BgSolidColor"] != '')
    Printf("  background-color:%s;\r\n", $background["BgSolidColor"]);
switch ($background["BgImgType"]) {
    case "gradient":
        Printf("  background: linear-gradient(%sdeg, %s, %s);\r\n", $background["BgGradientAngle"], $background["BgGradientColor1"], $background["BgGradientColor2"]);
        break;
    case "image":
        Printf("  background-image: url(%s);\r\n", $background["BgImageUrl"]);
        Printf("  background-repeat: %s;\r\n", $background["BgImageRepeat"]);
        break;
}
?>
}

A.edit_button
{ font-weight:bolder;
  background-color:#FF0000;
  border: 1px solid #FF0000;
  border-radius:3px;
  text-decoration:none;
  color:#FFFFFF;
  cursor:pointer;
}


A.edit_button:link  ,  A.edit_button:visited  ,    A.edit_button:active
{ background-color:#FF0000;
  border: 1px solid #FF0000;
  color:#FFFFFF;
  padding-left:3px;
  padding-right:3px;
  margin-left:5px;


}

A.edit_button:hover
 { background-color:#FF0000;
   border: 1px solid #FFFFFF;
   color:#FFFFFF;
 }

 A.edit_button:active
 {color:#FFFFFF;
 }

<?php editPartstart(); ?>
A
{ text-decoration:none;
}

input
{ border: 1px solid #d0d0d0;
  margin-right: 4px;
}



DIV.grapheditor
{ position : absolute;

  top:10%;
  height:80%;
  width:300px;
  background-color:#FFFFFF;
  border:2px solid #FF0000;
  border-top-right-radius: 10px;
  rgba(0, 0, 0, 0.5);
  overflow:hidden;
  z-index:1000;
}


div.settingsWindow
{ position : absolute;
  background-color:#FFFFFF;
  border:2px solid #FF0000;
  border-radius: 10px;
  border-bottom-right-radius: 10px;
  box-shadow: 10px 10px 5px rgba(0, 0, 0, 0.1);
  overflow:hidden;
  z-index:1000;
}

DIV.edit_header
{top :0px;
 left:0px;
 right:0px;
 background-color:#ff0000;
 color:#FFFFFF;
 font-weight:bold;
 padding-top:3px;
 padding-bottom:3px;
 padding-left:10px;
}

DIV.edit-contents
{ position:relative;
  top:10px;
  padding-left:5px;
  padding-right:5px;
}

DIV.edit_footer
{position: absolute;
 bottom:0px;
 left:0px;
 right:0px;
 background-color:#ff0000;
 text-align:right;
 color:#FFFFFF;
 font-weight:bold;
 padding-top:3px;
 padding-bottom:3px;
 font-weight:bolder;
 padding-right:10px;
}



DIV.propertylist
{ position:absolute;

  top:25px;
  bottom:25px;
  left:0px;
  right:0px;

  overflow-y:scroll;
  overflow-x:hidden;
  border:0px solid blue;
}

div.expandecolapse
{ display:inline;
  margin-left: 3px;
  border: 1px solid black;
  padding: 1px 4px 0px 4px;
  cursor:pointer;
  background-color:#FFd0d0;


}

DIV.sideswitcher
{ position:absolute;

  top:0px;
  color:white;
  font-weight:bolder;
}



DIV.header
{ border:0px solid black;
  background-color:#FF8080;
  padding-bottom:4px;
  padding-top:4px;

}

DIV.ErrorDiv , DIV.NotifyDiv
{ text-align:right;
  position:absolute;
  padding-right:5px;
  padding-bottom:5px;
  padding-top:5px;
  padding-left:5px;
  margin-top:5px;
  margin-bottom:5px;
  border-radius:5px;
  right:5px;
  bottom:5px;
}

DIV.ErrorDiv
{
  border:2px solid red;
  background-color:#FFE0E0;

}

DIV.NotifyDiv
{
  border:2px solid green;
  background-color:#E0FFE0;
  opacity:1.0;
}

A.button
{ font-size:smaller;
  border:1px solid #800000;
  border-radius:3px;
  padding: 2px 5px 2px 5px;
  background-color:red;
  color:white;
  font-weight:bold;
  margin-right:5px;
}

#MainMenu, #MainMenu ul
{ padding: 0;
  margin: 0;
  margin-right:20px;
  cursor:pointer;
  list-style: none;
  float:left;
  font-size:24px;
  font-weight: bold;
  color:white;
  background-color:#FF0000;
  box-shadow: 10px 10px 5px rgba(0, 0, 0, 0.1);
 }

#MainMenu
{ border:1px solid black;
  border-radius:5px;
  padding-right:150px;
  z-index:100000;
}

#MainMenu li
{ float: left;
  position: relative;

}

.mainMenuItem
{ border: 0px solid black;
  padding-bottom:10px;  /* fixes an IE bug */
}

.mainMenuItem a
{ margin-left: 6px;
  padding-right: 8px;
  text-decoration: none;
}

.subMenu
{ display: none;
  position: absolute;
  top: 2em;
  left: 0;
  background: rgba(255, 255, 255, 0.8);
  border-left: 1px solid black;
  border-right: 1px solid black;
  border-bottom: 1px solid black;
  border-bottom-right-radius:5px;
  border-bottom-left-radius:5px;
  margin-bottom:10px;
  z-index:1000;

}

.subMenu li
{ width: 100%;
  white-space:nowrap;
  line-height:24px;

  padding-left:8px;
}

.subMenu li a
{ text-decoration: none;
}

#MainMenu  A:link
 { color :  #FFFFFF; }

#MainMenu  A:visited
 { color :  #FFFFFF; }


#MainMenu  A:hover
 { color : #800000; }

#MainMenu A:active
 { color : #FFFFFF;}

#MainMenu li>ul
 {  top: auto;
   left: auto;
}

#MainMenu li:hover ul, li.over ul
{
  display: block;
}

TABLE.rawDataTable
{
  white-space:nowrap;
  margin-left:0px;
  margin-right:0px;
}

TABLE.rawDataTable TH
{ background-color: #e0e0e0;
  border: 1px solid #404040;
  padding-left:5px;
  padding-right:5px;
}




TD.time
{ border-left: 1px solid #404040;
  border-right: 1px solid #404040;
  padding-right:5px;
  padding-left:5px;
}


TD.mi
{ border-left: 1px solid #404040;
  text-align:right;
  padding-right:3px;
  padding-left:5px;

}
TD.cu
{ background-color:#f0f0f0;
  text-align:right;
  padding-right:3px;
  padding-left:3px;
}
TD.ma
{ border-right: 1px solid black;
  text-align:right;
  padding-left:3px;
   padding-right:5px;
}




A.calendarBtn
{   background-color: #f0f0f0;
    border-color: gray;
    border-radius: 2px;
    border-style: solid;
    border-width: 1px;
    color: #404040;
    cursor: pointer;
    display: inline-block;
    font-family: sans-serif;
    font-size: 12px;
    height: 12px;
	text-align:center;
    line-height: 12px;
    margin-left: 2px;
    margin-right: 2px;
	margin-top: 2px;
    margin-top: 2px;
    padding-top:2px;
    padding-bottom:2px;
	padding-lef:4px;
    padding-right:4px;
    text-decoration: none;
    width: 25px;
}

A.calendarCtl
{   background-color: white;
    border-color: gray;
    border-radius: 2px;
    border-style: solid;
    border-width: 1px;
    color: black;
    cursor: pointer;
    display: inline-block;
    font-family: sans-serif;
    font-size: 12px;
    height: 12px;
	text-align:center;
    line-height: 12px;
    margin-left: 2px;
    margin-right: 2px;
	margin-top: 2px;
    margin-top: 2px;
    padding-top:2px;
    padding-bottom:2px;
	padding-lef:4px;
    padding-right:4px;
    text-decoration: none;
    width: 50px;
}

DIV.fileDetailsContents
{  border:1px solid#404040;
   border-radius: 4px;
   border-style: solid;
   border-width: 1px;
   margin-top:5px;
   margin-left:5px;
   margin-right:3px;
   margin-bottom:5px;
   background-color:#f8f8f8;
}

<?php editPartEnd(); ?>

</style>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<script src="//code.highcharts.com/stock/highstock.js"></script>
<script src="//code.highcharts.com/modules/exporting.js"></script>
<script src="//code.highcharts.com/modules/offline-exporting.js"></script>
<script>
var GRAPHELMT =0;
var DECOELMT =1;
var feed='<?php
print($FEED);
?>';
var widgets=Array();
var now = new Date();
var lastestValues= Array();
var refreshEnabled = true;
var decoRefreshTimer=null;
var appReady =false;


Highcharts.setOptions({global: {timezoneOffset: now.getTimezoneOffset() }});

function self()
{ var url = window.location.href;
  var p= url.indexOf('?');
  if (p>0) url=url.substring(0,p);
  return url;

}

function hideNotice()
{  document.getElementById('notice1').style.display='none';
   document.getElementById('notice2').style.display='none';
   if (typeof startApp === "function") startApp();


}

function NewWidgetName(str_prefix)
{ var n=1;
  var id=str_prefix+n;
  var ok;
  do
   { var ok=true;
     for (var i=0;i<widgets.length;i++)
 	 {  if (widgets[i].id==id)
		{ ok =false;
	      n++;
		  id=str_prefix+n;

		}
	}
   } while(!ok)
   return id;
 }

<?php
function printDefaultObjectValue($EDITABLE_VALUES)
{
    $indent = "		";
    for ($i = 0; $i < sizeof($EDITABLE_VALUES); $i++)
        if ($EDITABLE_VALUES[$i]['appliesTo']) {
            $suffix = is_null($EDITABLE_VALUES[$i]['index']) ? '' : '_' . $EDITABLE_VALUES[$i]['index'];
            $prefix = $EDITABLE_VALUES[$i]['prefix'];
            if ($prefix != '')
                $prefix .= '_';
            $defaultvalue = $EDITABLE_VALUES[$i]['defaultValue'];
            if ($EDITABLE_VALUES[$i]['name'] == 'zIndex')
                $defaultvalue = 'maxZindex';
            if (is_null($defaultvalue))
                Printf("$indent\"%s%s%s\":null,\n", $prefix, $EDITABLE_VALUES[$i]['name'], $suffix);
            else
                switch ($EDITABLE_VALUES[$i]['type']) {
                    case TYPE_FLOAT:
                    case TYPE_INT:
                    case TYPE_PERCENT:
                    case TYPE_LEFTRIGHT:
                    case TYPE_YAXIS:
                    case TYPE_PX:
                        Printf("$indent\"%s%s%s\":%s,\n", $prefix, $EDITABLE_VALUES[$i]['name'], $suffix, $defaultvalue);
                        break;
                    case TYPE_FONTSIZE:
                        Printf("$indent\"%s%s%s\":%0.1f,\n", $prefix, $EDITABLE_VALUES[$i]['name'], $suffix, $defaultvalue);
                        break;
                    case TYPE_COLOR:
                    case TYPE_HALIGN:
                    case TYPE_TALIGN:
                    case TYPE_VALIGN:
                    case TYPE_LAYOUT:
                    case TYPE_PRECISION:
                    case TYPE_LONGSTRING:
                    case TYPE_STRING:
                        Printf("$indent\"%s%s%s\":\"%s\",\n", $prefix, $EDITABLE_VALUES[$i]['name'], $suffix, $defaultvalue);
                        break;
                    case TYPE_DATASRC:
                        Printf("$indent\"%s%s%s\":\"%s\",\n", $prefix, $EDITABLE_VALUES[$i]['name'], $suffix, $defaultvalue);
                        break;
                    case TYPE_BOOL:
                        Printf("$indent\"%s%s%s\":%s,\n", $prefix, $EDITABLE_VALUES[$i]['name'], $suffix, $defaultvalue ? 'true' : 'false');
                        break;
                }
        }
}
?>

function DefaultWidget(int_type,int_index)
{ var maxZindex = 0;
  for (i=0;i<widgets.length;i++)
   if (widgets[i].zIndex>maxZindex) maxZindex=widgets[i].zIndex+1;

  switch (int_type)
  { case GRAPHELMT:
	  return {
		"type" : int_type,
		"id"   : NewWidgetName("Graph"),
		"_dataSrcHasChanged" : false,
<?php
printDefaultObjectValue($GRAPH_EDITABLE_VALUES);
?>
	   "_data":  new Array(<?php
for ($i = 0; $i < MAX_DATASRC_PER_GRAPH; $i++)
    printf("%s{'Tmin':null,'Tmax':null,'minBuffer':null,'maxBuffer':null,'curBuffer':null }", $i > 0 ? ',' : '');
?>),
	    "_originalData" : new Array(),
		"_refreshTimer":null
		 };
    case DECOELMT:
      return {
		"type" : int_type,
		"id"   : NewWidgetName("Deco"),
		"_dataSrcHasChanged" : false,
<?php printDefaultObjectValue($DECO_EDITABLE_VALUES);?>
        "_originalData" : new Array(),
	    };
  }
  throw "invalid int_type";
}
<?php editPartStart(); ?>
function backupWidgetData(widgetData)
  { for(var key in widgetData)
	  if (key.charAt(0)!='_')  widgetData._originalData[key] = widgetData[key];
  }

function restoreWidgetData(widgetData)
  { for(var key in widgetData._originalData)
	  widgetData[key] = widgetData._originalData[key];
  }


function htmlEncode( html ) {
	var res = '';
	for (i=0;i<html.length;i++)
	 { var c = html.charCodeAt(i);
       if (c>127) res=res+"&#"+c+";"
	   else if (c==34) res=res+"&#34;"
	   else if (c>31)	res=res+ html.charAt(i);
	   else if (c==10)	res=res+ '<bR>';

	 }
    return res;
};

<?php editPartEnd(); ?>

function htmlDecode( st ) {
	if (st==null) return "";
	while (st.indexOf('<bR>')>=0)
	{st=st.replace("<bR>",String.fromCharCode(10));
	}

	var  start =0;
	var  s =-1;
	do
	 { var s =  st.substring(start).indexOf('&#');
	   if (s>=0)
	   { s= s+start;

         var e = st.substring(s+2,s+7).indexOf(';');
	     if (e>=0)
		 { start=e+1;
		   var code =  st.substring(s+2, s+2+e);
		   if (code>0)
		   { st = st.substring(0,s)+String.fromCharCode(code)+st.substring(e+s+3);
	       }
		 } else s=-1;
	   }
     }
	while (s>=0)

    return st;
};


<?php editPartStart(); ?>
function getConfigData()
{ var iniData='';
  for (var i=0;i<widgets.length;i++)
  { iniData+='[Widget'+i+']\n';
    for (var key in widgets[i])
      if (key.charAt(0)!='_')
	  { if (widgets[i][key]==null) iniData += key+'=null\n';
         else iniData += key+'="'+htmlEncode(widgets[i][key].toString())+'"\n';

        //if (key=='innerHTML') iniData += key+'="'+htmlEncode(widgets[i][key])+'"\n';
	    //   else iniData += key+'="'+widgets[i][key]+'"\n';
	  }

  }
  var value = getBgType();
  iniData +='[background]\n'
          +'BgImgType="'+value+'"\n'
          +'BgSolidColor="'+document.getElementById('BgSolidColor').value+'"\n'
          +'BgGradientColor1="'+document.getElementById('BgGradientColor1').value+'"\n'
          +'BgGradientColor2="'+document.getElementById('BgGradientColor2').value+'"\n'
          +'BgGradientAngle="'+document.getElementById('BgGradientAngle').value+'"\n'
          +'BgImageUrl="'+document.getElementById('BgImageUrl').value+'"\n'
	      +'BgImageRepeat="'+document.getElementById('BgImageRepeat').value+'"\n';
  iniData +='[callBackFreq]\n'
          +'freqEnabled="'+document.getElementById('freqEnabled').checked+'"\n'
          +'freqMin="'+parseInt(document.getElementById('freqMin').value)+'"\n'
          +'freqMax="'+parseInt(document.getElementById('freqMax').value)+'"\n'
          +'freqWait="'+parseInt(document.getElementById('freqWait').value)+'"\n';
  iniData +='[wakeUpSettings]\n'
          +'wakeUpEnabled="'+document.getElementById('wakeUpEnabled').checked+'"\n'
          +'wakeUpAutoSleep="'+document.getElementById('wakeUpAutoSleep').checked+'"\n'
          +'wakeUpSleepAfter="'+parseInt(document.getElementById('wakeUpSleepAfter').value)+'"\n'
          +'wakeUpDaysWeek="'+parseInt(document.getElementById('wakeUpDaysWeek').value)+'"\n'
          +'wakeUpDaysMonth="'+parseInt(document.getElementById('wakeUpDaysMonth').value)+'"\n'
	      +'wakeUpMonths="'+parseInt(document.getElementById('wakeUpMonths').value)+'"\n'
	      +'wakeUpHours="'+parseInt(document.getElementById('wakeUpHours').value)+'"\n'
	      +'wakeUpMinutesA="'+parseInt(document.getElementById('wakeUpMinutesA').value)+'"\n'
	      +'wakeUpMinutesB="'+parseInt(document.getElementById('wakeUpMinutesB').value)+'"\n';
  iniData +='[cleanUpSettings]\n'
          +'cleanUpEnabled="'+document.getElementById('cleanUpEnabled').checked+'"\n'
	      +'dataTrimSize="'+parseInt(document.getElementById('dataTrimSize').value)+'"\n'
          +'dataMaxSize="'+parseInt(document.getElementById('dataMaxSize').value)+'"\n';


  return iniData;
}


<?php editPartEnd(); ?>

<?php
function printObjectInitCode($graphObjectID)
  {
    global $GRAPH_EDITABLE_VALUES;
    $indent = '               ';
    $first  = '';
    for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
      if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & $graphObjectID)
       {
        $name   = $GRAPH_EDITABLE_VALUES[$i]['name'];
        $prefix = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
        if ($prefix != '') $prefix .= '_';
        switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
            case TYPE_PX:
                Printf("$first\n$indent%s: widgets[index].%s%s+'px'", $name, $prefix, $name);
                break;
            case TYPE_FONTSIZE:
                Printf("$first\n$indent%s: widgets[index].%s%s+'em'", $name, $prefix, $name);
                break;
            case TYPE_PERCENT:
                Printf("$first\n$indent%s: widgets[index].%s%s+'%%'", $name, $prefix, $name);
                break;
            case TYPE_STRING:
            case TYPE_LONGSTRING:
                Printf("$first\n$indent%s: htmlDecode(widgets[index].%s%s)", $name, $prefix, $name);
                break;
            default:
                Printf("$first\n$indent%s: widgets[index].%s%s", $name, $prefix, $name);
                break;
        }
      $first = ',';
    }
  }
?>

function newGraph(index)
  {
     return  new Highcharts.StockChart({
        chart :     { type: 'line', zoomType: 'x',
		animation :false,
		spacingBottom: 5,
        spacingTop: 5,
        spacingLeft: 5,
        spacingRight: 5,
		marginBottom: 20,
        marginTop: 25,
		borderColor: '#EBBA95',
        // Explicitly tell the width and height of a chart
        width: null,
        height: null,
		renderTo: widgets[index].id,
        <?php printObjectInitCode(GRAPH_OBJ); ?>

		},

	    title: {<?php printObjectInitCode(TITLE_OBJ);?>,
                style:{<?php printObjectInitCode(TITLESTYLE_OBJ);?>}
        },
        legend: {<?php printObjectInitCode(LEGEND_OBJ);?>},
        navigator: {baseSeries: 1,  <?php printObjectInitCode(NAVIGATOR_OBJ);?>},
        scrollbar: {liveRedraw: true,    <?php printObjectInitCode(SCROLLBAR_OBJ);?>},


		credits: { enabled: false},
        plotOptions:{series: { animation: false}},
		xAxis : { minRange: 60 * 1000,
		          // min : <?php print((time() - 3 * 86400) * 1000);?>,
                  // max :	<?php print(time() * 1000); ?>,
		          type: 'datetime',
                  ordinal: false,
                  <?php printObjectInitCode(XAXIS_OBJ);?>,
                  labels:{style: { <?php printObjectInitCode(XAXISSTYLE_OBJ);?>}}



                  },

        rangeSelector: {
            buttons: [{ count:1, type:'hour', text: ' hour ' },
                      { count:1, type:'day', text: ' day ' },
                      { count:7, type:'day', text: ' week ' },
                      { count:1, type:'month', text: ' month ' },
                      { type:'all', text: 'All' }],
            selected: 2,
             <?php printObjectInitCode(RANGESEL_OBJ);?>
         },


        navigation: {
            buttonOptions: {
               <?php printObjectInitCode(EXPORTBTN_OBJ);?>
        } },


		yAxis: [


<?php
$indent = "           ";
for ($j = 0; $j < MAX_YAXIS_PER_GRAPH; $j++) {
   if ($j > 0)
        print(",\n");

   if ($j==0) //  bands  on the 1srt y axis onbly.
    { Printf("{$indent}{plotBands: [\n");
      for ($k=0;$k<MAX_YBAND_PER_GRAPH;$k++)
       {  $firstInList = true;
          if ($k>0) print(",");
          print("{$indent}{");
          for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
          if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & YBAND_OBJ)
             if ($GRAPH_EDITABLE_VALUES[$i]['index'] ==$k)
               {  if (!$firstInList)  print(",\n{$indent}  ");
                  $firstInList = false;
                  $name        = $GRAPH_EDITABLE_VALUES[$i]['name'];
                  $prefix      = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
                  if ($prefix != '') $prefix .= '_';
                  Printf("%s: widgets[index].%s%s_%d", $name, $prefix, $name, $k);

               }
          print("\n{$indent}}\n");
       }
       print("],\n");
    } else print('{');



    Printf("{$indent}title:{");
    $firstInList = true;
    for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
    { if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & YAXISTITLE_OBJ)
          if ($GRAPH_EDITABLE_VALUES[$i]['index'] == $j) {
                if (!$firstInList)  print(',');
                $firstInList = false;
                $name        = $GRAPH_EDITABLE_VALUES[$i]['name'];
                $prefix      = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
                if ($prefix != '')
                    $prefix .= '_';
                switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
                    case TYPE_PX:
                        Printf("%s: widgets[index].%s%s_%d+'px'", $name, $prefix, $name, $j);
                        break;
                    case TYPE_FONTSIZE:
                        Printf("%s: widgets[index].%s%s_%d+'em'", $name, $prefix, $name, $j);
                        break;
                    case TYPE_PERCENT:
                        Printf("%s: widgets[index].%s%s_%d+'%%'", $name, $prefix, $name, $j);
                        break;
                    case TYPE_STRING:
                    case TYPE_LONGSTRING:
                        Printf("%s: htmlDecode(widgets[index].%s%s_%d)", $name, $prefix, $name, $j);
                        break;
                    default:
                        Printf("%s: widgets[index].%s%s_%d", $name, $prefix, $name, $j);
                        break;
                }
            }


    }
    print(",\n   style:{");
    printObjectInitCode(YAXISSTYLE_OBJ);
    print("}");

    print("},\n   labels:{style:{");
    printObjectInitCode(YAXISSTYLE_OBJ);
    print("}}");


    for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & YAXIS_OBJ)
            if ($GRAPH_EDITABLE_VALUES[$i]['index'] == $j) {
                $name   = $GRAPH_EDITABLE_VALUES[$i]['name'];
                $prefix = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
                if ($prefix != '')
                    $prefix .= '_';
                switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
                    case TYPE_PX:
                        Printf(",\n{$indent} %s: widgets[index].%s%s_%d+'px'", $name, $prefix, $name, $j);
                        break;
                    case TYPE_FONTSIZE:
                        Printf(",\n{$indent} %s: widgets[index].%s%s_%d+'em'", $name, $prefix, $name, $j);
                        break;
                    case TYPE_PERCENT:
                        Printf(",\n{$indent} %s: widgets[index].%s%s_%d+'%%'", $name, $prefix, $name, $j);
                        break;
                    case TYPE_LEFTRIGHT:
                        Printf(",\n{$indent} %s: parseInt(widgets[index].%s%s_%d)>0", $name, $prefix, $name, $j);
                        break;
                    case TYPE_STRING:
                    case TYPE_LONGSTRING:
                        Printf(",\n{$indent} %s: htmlDecode(widgets[index].%s%s_%d)", $name, $prefix, $name, $j);
                        break;
                    default:
                        Printf(",\n{$indent} %s: widgets[index].%s%s_%d", $name, $prefix, $name, $j);
                        break;
                }
            }
    Print('}');
}
Print("{$indent}\n],\n");
?>


        series : [
<?php
$showmax = 'false';
$showmin = 'false';
$indent  = '                  ';
for ($j = 0; $j < MAX_DATASRC_PER_GRAPH; $j++) {
    $serieName = 'Serie $j';
    for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
        if (array_key_exists('index', $GRAPH_EDITABLE_VALUES[$i]))
            if ($GRAPH_EDITABLE_VALUES[$i]['index'] == $j) {
                $prefix = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
                if ($prefix != '')
                    $prefix .= '_';
                if (($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & SERIE_OBJ) && ($GRAPH_EDITABLE_VALUES[$i]['name'] == 'name'))
                    $serieName = "widgets[index].$prefix" . $GRAPH_EDITABLE_VALUES[$i]['name'] . "_$j";
                if ($GRAPH_EDITABLE_VALUES[$i]['name'] == 'showmax')
                    $showmax = sPrintf("(widgets[index].%sshowmax_%d && (widgets[index].%sdatasource_%s!=null)) ", $prefix, $j, $prefix, $j);
                if ($GRAPH_EDITABLE_VALUES[$i]['name'] == 'showmin') {
                    $showmin = sPrintf("(widgets[index].%sshowmin_%d && (widgets[index].%sdatasource_%s!=null)) ", $prefix, $j, $prefix, $j);
                }
            }
    Printf("$indent{ name: %s+' (max)', visible: %s, data : new Array(),showInLegend :false, tooltip:{valueDecimals:2}, color:'#E0E0E0',yAxis:parseInt(widgets[index].data_yAxis_%s)},\n", $serieName, $showmax, $j);
    Printf("$indent{ name: %s+' (min)', visible: %s, data : new Array(),showInLegend :false, tooltip:{valueDecimals:2}, color:'#E0E0E0',yAxis:parseInt(widgets[index].data_yAxis_%s)},\n", $serieName, $showmin, $j);
    Printf("$indent{ data : new Array(), yAxis:parseInt(widgets[index].data_yAxis_%s),tooltip:{valueDecimals:2} ", $j);
    for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & SERIE_OBJ)
            if (($GRAPH_EDITABLE_VALUES[$i]['index'] == $j) && ($GRAPH_EDITABLE_VALUES[$i]['name'] != 'yAxis') && ($GRAPH_EDITABLE_VALUES[$i]['name'] != 'showmin') && ($GRAPH_EDITABLE_VALUES[$i]['name'] != 'showmax')) {
                $name   = $GRAPH_EDITABLE_VALUES[$i]['name'];
                $prefix = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
                if ($prefix != '')
                    $prefix .= '_';
                Printf(", %s:widgets[index].%s%s_%d", $name, $prefix, $name, $j);
            }
    Printf(",showInLegend:(widgets[index].data_datasource_$j!='')");
    Print('}');
    if ($j < MAX_DATASRC_PER_GRAPH - 1)
        print(",\n");
}
?>        ]
    });

  }

function findwidgetIndex(id)
{  var index = -1;

   for (var i=0;i<widgets.length;i++)
	 if (widgets[i].id==id) index=i;
  return index;
}

function startDecoRefresh()
 { decoRefreshTimer=null;
   document.getElementById('decoRefreshFrame').src=self()+'?cmd=getlastestdata&feed='+feed;

 }

function updateDecoValue(data)
{ for (var i=0;i<data.length;i++)
	{  updateLastValue(data[i][0],data[i][1], data[i][2],data[i][3]);
	}

  if (refreshEnabled) initDecoRefreshTimer();
}

function  initDecoRefreshTimer()
 { if  (decoRefreshTimer==null)
     decoRefreshTimer=setTimeout(function() {startDecoRefresh(); }, <?php print (PHPDECOREFRESHDELAY);?>);
 }

<?php editPartStart(); ?>
function deleteWidget(id)
  {
	var index=  findwidgetIndex(id);
	if (index<0) return;

    if (!confirm('Do you really want to delete this widget?')) return;

    var div =  document.getElementById(widgets[index].id);
    div.parentElement.removeChild(div);
	var div =  document.getElementById(widgets[index].id+"ctrl");
    div.parentElement.removeChild(div);

    widgets.splice(index,1);
    saveAll();
    refreshEditSubMenu();
  }

function stopAllRefreshs()
 { refreshEnabled = false;
   stopDecoRefreshTimer();
   for (var i=0;i<widgets.length;i++)
	 if  (widgets[i].type==GRAPHELMT)
      stopGraphRefreshTimer(widgets[i]);
 }






function restoreAllRefreshs()
 {  refreshEnabled = true;
    initDecoRefreshTimer();
    for (var i=0;i<widgets.length;i++)
	 if  (widgets[i].type==GRAPHELMT)
        initRefreshGraphData( widgets[i],-1);
 }

function stopDecoRefreshTimer()
{  if (decoRefreshTimer)   clearTimeout(decoRefreshTimer);
   decoRefreshTimer =null;
}


<?php editPartEnd(); ?>


function stopGraphRefreshTimer(widget)
{ if (widgets['_refreshTimer'])
	{ clearTimeout(widgets['_refreshTimer']);
      widget['_refreshTimer']=null;
	}

}

function CreateWidget(index,widgetType)
{ var newdiv = document.createElement('div');
  newdiv.setAttribute('id',  widgets[index].id);
  newdiv.style.position= "absolute";
  newdiv.style.marginLeft= "0px";
  newdiv.style.marginRight= "0px";
  newdiv.style.marginTop= "0px";
  newdiv.style.marginBottom= "0px";
  newdiv.style.paddingLeft= "0px";
  newdiv.style.paddinRight= "0px";
  newdiv.style.paddingTop= "0px";
  newdiv.style.paddingBottom= "0px";
  newdiv.style.overflow = "hidden";
  newdiv.style.border= "1px solid #f0f0f0";
  newdiv.style.Zindex= widgets[index].zindex;
  document.body.appendChild(newdiv);
  applyDivProperties(newdiv,widgets[index]);
  switch (widgetType)
  {  case  GRAPHELMT:
       widgets[index]["_chart"] = newGraph(index);
	   break;
     case  DECOELMT:
	   newdiv.InnerHTML='Hello world!';
	   break;
  }

  var ctrldiv = document.createElement('div');
  ctrldiv.setAttribute('id',  widgets[index].id+'ctrl');
  ctrldiv.style.position= "absolute";
  ctrldiv.style.paddingLeft= "10px";
  ctrldiv.style.paddingTop= "10px";
  ctrldiv.style.border= "0px solid #f0f0f0";
  document.body.appendChild(ctrldiv);
  ctrldiv.innerHTML =
   <?php editPartStart(); ?>
          '<a class="button" name="UICtrl" href="javascript:editWidget(\''+widgets[index].id+'\')">Edit</A>'+
          '<a class="button" name="UICtrl" href="javascript:deleteWidget(\''+widgets[index].id+'\')">Delete</A>'+
   <?php editPartEnd(); ?>
          '<iframe  id="'+widgets[index].id+'frame"style="display:none" ></iframe>' ;
  applyCtrlDivProperties(newdiv,ctrldiv);
  if (widgetType==GRAPHELMT)
  { stopGraphRefreshTimer(widgets[index]);
    if(refreshEnabled) widgets[index]['_refreshTimer']=setTimeout(function() {initRefreshGraphData( widgets[index],-1); },100);
  }
}

function updateDone(id)
 { for (var i=0;i<widgets.length;i++)
    if (widgets[i].id==id)
	 { if (widgets[i]['_needUpdate'])
		 { for (j=0;j<<?php
print(MAX_DATASRC_PER_GRAPH);
?>;j++)
	        { widgets[i]['_chart'].series[3*j].setData(widgets[i]["_data"][j].max,false,false,false);
              widgets[i]['_chart'].series[3*j+1].setData(widgets[i]["_data"][j].min,false,false,false);
              widgets[i]['_chart'].series[3*j+2].setData(widgets[i]["_data"][j].cur,false,false,false);
 	        }
	       widgets[i]['_chart'].redraw();
	     }
	  stopGraphRefreshTimer(widgets[i]);
	  if(refreshEnabled) setTimeout(function() {initRefreshGraphData( widgets[i],-1); },2500);
	  return;
	 }
 }


function updateLastValue(srcname,timestamp, value, unit)
 {if (!lastestValues[srcname])
    { lastestValues[srcname]= {"timestamp":0,"value":0.0,"unit":""};
    }
   if (lastestValues[srcname].timestamp<timestamp)
    { lastestValues[srcname].timestamp=timestamp;
      lastestValues[srcname].value=value;
      lastestValues[srcname].unit=unit;
	  refreshDecoValues(srcname);
    }
 }



function updateGraph(id,serieIndex,start,minData,maxData,curData,precision,unit)
 { var chart=null;
   var  data=null;
   var shift=false;
   firstRefresh=false;
   var widgetsIndex=-1;
   for (var i=0;i<widgets.length;i++)
	if (widgets[i].id==id)
	  { chart= widgets[i]["_chart"];
        data = widgets[i]["_data"][serieIndex];
		widgetsIndex=i;
		widgets[i]['_needUpdate']=true;
	  }
   var Tmin = curData[0][0]+start;
   var Tmax = curData[curData.length-1][0]+start;
   for (var i=0;i<curData.length;i++)
   { curData[i][0] = (curData[i][0]+start)*1000;
     maxData[i][0] = (maxData[i][0]+start)*1000;
     minData[i][0] = (minData[i][0]+start)*1000;
   }
   var i =  serieIndex*3;
   if (data.Tmin==null)
     { data.Tmin = Tmin;
       data.Tmax = Tmax;
	   data.min = minData;
	   data.max = maxData;
	   data.cur = curData;
	 }
   else
    {  if (data.Tmin>Tmin)
		{data.Tmin=Tmin;
         data.min = minData.concat(data.min);
	     data.max = maxData.concat(data.max);
	     data.cur = curData.concat(data.cur);
	    }
		else
	   if (data.Tmax<Tmax)
	   { data.Tmax=Tmax;
 	     data.min  = data.min.concat(minData);
	     data.max = data.max.concat(maxData);
	     data.cur = data.cur.concat(curData);
	   }
	}
   var srcname = widgets[widgetsIndex]['data_datasource_'+serieIndex];
   updateLastValue(srcname,Tmax, curData[curData.length-1][1],unit);


  if (firstRefresh)
  { chart.series[serieIndex*3].update({"tooltip":{"valueDecimals":precision,"valueSuffix":" "+unit}});
    chart.series[serieIndex*3+1].update({"tooltip":{"valueDecimals":precision,"valueSuffix":" "+unit}});
    chart.series[serieIndex*3+2].update({"tooltip":{"valueDecimals":precision,"valueSuffix":" "+unit}});
  }
 }

function preloadGraphData(widget,serieIndex, newsrc)
 {  var it =  document.getElementById(widget.id+'frame');
	it.src = self()+'?cmd=getdata'+'&feed='+feed+'&id='+widget.id+'&indexes='+serieIndex+'&name='+newsrc+'&time=null,null';
 }

function initRefreshGraphData(widget,serieIndex)
 {
    var it =  document.getElementById(widget.id+'frame');
	var srcnames = '';
	var indexes ='';
	var timestamps ='';
	widget['_needUpdate']=false;
	if (serieIndex<0)
  	  for (var i=0;i<<?php
print(MAX_DATASRC_PER_GRAPH);
?>;i++)
 	    { srcnames=srcnames+(i>0?',':'')+widget['data_datasource_'+i];
	      indexes =indexes +(i>0?',':'')+i;
          var data = widget['_data'][i];
		  timestamps= timestamps  +(i>0?',':'')+data.Tmin+','+data.Tmax;

		}
	else
	{ srcnames= widget['data_datasource_'+serieIndex];
      indexes = serieIndex;
	  var data = widget['_data'][serieIndex];
	  timestamps= timestamps  +(i>0?',':'')+data.Tmin+','+data.Tmax;
	}
    it.src = self()+'?cmd=getdata'+'&feed='+feed+'&id='+widget.id+'&indexes='+indexes+'&name='+srcnames+'&time='+timestamps+'&pouet=0';
 }

function NewGraphWidget(hwdname)
{ var index = widgets.length;
  var max=0;
  widgets[index] = DefaultWidget(GRAPHELMT,index);
  CreateWidget(index,GRAPHELMT);
  refreshEditSubMenu();
}

function NewDecoWidget(hwdname)
{ var index = widgets.length;
  widgets[index] = DefaultWidget(DECOELMT,index);
  CreateWidget(index,DECOELMT);
  refreshEditSubMenu();
}

function refreshDecoValues(srcName)
 { if (lastestValues[srcName])
	 { var spans = document.getElementsByName(srcName);
       for (var i=0;i<spans.length;i++)
	   {
         var precision = spans[i].getAttribute('precision');
         var showUnit  = (parseInt(spans[i].getAttribute('showunit'))==1);
		 var value = lastestValues[srcName].value.toFixed(precision);
		 spans[i].innerHTML= value +(showUnit?' '+lastestValues[srcName].unit:'');
	   }
	 }
 }

function computeTextContents(widgetsdata,innerHTML)
{ var data =innerHTML;
<?php
$indent = '  ';
for ($i = 0; $i < MAX_VARIABLE_PER_DECO; $i++) {
    $index = $i + 1;
    Print("{$indent}var name       = 'UNDEFINED';\n");
    Print("{$indent}var value     = '*UNDEFINED*';\n");
    Print("{$indent}var precision = 1;\n");
    Print("{$indent}var showUnit     = 0;\n");
    print("{$indent}if (widgetsdata['var_datasource_$i'])\n");
    print("{$indent}   { name= widgetsdata['var_datasource_$i']; \n");
    print("{$indent}     value='N/A';\n");
    print("{$indent}     showUnit=(widgetsdata['var_showunit_$i'].toString().toUpperCase()=='TRUE')?1:0;\n");
    print("{$indent}     precision=widgetsdata['var_precision_$i'];\n");
    print("{$indent}     if (lastestValues[name])\n");
    print("{$indent}        { value = lastestValues[name].value.toFixed(precision);\n");
    print("{$indent}          if (showUnit) value=value+' '+lastestValues[name].unit;\n");
    print("{$indent}        }\n");
    print("{$indent}   }\n");
    print("{$indent}data = data.replace(new RegExp(/{\\$$index}/,'g'),'<span name=\"'+name+'\" precision='+precision+' showUnit='+showUnit+'>'+value+'</span>');\n");
}
?>
  return data;
}

<?php
function PrintDivPropertiesApplyCode($EDITABLE_VALUES)
{
    for ($i = 0; $i < sizeof($EDITABLE_VALUES); $i++) {
        if ($EDITABLE_VALUES[$i]['appliesTo'] & (STYLE_OBJ | DIV_OBJ)) {
            $name   = $EDITABLE_VALUES[$i]['name'];
            $prefix = $EDITABLE_VALUES[$i]['prefix'];
            $target = ($EDITABLE_VALUES[$i]['appliesTo'] & STYLE_OBJ) ? "obj_div.style" : "obj_div";
            if ($prefix != '')
                $prefix .= '_';
            if ($name == 'zIndex')
                Printf("       %s.%s=2*widgetsdata.%s%s;\n", $target, $name, $prefix, $name);
            else
                switch ($EDITABLE_VALUES[$i]['type']) {
                    case TYPE_PX:
                        Printf("       %s.%s=widgetsdata.%s%s+'px';\n", $target, $name, $prefix, $name);
                        break;
                    case TYPE_FONTSIZE:
                        Printf("       %s.%s=widgetsdata.%s%s+'em';\n", $target, $name, $prefix, $name);
                        break;
                    case TYPE_PERCENT:
                        Printf("       %s.%s=widgetsdata.%s%s+'%%';\n", $target, $name, $prefix, $name);
                        break;
                    default:
                        Printf("       %s.%s=widgetsdata.%s%s;\n", $target, $name, $prefix, $name);
                        break;
                }
        }
        if ($EDITABLE_VALUES[$i]['appliesTo'] & (DECOTEXT_OBJ))
            if ($EDITABLE_VALUES[$i]['name'] == 'innerHTML') {
                print("       obj_div.innerHTML= computeTextContents(widgetsdata, widgetsdata.innerHTML);\n");
            }
    }
}
?>

function  applyDivProperties(obj_div,widgetsdata)
 { switch (parseInt(widgetsdata['type']))
   { case GRAPHELMT:
<?php
PrintDivPropertiesApplyCode($GRAPH_EDITABLE_VALUES);
?>
      break;
	 case DECOELMT:
<?php
PrintDivPropertiesApplyCode($DECO_EDITABLE_VALUES);
?>
      break;
   }
 }

 function applyCtrlDivProperties(graph_div,ctrl_div)
 {  ctrl_div.style.left=graph_div.style.left;
    ctrl_div.style.top=graph_div.style.top;
	ctrl_div.style.zIndex=parseInt(graph_div.style.zIndex)+1;
 }


function  applyGraphProperties(widget)
 {

  var graphobj = widget["_chart"];
<?php
for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
    if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & GRAPH_OBJ) {
        $name   = $GRAPH_EDITABLE_VALUES[$i]['name'];
        $prefix = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
        if ($prefix != '')
            $prefix .= '_';
        switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
            case TYPE_DATASRC:
                break;
            case TYPE_PX:
                Printf("  graphobj.options.chart.%s = widget.%s%s +'px';\n", $name, $prefix, $name);
                break;
            case TYPE_FONTSIZE:
                Printf("  graphobj.options.chart.%s = widget.%s%s +'em';\n", $name, $prefix, $name);
                break;
            case TYPE_PERCENT:
                Printf("  graphobj.options.chart.%s = widget.%s%s +'%%';\n;", $name, $prefix, $name);
                break;
            case TYPE_STRING:
            case TYPE_LONGSTRING:
                Printf("  graphobj.options.chart.%s =  htmlDecode(widget.%s%s) ;\n", $name, $prefix, $name);
                break;
            default:
                Printf("  graphobj.options.chart.%s = widget.%s%s ;\n", $name, $prefix, $name);
                break;
        }
    }
for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
    if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & TITLE_OBJ) {
        $name   = $GRAPH_EDITABLE_VALUES[$i]['name'];
        $prefix = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
        if ($prefix != '')
            $prefix .= '_';
        switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
            case TYPE_PX:
                Printf("  graphobj.setTitle({%s : widget.%s%s +'px'});\n", $name, $prefix, $name);
                break;
            case TYPE_FONTSIZE:
                Printf("  graphobj.setTitle({%s : widget.%s%s +'em'});\n", $name, $prefix, $name);
                break;
            case TYPE_PERCENT:
                Printf("  graphobj.setTitle({%s : widget.%s%s +'%%'});\n;", $name, $prefix, $name);
                break;
            case TYPE_STRING:
            case TYPE_LONGSTRING:
                Printf("  graphobj.setTitle({%s : htmlDecode(widget.%s%s)});\n;", $name, $prefix, $name);
                break;
            default:
                Printf("  graphobj.setTitle({%s: widget.%s%s}) ;\n", $name, $prefix, $name);
                break;
        }
    }
for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
    if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & SERIE_OBJ) {
        $name = $GRAPH_EDITABLE_VALUES[$i]['name'];
        if (($name != 'showmin') && ($name != 'showmax')) {
            $prefix = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
            if ($prefix != '')
                $prefix .= '_';
            $index = intVal($GRAPH_EDITABLE_VALUES[$i]['index']);
            switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
                case TYPE_DATASRC:
                    break;
                case TYPE_PX:
                    Printf("  graphobj.series[" . ($index * 3 + 1) . "].%s = widget.%s%s_%d +'px';\n", $name, $prefix, $name, $index);
                    break;
                case TYPE_FONTSIZE:
                    Printf("  graphobj.series[" . ($index * 3 + 1) . "].%s = widget.%s%s_%d +'em';\n", $name, $prefix, $name, $index);
                    break;
                case TYPE_PERCENT:
                    Printf("  graphobj.series[" . ($index * 3 + 1) . "].%s = widget.%s%s_%d +'%%';\n", $name, $prefix, $name, $index);
                    break;
                case TYPE_STRING:
                case TYPE_LONGSTRING:
                    Printf("  graphobj.series[" . ($index * 3 + 1) . "].%s = htmlDecode(widget.%s%s_%d);\n", $name, $prefix, $name, $index);
                    break;
                default:
                    Printf("  graphobj.series[" . ($index * 3 + 1) . "].%s = widget.%s%s_%d ;\n", $name, $prefix, $name, $index);
                    break;
            }
        }
    }
printf("  if (widget['_dataSrcHasChanged'])\n   {\n");
for ($i = 0; $i < MAX_DATASRC_PER_GRAPH; $i++) {
    Printf("    graphobj.series[" . ($i * 3) . "].setData(new Array(),true);\n");
    Printf("    graphobj.series[" . ($i * 3 + 1) . "].setData(new Array(),true);\n");
    Printf("    graphobj.series[" . ($i * 3 + 2) . "].setData(new Array(),true);\n");
    Print("    widget['_data'][$i].Tmin=null;\n");
    Print("    widget['_data'][$i].Tmax=null;\n");
    Print("    widget['_data'][$i].maxBuffer=null;\n");
    Print("    widget['_data'][$i].minBuffer=null;\n");
    Print("    widget['_data'][$i].curBuffer=null;\n");
}
?>
    stopGraphRefreshTimer(widget);
    initRefreshGraphData(widget,-1);
	widget['_dataSrcHasChanged'] =false;
   }
  }

<?php editPartStart(); ?>
function changeGraphDataSource(widget,options,serieindex,newsource)
  { var visible =  !((newsource==null) || (newsource==''));
    var graph = widget['_chart'];
	var extremes = graph.xAxis[0].getExtremes();
	var max = extremes.max;
	var min = extremes.min;
	if (visible)
	{ widget["_dataSrcHasChanged"]=true;
	  for (var j=0;j<3;j++)
	    graph.series[serieindex*3+j].setData(new Array(),true);
	}
	options.series[serieindex*3+2].showInLegend = visible;
	options.series[serieindex*3+2].visible = visible;
	options.xAxis[0].min=min;
	options.xAxis[0].max=max;
	if (!visible)
	{ options.series[serieindex*3+0].visible = visible;
      options.series[serieindex*3+1].visible = visible;
	}
    if (visible) return serieindex;
  }


function refreshGraph(str_field,dataindex)
 { var index  = parseInt(document.getElementById('WidgetIndex').value);
   var id =  widgets[index].id;
   var div = document.getElementById(id);
   var ctrlDiv = document.getElementById(id+'ctrl');
   var graph =  widgets[index]._chart;
   var suffix=''
   if (typeof dataindex !== 'undefined') suffix='_'+dataindex;
   var value =  document.getElementById('grapheditor_'+str_field+suffix).value;
   var options = widgets[index]._chart.options;
   var optionschanged= false;
   str_field=str_field+suffix;
   var  mustReloadSerie= -1;

<?php
$indent = "   ";
for ($i = 0; $i < sizeof($GRAPH_EDITABLE_VALUES); $i++)
    if ($GRAPH_EDITABLE_VALUES[$i]['editable']) {
        $name   = $GRAPH_EDITABLE_VALUES[$i]['name'];
        $prefix = $GRAPH_EDITABLE_VALUES[$i]['prefix'];
        if ($prefix != '')
            $prefix .= '_';
        $index = $GRAPH_EDITABLE_VALUES[$i]['index'];
        $value = 'value';
        // printf("/*$prefix $name $index*/\n");
        if ($GRAPH_EDITABLE_VALUES[$i]['nullable'])
            switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
                case TYPE_PX:
                    $value = "(value=='')? null : value+'px'";
                    break;
                case TYPE_FONTSIZE:
                    $value = "(value=='')? null : value+'em'";
                    break;
                case TYPE_PERCENT:
                    $value = "(value=='')? null : value+'%'";
                    break;
                case TYPE_LONGSTRING:
                    $value = "(value=='')? null : htmlEncode(value)";
                    break;
                case TYPE_BOOL:
                    $value = "(value=='')? null : (value=='true')";
                    break;
                case TYPE_FLOAT:
                    $value = "(value=='')? null : parseFloat(value)";
                    break;
                case TYPE_YAXIS:
                case TYPE_INT:
                    $value = "(value=='')? null : parseInt(value)";
                    break;
                default:
                    $value = "(value=='')? null : value";
                    break;
            } else
            switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
                case TYPE_PX:
                    $value = "value+'px'";
                    break;
                case TYPE_FONTSIZE:
                    $value = "value+'em'";
                    break;
                case TYPE_PERCENT:
                    $value = "value+'%'";
                    break;
                case TYPE_LONGSTRING:
                    $value = "htmlEncode(value)";
                    break;
                case TYPE_BOOL:
                    $value = "(value=='true')";
                    break;
                case TYPE_YAXIS:
                case TYPE_INT:
                    $value = "parseInt(value)";
                    break;
                case TYPE_FLOAT:
                    $value = "parseFloat(value)";
                    break;
                default:
                    $value = "value";
                    break;
            }
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & STYLE_OBJ)
            Printf("{$indent}if(str_field=='%s%s') { div.style.%s=%s; }\n", $prefix, $name, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & DIV_OBJ)
            Printf("{$indent}if(str_field=='%s%s') { div.%s=%s; }\n", $prefix, $name, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & LEGEND_OBJ)
            Printf("{$indent}if(str_field=='%s%s') {options.legend.%s=%s; optionschanged=true; }\n", $prefix, $name, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & TITLE_OBJ)
            Printf("{$indent}if(str_field=='%s%s') {options.title.%s=%s;optionschanged=true;};\n", $prefix, $name, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & NAVIGATOR_OBJ)
            Printf("{$indent}if(str_field=='%s%s') {options.navigator.%s=%s;optionschanged=true;}\n", $prefix, $name, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & SCROLLBAR_OBJ)
            Printf("{$indent}if(str_field=='%s%s') {options.scrollbar.%s=%s;optionschanged=true;}\n", $prefix, $name, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & RANGESEL_OBJ)
            Printf("{$indent}if(str_field=='%s%s') {options.rangeSelector.%s=%s;optionschanged=true;}\n", $prefix, $name, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & XAXIS_OBJ)
            Printf("{$indent}if(str_field=='%s%s') { options.xAxis[0].%s=%s;optionschanged=true;}\n", $prefix, $name, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & TITLESTYLE_OBJ)
            Printf("{$indent}if(str_field=='%s%s') { options.title.style.%s=%s;optionschanged=true;}\n", $prefix, $name, $name, $value);
         if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & EXPORTBTN_OBJ)
            Printf("{$indent}if(str_field=='%s%s') { options.navigation.buttonOptions.%s=%s;optionschanged=true;}\n", $prefix, $name, $name, $value);

        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & XAXISSTYLE_OBJ)
            Printf("{$indent}if(str_field=='%s%s') { options.xAxis[0].labels.style.%s=%s;optionschanged=true;}\n", $prefix, $name, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & YAXISSTYLE_OBJ)
        {  Printf("{$indent}if(str_field=='%s%s') {optionschanged=true;",$prefix, $name);
           for ($j=0;$j<MAX_YAXIS_PER_GRAPH;$j++)
            { Printf("options.yAxis[$j].labels.style.%s=%s;", $name, $value);
              Printf("options.yAxis[$j].title.style.%s=%s;", $name, $value);

            }
           print("}\n");
        }
        if (($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & MANUAL_APPLY) && ($name == 'showmin'))
            Printf("{$indent}if(str_field=='%sshowmin_%d') { options.series[%d].visible=%s; optionschanged=true;}\n", $prefix, $index, 3 * $index + 1, $value);
        if (($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & MANUAL_APPLY) && ($name == 'showmax'))
            Printf("{$indent}if(str_field=='%sshowmax_%d') { options.series[%d].visible=%s;optionschanged=true; }\n", $prefix, $index, 3 * $index, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & GRAPH_OBJ)
            switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
                case TYPE_DATASRC:
                    Printf("{$indent}if(str_field=='%s%s_%d')mustReloadSerie= changeGraphDataSource(widgets[index],options,%d,%s);optionschanged=true;\n", $prefix, $name, $index, $index, $value);
                    break;
                default:
                    Printf("{$indent}if(str_field=='%s%s')options.chart.%s=%s;\n", $prefix, $name, $name, $value);
                    break;
            }
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & SERIE_OBJ)
            for ($j = 0; $j < MAX_DATASRC_PER_GRAPH; $j++)
                Printf("{$indent}if(str_field=='%s%s_%d') { options.series[%s].%s=%s;optionschanged=true; }\n", $prefix, $name, $j, 3 * $j + 2, $name, $value);
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & YAXIS_OBJ)
            for ($j = 0; $j < MAX_YAXIS_PER_GRAPH; $j++)
                switch ($GRAPH_EDITABLE_VALUES[$i]['type']) {
                    case TYPE_LEFTRIGHT:
                        Printf("{$indent}if(str_field=='%s%s_%d') { options.yAxis[%s].%s=(parseInt(%s)>0);optionschanged=true;};\n", $prefix, $name, $j, $j, $name, $value);
                        break;
                    default:
                        Printf("{$indent}if(str_field=='%s%s_%d') { options.yAxis[%s].%s=%s;optionschanged=true; };\n", $prefix, $name, $j, $j, $name, $value);
                        break;
                }

        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & YBAND_OBJ)
            for ($j = 0; $j < MAX_YBAND_PER_GRAPH; $j++)
             {  Printf("{$indent}if(str_field=='%s%s_%d') { options.yAxis[0].plotBands[%s].%s=%s;optionschanged=true; };\n", $prefix, $name, $j, $j, $name, $value);
             }



        if ($GRAPH_EDITABLE_VALUES[$i]['name'] == 'showmax') {
            Printf("{$indent}if(str_field=='%s%s_%d') { options.series[%d].visible=%s;optionschanged=true;}\n", $prefix, $name, $j, $index * 3, $value);
        }
        if ($GRAPH_EDITABLE_VALUES[$i]['name'] == 'showmin') {
            Printf("{$indent}if(str_field=='%s%s_%d') { options.series[%d].visible=%s;optionschanged=true;}\n", $prefix, $name, $j, $index * 3 + 1, $value);
        }
        if ($GRAPH_EDITABLE_VALUES[$i]['appliesTo'] & YAXISTITLE_OBJ)
            for ($j = 0; $j < MAX_YAXIS_PER_GRAPH; $j++)
                Printf("{$indent}if(str_field=='%s%s_%d') { options.yAxis[%s].title.%s=%s;optionschanged=true; };\n", $prefix, $name, $j, $j, $name, $value);
    }
?>
   applyCtrlDivProperties(div,ctrlDiv);
   if (optionschanged) {
	   widgets[index]._chart =  new Highcharts.StockChart(options);
   }
   if (mustReloadSerie>=0) preloadGraphData(widgets[index],mustReloadSerie, value);
 }



 function refreshDeco(str_field,dataindex)
 { var index  = parseInt(document.getElementById('WidgetIndex').value);
   var id =  widgets[index].id;
   var div = document.getElementById(id);
   var ctrlDiv = document.getElementById(id+'ctrl');
   var suffix=''
   if (typeof dataindex !== 'undefined') suffix='_'+dataindex;
   var value =  document.getElementById('decoeditor_'+str_field+suffix).value;
   str_field=str_field+suffix;
   <?php
$indent = "   ";
for ($i = 0; $i < sizeof($DECO_EDITABLE_VALUES); $i++)
    if ($DECO_EDITABLE_VALUES[$i]['editable']) {
        $name   = $DECO_EDITABLE_VALUES[$i]['name'];
        $prefix = $DECO_EDITABLE_VALUES[$i]['prefix'];
        if ($prefix != '')
            $prefix .= '_';
        $suffix = '';
        if (!is_null($DECO_EDITABLE_VALUES[$i]['index']))
            $suffix = '_' . $DECO_EDITABLE_VALUES[$i]['index'];
        $value = 'value';
        if ($DECO_EDITABLE_VALUES[$i]['nullable'])
            switch ($DECO_EDITABLE_VALUES[$i]['type']) {
                case TYPE_PX:
                    $value = "(value=='')? null : value+'px'";
                    break;
                case TYPE_FONTSIZE:
                    $value = "(value=='')? null : value+'em'";
                    break;
                case TYPE_PERCENT:
                    $value = "(value=='')? null : value+'%'";
                    break;
                case TYPE_LONGSTRING:
                    $value = "(value=='')? null : htmlEncode(value)";
                    break;
                case TYPE_BOOL:
                    $value = "(value=='')? null : (value=='true')";
                    break;
                default:
                    $value = "(value=='')? null : value";
                    break;
            } else
            switch ($DECO_EDITABLE_VALUES[$i]['type']) {
                case TYPE_PX:
                    $value = "value+'px'";
                    break;
                case TYPE_FONTSIZE:
                    $value = "value+'em'";
                    break;
                case TYPE_PERCENT:
                    $value = "value+'%'";
                    break;
                case TYPE_LONGSTRING:
                    $value = "htmlEncode(value)";
                    break;
                case TYPE_BOOL:
                    $value = "(value=='true')";
                    break;
                default:
                    $value = "value";
                    break;
            }
        if ($DECO_EDITABLE_VALUES[$i]['appliesTo'] & STYLE_OBJ)
            Printf("{$indent}if(str_field=='%s%s') { div.style.%s=%s; }\n", $prefix, $name, $name, $value);
        if ($DECO_EDITABLE_VALUES[$i]['appliesTo'] & DIV_OBJ)
            Printf("{$indent}if(str_field=='%s%s') { div.%s=%s; }\n", $prefix, $name, $name, $value);
        if ($DECO_EDITABLE_VALUES[$i]['appliesTo'] & DECOTEXT_OBJ) {
            Printf("{$indent}\nif(str_field=='%s%s%s') { widgets[index]['%s%s%s']=%s;\n", $prefix, $name, $suffix, $prefix, $name, $suffix, $value);
            Printf("\ndiv.innerHTML =  htmlEncode(computeTextContents(widgets[index],widgets[index].innerHTML));}\n");
        }
    }
?>
 applyCtrlDivProperties(div,ctrlDiv);
 }

function cancelGraphEdit()
 {var index  = parseInt(document.getElementById('WidgetIndex').value);
  var div = document.getElementById(widgets[index].id);
  restoreWidgetData(widgets[index]);
  applyGraphProperties(widgets[index]);
  applyDivProperties(div,widgets[index]);
  document.getElementById('grapheditor').style.display='none';
  showUICtrls(true);
  restoreAllRefreshs();
 }

function cancelDecoEdit()
 {var index  = parseInt(document.getElementById('WidgetIndex').value);
  var div = document.getElementById(widgets[index].id);
  restoreWidgetData(widgets[index]);
  applyDivProperties(div,widgets[index]);
  document.getElementById('decoeditor').style.display='none';
  showUICtrls(true);
  restoreAllRefreshs();
 }

function saveAll()
{   document.getElementById('serviceFrame').src=self()+'?cmd=jssave&feed='+feed;
}

var previousBgSettings = Array();
function openBgSettings()
 { stopAllRefreshs();
   showUICtrls(false);
   previousBgSettings['BgImgType'] =  getBgType();
   <?php
foreach ($background as $key => $value)
    if ($key != 'BgImgType')
        printf("previousBgSettings['%s'] =  document.getElementById('%s').value\n;", $key, $key);
?>
   document.getElementById('bgSettingsWindow').style.display='';
 }


function openLogWindow()
 { stopAllRefreshs();
   showUICtrls(false);
   document.getElementById('logdata').innerHTML='loading..';
   document.getElementById('logWindow').style.display='';
   refreshLogWindow();
 }

function refreshLogWindow()
 { document.getElementById('logFrame').src=self()+'?feed=<?php print($FEED);?>&cmd=showLog';
 }

function updateLogWindow(data)
 { var div = document.getElementById('logdata');
   div.innerHTML=data;
   div.scrollTop = div.scrollHeight;
 }

function closeLogWindow()
 { document.getElementById('logWindow').style.display='none';
   showUICtrls(true);
   restoreAllRefreshs();
 }

function openRawDataWindow()
 { stopAllRefreshs();
   showUICtrls(false);
   document.getElementById('rawdata').innerHTML='loading..';
   document.getElementById('rawDataWindow').style.display='';
   refreshRawDataWindow();
 }

function refreshRawDataWindow()
 {
   document.getElementById('rawDataFrame').src=self()+'?feed=<?php print($FEED);?>&cmd=showRawData';
 }

function getCsvData()
 { d = new Date();
   window.location=self()+'?feed=<?php print($FEED);?>&cmd=getcsv&UTCoffset='+60*d.getTimezoneOffset();
 }


function closeRawDataWindow()
 { document.getElementById('rawDataWindow').style.display='none';
   showUICtrls(true);
   restoreAllRefreshs();
 }

function timestampTolocalTime(timestamp)
 { var d = new Date(timestamp * 1000);
   var year = d.getFullYear();
   var month = ('0' + (d.getMonth() + 1)).slice(-2);
   var day = ('0' + d.getDate()).slice(-2);
   var hour = ('0' + d.getHours()).slice(-2);
   var min = ('0' + d.getMinutes()).slice(-2);
   var sec = ('0' + d.getSeconds()).slice(-2);
   return   year+'-'+month+'-'+day+' '+hour+':'+min+':'+sec ;
 }


function updateRawDataWindow(headers,resolution,data)
 { var div = document.getElementById('rawdata');
   html='<table cellspacing=0 cellpadding=0 class="rawDataTable">';

   html+='<thead>';
   h1='<th class="time" rowspan=2 valign="bottom">Date Time</th>';
   h2='';
   for (i=0;i<headers.length;i++)
   { h1=h1+'<th colspan=3>'+headers[i].replace('.','<br>')+'</th>';
     h2=h2+'<th>Min</th><th>Cur</th><th>Max</th>';
   }
   html+='<tr>'+h1+'</tr><tr>'+h2+'</tr><thead>';

   html+='<tbody>';
   for (i=0;i<data.length;i++)
   { html+='<tr><td class="time" >'+timestampTolocalTime(data[i][0])+'</td>';
     for (j=0;j<headers.length;j++)
	   { var r = resolution[j];
         var d0= (data[i][1][j][0]==null) ?'':data[i][1][j][0].toFixed(r);
		 var d1= (data[i][1][j][1]==null) ?'':data[i][1][j][1].toFixed(r);
		 var d2= (data[i][1][j][2]==null) ?'':data[i][1][j][2].toFixed(r);
	     html+='<td class="mi">'+d0+'</td><td class="cu">'+d1+'</td><td class="ma">'+d2+'</td>';
	   }
     html+='</tr>';
   }
   html+='</tbody>';





   html+='</table>';
   div.innerHTML=html;

 }


function cancelBgSettings()
 { var  values =document.getElementsByName('BgImgType');
	for (var i=0;i<values.length;i++)
     { values[i].checked = values[i].value==previousBgSettings['BgImgType'];
 	 }

  <?php
foreach ($background as $key => $value)
    if ($key != 'BgImgType')
        printf(" document.getElementById('%s').value = previousBgSettings['%s']\n;", $key, $key);
?>
  setBackground();
  document.getElementById('bgSettingsWindow').style.display='none';
  showUICtrls(true);
  restoreAllRefreshs();
 }

function saveBgEdit()
 { saveAll();
   restoreAllRefreshs();

   showUICtrls(true);
   document.getElementById('bgSettingsWindow').style.display='none';
 }


function freqEnableChange()
 { var v = document.getElementById('freqEnabled').checked;
   document.getElementById('freqMin').disabled=!v;
   document.getElementById('freqMax').disabled=!v;
   document.getElementById('freqWait').disabled=!v;
 }

var previousCallBackSettings = Array();
function opencallBackFreqSettings()
 { stopAllRefreshs();
   showUICtrls(false);
   <?php
foreach ($callBackFreq as $key => $value)
    if ($key == 'freqEnabled')
        printf("previousCallBackSettings['%s'] = document.getElementById('%s').checked;\n;", $key, $key);
    else
        printf("previousCallBackSettings['%s'] = document.getElementById('%s').value;\n;", $key, $key);
?>
    document.getElementById('callBackFreqSettings').style.display='';
 }

function cancelCallBackFreqSettings()
 { document.getElementById('callBackFreqSettings').style.display='none';
   <?php
foreach ($callBackFreq as $key => $value)
    if ($key == 'freqEnabled')
        printf("document.getElementById('%s').checked=previousCallBackSettings['%s'];  \n;", $key, $key);
    else
        printf("document.getElementById('%s').value=previousCallBackSettings['%s'];\n;", $key, $key);
?>
   freqEnableChange();
   restoreAllRefreshs();
   showUICtrls(true);
 }

function saveCallBackFreqSettings()
 { saveAll();
   restoreAllRefreshs();
   document.getElementById('callBackFreqSettings').style.display='none';
   showUICtrls(true);
 }

var previousWakeUpSettings = Array();
function openwakeUpSettings()
 { stopAllRefreshs();
   showUICtrls(false);
   <?php
foreach ($wakeUpSettings as $key => $value)
    if (($key == 'wakeUpEnabled') || ($key == 'wakeUpAutoSleep'))
        printf("previousWakeUpSettings['%s'] = document.getElementById('%s').checked;\n;", $key, $key);
    else
        printf("previousWakeUpSettings['%s'] = document.getElementById('%s').value;\n;", $key, $key);
?>
   wakeUpEnableChange();
   document.getElementById('wakeUpSettings').style.display='';
 }

function cancelWakeUpSettings()
 { document.getElementById('wakeUpSettings').style.display='none';
   <?php
foreach ($wakeUpSettings as $key => $value)
    if (($key == 'wakeUpEnabled') || ($key == 'wakeUpAutoSleep'))
        printf("document.getElementById('%s').checked=previousWakeUpSettings['%s'];  \n;", $key, $key);
    else
        printf("document.getElementById('%s').value=previousWakeUpSettings['%s'];\n;", $key, $key);
?>
   restoreAllRefreshs();
   showUICtrls(true);
 }

function saveWakeUpSettings()
 {
   if (document.getElementById('wakeUpEnabled').checked)
     { if   (((document.getElementById('wakeUpAutoSleep').checked)
           || (parseInt(document.getElementById('wakeUpSleepAfter').value)>0))
	       && (parseInt(document.getElementById('wakeUpDaysWeek').value)==0)
		   && (parseInt(document.getElementById('wakeUpDaysMonth').value)==0)
		   && (parseInt(document.getElementById('wakeUpMonths').value)==0)
		   && (parseInt(document.getElementById('wakeUpHours').value)==0)
		   && (parseInt(document.getElementById('wakeUpMinutesA').value)==0)
		   && (parseInt(document.getElementById('wakeUpMinutesB').value)==0))
	     if (!confirm("These settings might very well send the\nhub in infinite sleep, a physical interaction\nwill be needed to wake it up again.\n\nDo you really want to save these settings?")) return;

	 }
   saveAll();
   restoreAllRefreshs();
   document.getElementById('wakeUpSettings').style.display='none';
   showUICtrls(true);
 }

 function wakeUpEnableChange()
 { var v = document.getElementById('wakeUpEnabled').checked;
   document.getElementById('wakeUpAutoSleep').disabled=!v;
   document.getElementById('wakeUpSleepAfter').disabled=!v;
   document.getElementById('freqWait').disabled=!v;
   var btn = document.getElementsByClassName('calendarBtn');
   for (var i=0;i<btn.length;i++)
   {  var src = btn[i].getAttribute('data-key');
      var ofs = parseInt( btn[i].getAttribute('data-ofset'));
      value =  document.getElementById(src).value;
      if (value & (1<<ofs))
	     {  btn[i].style.color = v?'white':'#e0e0e0';
          btn[i].style.backgroundColor = v?'black':'#808080';
       }
	   else
	   {  btn[i].style.color = v?'black':'#f0f0f0';
         btn[i].style.backgroundColor = v?'white':'#E0E0E0';
     }
     btn[i].style.borderColor = v?'#606060':'#A0A0A0';
     btn[i].style.cursor = v?'pointer':'text';

   }
   var btn = document.getElementsByClassName('calendarCtl');
   for (var i=0;i<btn.length;i++)
   {   btn[i].style.color = v?'black':'#e0e0e0';
       btn[i].style.backgroundColor = v?'white':'#E0E0E0';
  	   btn[i].style.borderColor = v?'#606060':'#A0A0A0';
       btn[i].style.cursor = v?'pointer':'text';
   }
 }

var previousCleanUpSettings = Array();
function openCleanupWindow()
 { stopAllRefreshs();
   showUICtrls(false);
   <?php
foreach ($cleanUpSettings as $key => $value)
    if ($key == 'cleanUpEnabled')
        printf("previousCleanUpSettings['%s'] = document.getElementById('%s').checked;\n;", $key, $key);
    else
        printf("previousCleanUpSettings['%s'] = document.getElementById('%s').value;\n;", $key, $key);
?>
   wakeUpEnableChange();
   document.getElementById('cleanUpWindow').style.display='';
   refreshCleanUpWindow();
 }



function cancelCleanUpSettings()
 { document.getElementById('cleanUpWindow').style.display='none';
   <?php
foreach ($cleanUpSettings as $key => $value)
    if ($key == 'cleanUpEnabled')
        printf("document.getElementById('%s').checked=previousCleanUpSettings['%s'];  \n;", $key, $key);
    else
        printf("document.getElementById('%s').value=previousCleanUpSettings['%s'];\n;", $key, $key);
?>
   restoreAllRefreshs();
   showUICtrls(true);
 }

function saveCleanUpSettings()
 { var trimsize   = document.getElementById('dataTrimSize').value;
   var maxsize = document.getElementById('dataMaxSize').value;
   if (trimsize>=maxsize)
   { alert('Trim size must be less than maximum file size');
     return;
   }
   if (trimsize<2)
   { alert('Trim size must be greater than 2');
     return;
   }
   if ((trimsize/maxsize)>0.95)
   { alert('Trim size must be less then Maximum file size * 95%');
     return;
   }
  saveAll();
  document.getElementById('cleanUpWindow').style.display='none';
  showUICtrls(true);
  restoreAllRefreshs();

 }


function refreshCleanUpWindow()
 { document.getElementById('cleanupFrame').src=self()+'?feed=<?php print($FEED);?>&cmd=showDataStat';
 }

function deleteData(dataname,datafile)
 { if (confirm("Do really want to delete "+dataname+"?"))
	 document.getElementById('cleanupFrame').src=self()+'?feed=<?php print($FEED);?>&cmd=delete&file='+encodeURI(datafile);
 }

function sizeToStr(s)
{
   if (s<1024) return s+' bytes';
   if (s<1024*1024) return (s/1024).toFixed(1)+' Kb';

   return (s/(1024*1024)).toFixed(1)+' Mb';
}

function unixTimeStampToLocaStr(t)
{ var d = new Date(t*1000);
  var m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  var year  = d.getFullYear();
  var month = m[d.getMonth()];
  var date  = d.getDate();
  var hour  = '0'+d.getHours();
  var min   = '0'+d.getMinutes();
  var sec   = '0'+d.getSeconds();
  return   date + '-' + month + '-' + year + ' ' + hour.substr(-2) + ':' + min.substr(-2) + ':' + sec.substr(-2) ;
}


function updatefileDetails(data)
{ var html='';
  for (var i=0;i<data.length;i++)
   {   html+='<div class="fileDetailsContents">'
           +'<table width="100%">'
		   +'<tr><td colspan=3><b>'+data[i].name+'</b></td></tr>'
		   +'<tr><td>File name:</td><td>'+data[i].file+'</td><td rawspan=2></td></tr>'
		   +'<tr><td>File size:</td><td>'+sizeToStr(data[i].filesize)+'</td></tr>'
		   +'<tr><td>Records count:</td><td>'+data[i].recordscount+'</td><td rawspan=2 valign="bottom"><a class="button" href="javascript:deleteData('+"'"+data[i].name+"'"+",'"+data[i].file+"'"+')">Delete</a></td></tr>'
		   +'<tr><td>Last update:</td><td>'+unixTimeStampToLocaStr(data[i].lastupdate)+'</td></tr>'
		   +'</table></div>';
   }
  document.getElementById('filelist').innerHTML = html;
 }

 <?php
function PrintSaveCode($editorPrefix, $EDITABLE_VALUES)
{
    $indent = "   ";
    for ($i = 0; $i < sizeof($EDITABLE_VALUES); $i++)
        if ($EDITABLE_VALUES[$i]['editable']) {
            $suffix = '';
            if (!is_null($EDITABLE_VALUES[$i]['index']))
                $suffix = '_' . $EDITABLE_VALUES[$i]['index'];
            $name   = $EDITABLE_VALUES[$i]['name'];
            $prefix = $EDITABLE_VALUES[$i]['prefix'];
            if ($prefix != '')
                $prefix .= '_';
            Printf("{$indent}widgets[index].%s%s%s= document.getElementById('%seditor_%s%s%s').value;\n", $prefix, $name, $suffix, $editorPrefix, $prefix, $name, $suffix);
        }
}
?>

function saveGraphEdit()
 { var index  = parseInt(document.getElementById('WidgetIndex').value);
   <?php
PrintSaveCode('graph', $GRAPH_EDITABLE_VALUES);
?>
   saveAll();
   document.getElementById('grapheditor').style.display='none';
   showUICtrls(true);
   restoreAllRefreshs();
 }

function saveDecoEdit()
 { var index  = parseInt(document.getElementById('WidgetIndex').value);
   <?php
PrintSaveCode('deco', $DECO_EDITABLE_VALUES);
?>
   saveAll();
   document.getElementById('decoeditor').style.display='none';
   showUICtrls(true);
   restoreAllRefreshs();
 }





<?php
function PrintEditWidgetInputUpdate($editorName, $EDITABLE_VALUES)
{
    $indent = "   ";
    for ($i = 0; $i < sizeof($EDITABLE_VALUES); $i++)
        if ($EDITABLE_VALUES[$i]['editable']) {
            $suffix = '';
            if (!is_null($EDITABLE_VALUES[$i]['index']))
                $suffix = '_' . $EDITABLE_VALUES[$i]['index'];
            $name   = $EDITABLE_VALUES[$i]['name'];
            $prefix = $EDITABLE_VALUES[$i]['prefix'];
            if ($prefix != '')
                $prefix .= '_';
            if (($EDITABLE_VALUES[$i]['type']) == TYPE_STRING || ($EDITABLE_VALUES[$i]['type'] == TYPE_LONGSTRING))
                Printf("{$indent}document.getElementById('%s_%s%s%s').value=htmlDecode(widgets[index].%s%s%s);\n", $editorName, $prefix, $name, $suffix, $prefix, $name, $suffix);
            else if ($EDITABLE_VALUES[$i]['nullable'])
                Printf("{$indent}document.getElementById('%s_%s%s%s').value=widgets[index].%s%s%s==null?'':widgets[index].%s%s%s;\n", $editorName, $prefix, $name, $suffix, $prefix, $name, $suffix, $prefix, $name, $suffix);
            else
                Printf("{$indent}document.getElementById('%s_%s%s%s').value=widgets[index].%s%s%s;\n", $editorName, $prefix, $name, $suffix, $prefix, $name, $suffix);
        }
}
?>

function showUICtrls(state)
{ var UICtrl = document.getElementsByName('UICtrl');
  for (var i=0;i<UICtrl.length;i++)
    UICtrl[i].style.display=state?'':'none';
}

function editWidget(id)
{  var index= findwidgetIndex(id)
   if (index<0) return;
   stopAllRefreshs(widgets[index]);
   document.getElementById('WidgetIndex').value=index;
   document.getElementById('grapheditorHeader').innerHTML="Edit "+widgets[index].id+' properties';
   backupWidgetData(widgets[index])
   switch(parseInt(widgets[index].type))
   { case  GRAPHELMT:
           <?php PrintEditWidgetInputUpdate('grapheditor',$GRAPH_EDITABLE_VALUES);?>
	   document.getElementById('grapheditor').style.display='';
	   break;
     case  DECOELMT:
	   <?php PrintEditWidgetInputUpdate('decoeditor',$DECO_EDITABLE_VALUES);?>
	   document.getElementById('decoeditor').style.display='';
	   showUICtrls(false);
	   break;
   }
   showUICtrls(false);
   widgets[index]['_dataSrcHasChanged'] =false;
}
<?php editPartEnd(); ?>

function actionFailed($msg)
 { document.getElementById('errormessage').innerHTML=$msg;
   document.getElementById('ErrorDiv').style.display='';
 }
function closeErrorDiv()
 { document.getElementById('ErrorDiv').style.display='none';
 }


function actionSuccess($msg)
 { document.getElementById('NotifyDiv').style.opacity= 1.0;
   document.getElementById('Notifymessage').innerHTML=$msg;
   document.getElementById('NotifyDiv').style.display='';
   setTimeout(function(){notificationDecay()},1000);
 }

function notificationDecay()
 { var opacity = parseFloat(document.getElementById('NotifyDiv').style.opacity);
   opacity-=0.05;
   if (opacity>0)
	{ document.getElementById('NotifyDiv').style.opacity = opacity;
      setTimeout(function(){notificationDecay()},60);
	}
   else
   document.getElementById('NotifyDiv').style.display='none';
 }
<?php editPartStart(); ?>
function expandColapse(prefix,index)
 {  var div =document.getElementById(prefix+'Section'+index);
    var link =  document.getElementById(prefix+'link'+index);
	if (div.style.display=='')
	 { div.style.display='none';
       link.innerHTML =  '&#10133;'
	 }
	else
	 { div.style.display='';
       link.innerHTML =  '&#10134;'
	 }


 }


function switchMainMenuSide()
 { var div = document.getElementById('mainMenuContainer');
   var left= parseInt(window.getComputedStyle(div,null).getPropertyValue('left'));
   if (left<25)
   { div.style.right='10px';
     div.style.left=null;
   }
   else
   { div.style.left='10px';
	 div.style.right=null;
   }
 }

function sideswitch(prefix)
 { var div = document.getElementById(prefix);
   var left= window.getComputedStyle(div,null).getPropertyValue('left');
   var sideSwitcher = document.getElementById(prefix+'SideSwitcher');
   var header = document.getElementById(prefix+'Header');
   if (parseInt(left)==0)
   { div.style.right='0px';
     div.style.left=null;
	 div.style.borderTopRightRadius='0px';
	 div.style.borderBottomRightRadius='0px';
	 div.style.borderTopLeftRadius='10px';
	 div.style.borderBottomLeftRadius='10px';
	 sideSwitcher.style.right=null;
         sideSwitcher.style.left="0px";
	 header.style.textAlign='right';
   }
   else
       { div.style.right=null;
         div.style.left='0px';
	 div.style.borderTopRightRadius='10px';
	 div.style.borderBottomRightRadius='10px';
	 div.style.borderTopLeftRadius='0px';
	 div.style.borderBottomLeftRadius='0px';
	 sideSwitcher.style.right="0px";
         sideSwitcher.style.left=null;
	 header.style.textAlign='left';
   }
 }

 function refreshEditSubMenu()
 { var html = "<li><a href='javascript:openBgSettings()'>Background settings</a></li>";
   for (i=0;i<widgets.length;i++)
    { html = html+ "<li><a href='javascript:editWidget(\""+widgets[i].id+"\")'>"+widgets[i].id;
      if (widgets[i].title_text)  html=html+' ('+widgets[i].title_text+')';
	  html = html+"</a></li>";
	}
   document.getElementById('editSubMenu').innerHTML = html;
 }


<?php editPartEnd(); ?>

 function getBgType()
 { var  values =document.getElementsByName('BgImgType');
   var value ='none';
   for (var i=0;i<values.length;i++)
     { if (values[i].checked) value = values[i].value;
     }
   return value;
 }

 function bgselect(index)
  { var  values =document.getElementsByName('BgImgType');
    for (var i=0;i<values.length;i++)
		 values[i].checked = i==index;
	setBackground();
  }

 function setBackground()
 { var value =getBgType();
   var rule=null;
   var sheet = document.styleSheets[0];
   var rules = sheet.cssRules || sheet.rules;
   for (var i=0;i<rules.length;i++)
	if (rules[i].selectorText.toUpperCase()=='BODY')
   rule =	rules[i];
   rule.style.backgroundColor=document.getElementById('BgSolidColor').value;
   switch (value)
     {  case 'none' :

	       rule.style.backgroundImage=null;
	       rule.style.backgroundRepeat= null;
		   rule.style.backgroundSize= null;
		   break;

		case 'gradient' :
		   var c1= document.getElementById('BgGradientColor1').value;
		   var c2= document.getElementById('BgGradientColor2').value;
		   var angle= document.getElementById('BgGradientAngle').value;
		   rule.style.backgroundImage= "linear-gradient("+angle+"deg,"+c1+" 0%,"+c2+" 100%)";
		    break;
		case 'image' :
		   var url    = document.getElementById('BgImageUrl').value;
	       var repeat = document.getElementById('BgImageRepeat').value;

	      rule.style.backgroundImage= "url("+url+")";
		  switch (repeat)
		  { case 'cover' :   rule.style.backgroundRepeat= 'no-repeat';
		                     rule.style.backgroundSize= 'cover';
		                     break;
			case 'contain' : rule.style.backgroundRepeat= 'no-repeat';
		                     rule.style.backgroundSize= 'contain';
		                     break;
			default:         rule.style.backgroundSize= 'auto';
		                     rule.style.backgroundRepeat= repeat;
		                     break;
		  }
     }
 }

 <?php editPartStart(); ?>

function setAll(inputName,count,every)
 {  if (!document.getElementById('wakeUpEnabled').checked) return;
    var value = 0;
    var valueA = 0;
	var valueB = 0;
	if (count<32)
	{ if (every==0) document.getElementById(inputName).value =0;
	  else
	  { for (i=0;i<count;i++) if ((i%every)==0) value |= (1<<i);
		  document.getElementById(inputName).value =value;
	  }
	}
    else
	{ if (every==0)
	   { document.getElementById(inputName+'A').value =0;
	     document.getElementById(inputName+'B').value =0;
	   }
	  else
	  { for (i=0;i<count;i++)
		 if ((i%every)==0)
		   {  if (i<30) valueA  |= (1<<i);
	                 else valueB  |= (1<<(i-30));
		   }

		document.getElementById(inputName+'A').value =valueA;
        document.getElementById(inputName+'B').value =valueB;
	 }
	}

  for (var i=0;i<count;i++)
	{ var index=i;
    if (count>32)
	    { if (i<30)  value = valueA; else { value=valueB; index-=30;}
       }
      var el = document.getElementById(inputName+i);
	   if (value & (1<<index))
       { el.style.color = 'white';
	     el.style.backgroundColor = 'black';

       }
      else
       { el.style.color = 'black';
	       el.style.backgroundColor = 'white';

       }
	}

 }


function setCalendar(inputName,index,MoreThan32Value)
{  if (!document.getElementById('wakeUpEnabled').checked) return;
   var bitindex =index;
   var el = document.getElementById(inputName+index);
   if (MoreThan32Value)
	 if (index>=30)
	 { inputName=inputName+'B'
       bitindex-=30;
	 } else  inputName=inputName+'A';
   var value = parseInt(document.getElementById(inputName).value);
   var mask = (1<<bitindex);
   var pvalue= value;
   if (value & mask)
   {  value -=  mask;
      el.style.color = 'black';
	    el.style.backgroundColor = 'white';


   }
   else
   {  value |= mask;
      el.style.color = 'white';
	    el.style.backgroundColor = 'black';
   }

   document.getElementById(inputName).value = value;
}
 <?php editPartEnd(); ?>

 function updateOrientation()  // thanks stackoverflow
  {  var viewport = document.querySelector("meta[name=viewport]");
	 if (typeof window.orientation !== 'undefined')
	 { switch (window.orientation)
	   { case 90:
	     case -90:
            viewport.setAttribute('content', 'width=450');
			break;
         default:
	       viewport.setAttribute('content', 'width=900');
	   }
	 }


  }

 updateOrientation();
 window.addEventListener('orientationchange', updateOrientation, false);


</script>
</HEAD>

<BODY>
<iframe id='decoRefreshFrame' style='display:none'></iframe>
<?php
$config_file_found = file_exists($configfile);
$data_found        = sizeof(glob(DATAFOLDER . "/data-$FEED-*.bin")) > 0;
if (!$data_found || !$config_file_found) {
    print("<div style='position:absolute;text-align:center;top:40%;bottom:50%;left:0%;right:0%;font-size:4em;color:#d0d0d0'><tt>");
    if ($config_file_found)
        print('- No data in that feed -');
    else if ($data_found) {

        print('- Feed not configured yet -<br><span style="font-size:1em">');
        if (editMode())
            print('Click on the <i>New..</i> menu');
        else
            printf('Reload that URL with <i>%sedit</i> at the end.',$FEED=='default'?'?':'&');
        print('</span>');
    } else
        print('- Unknown feed -');
    print("</tt></div>");
}
?>


<div id='ErrorDiv' class='ErrorDiv' style='display:none'>
<span id='errormessage' ></span>
<p style='text-align:right'><a class='button' href='javascript:closeErrorDiv()'>Close</a></p>
</div>
<div id='NotifyDiv' class='NotifyDiv' style='display:none'>
<span id='Notifymessage' ></span>
</div>

<?php
function printEditorInnerCode($inputprefix, $EDITABLE_VALUES)
{
    global $sensors;
    $needClose    = false;
    $first        = true;
    $sectionindex = 0;
    $refreshFct   = 'refresh' . ucfirst($inputprefix);
    for ($i = 0; $i < sizeof($EDITABLE_VALUES); $i++) {
        $break = false;
        if (array_key_exists('columnBreak', $EDITABLE_VALUES[$i]))
            $break = $EDITABLE_VALUES[$i]['columnBreak'];
        if ($break) {
            if ($needClose)
                Printf("</table>\r\n");
            Printf("<div class='header'><a href='javascript:expandColapse(\"%seditor\",%d );'><div class='expandecolapse' id='%seditorlink%d'>%s</div></a> %s</div>\r\n", $inputprefix, $sectionindex, $inputprefix, $sectionindex, $first ? '&#10134;' : '&#10133;', $EDITABLE_VALUES[$i]['caption']);
            printf("<table id='%seditorSection%d' style='display:%s'>", $inputprefix, $sectionindex, $first ? '' : 'none');
            $sectionindex++;
            $first     = false;
            $needClose = true;
        } else {
            if ($EDITABLE_VALUES[$i]['editable']) {
                $suffix = '';
                $param2 = '';
                if (!is_null($EDITABLE_VALUES[$i]['index'])) {
                    $suffix = '_' . $EDITABLE_VALUES[$i]['index'];
                    $param2 = ',' . $EDITABLE_VALUES[$i]['index'];
                }
                $alt = '';
                if (array_key_exists('hint', $EDITABLE_VALUES[$i]))
                    $alt = sprintf("title=\"%s\" ", $EDITABLE_VALUES[$i]['hint']);
                $name   = $EDITABLE_VALUES[$i]['name'];
                $prefix = $EDITABLE_VALUES[$i]['prefix'];
                if ($prefix != '')
                    $prefix .= '_';
                switch ($EDITABLE_VALUES[$i]['type']) {
                    case TYPE_PERCENT:
                        Printf("<tr><td>%s:</td><td><input size=4 maxlength=4 id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>%%</td></tr>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        break;
                    case TYPE_PX:
                        Printf("<tr><td>%s:</td><td><input size=2 maxlength=4 id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>px</td></tr>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        break;
                    case TYPE_FONTSIZE:
                        Printf("<tr><td>%s:</td><td><input size=2 maxlength=4 id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt'>em</td></tr>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        break;
                    case TYPE_FLOAT:
                    case TYPE_INT:
                        Printf("<tr><td>%s:</td><td><input size=6 maxlength=6 id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt></td></tr>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        break;
                    case TYPE_STRING:
                        Printf("<tr><td>%s:</td><td><input size=12 maxlength=64 id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt></td></tr>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        break;
                    case TYPE_LONGSTRING:
                        Printf("<tr><td>%s:</td><td><textarea style='width:100%%' rows=2  id='%seditor_%s%s%s' onkeyup='$refreshFct(\"%s%s\"%s)' $alt></textarea></td></tr>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        break;
                    case TYPE_COLOR:
                        Printf("<tr><td>%s:</td><td><input size=7 maxlength=7 id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt></td></tr>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        break;
                    case TYPE_LEFTRIGHT:
                        Printf("<tr><td>%s:</td><td><select  id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        Printf("<option value='0'  >Left</option>\n");
                        Printf("<option value='1'   >Right</option>\n");
                        Printf("</select></td></tr>\n");
                        break;
                    case TYPE_YAXIS:
                        Printf("<tr><td>%s:</td><td><select  id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        for ($k = 0; $k < MAX_YAXIS_PER_GRAPH; $k++)
                            Printf("<option value='%d'>Y Axis %d</option>\n", $k, $k + 1);
                        Printf("</select></td></tr>\n");
                        break;
                    case TYPE_BOOL:
                        Printf("<tr><td>%s:</td><td><select  id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        Print("<option value='true'>Yes</option>\n");
                        Print("<option value='false'>No</option>\n");
                        Printf("</select></td></tr>\n");
                        break;
                    case TYPE_HALIGN:
                    case TYPE_TALIGN:
                        Printf("<tr><td>%s:</td><td><select  id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        Print("<option value='left'>Left</option>\n");
                        Print("<option value='center'>Center</option>\n");
                        Print("<option value='right'>Right</option>\n");
                        if ($EDITABLE_VALUES[$i]['type'] == TYPE_TALIGN)
                            Print("<option value='justify'>Justify</option>\n");
                        Printf("</select></td></tr>\n");
                        break;
                    case TYPE_PRECISION:
                        Printf("<tr><td>%s:</td><td><select  id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        $label = "123";
                        for ($j = 0; $j < 5; $j++) {
                            Print("<option value='$j'>$label</option>\n");
                            $label .= ($j == 0) ? '.0' : '0';
                        }
                        Printf("</select></td></tr>\n");
                        break;
                    case TYPE_VALIGN:
                        Printf("<tr><td>%s:</td><td><select  id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        Print("<option value='top'>Top</option>\n");
                        Print("<option value='middle'>Middle</option>\n");
                        Print("<option value='bottom'>Bottom</option>\n");
                        Printf("</select></td></tr>\n");
                        break;
                    case TYPE_LAYOUT:
                        Printf("<tr><td>%s:</td><td><select  id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        Print("<option value='horizontal'>Horizontal</option>\n");
                        Print("<option value='vertical'>Vertical</option>\n");
                        Printf("</select></td></tr>\n");
                        break;
                    case TYPE_DATASRC:
                        Printf("<tr><td>%s:</td><td><select style='widtH:100%%'  id='%seditor_%s%s%s' onchange='$refreshFct(\"%s%s\"%s)' $alt>\n", $EDITABLE_VALUES[$i]['caption'], $inputprefix, $prefix, $name, $suffix, $prefix, $name, $param2);
                        Printf("<option value='' selected='selected' >None</option>\n");
                        for ($k = 0; $k < sizeof($sensors); $k++)
                            Printf("<option value='%s'>%s</option>\n", $sensors[$k]->hname, $sensors[$k]->name);
                        Printf("</select></td></tr>\n");
                        break;
                }
            }
            if ($EDITABLE_VALUES[$i]['appliesTo'] == NOTHING)
                Printf("<tr><td colspan=2><b>%s</b></td></tr>\n", $EDITABLE_VALUES[$i]['caption']);
        }
    }
    if ($needClose > 0)
        Printf("</table>\r\n");
}
?>
<input id='WidgetIndex' type='hidden'>
<?php editPartStart(); ?>
<div id='grapheditor' class='grapheditor' style='display:none;left:0%;'>
<div id='grapheditorHeader' class='edit_header'>Edit graph property</div>
<div class='propertylist'>
<?php printEditorInnerCode('graph',$GRAPH_EDITABLE_VALUES);?>
</div>
<div class='edit_footer'>
<a class='edit_button' href='javascript:saveGraphEdit()'>Save</a>
<a class='edit_button' href='javascript:cancelGraphEdit()'>Cancel</a>
</div>
<a href='javascript:sideswitch("grapheditor");'>
<div class='sideswitcher' id='grapheditorSideSwitcher' style='right:0px'  >
&#8633;
</div></a></div>

<div id='decoeditor' class='grapheditor' style='display:none;left:0%;'>
<div id='decoeditorHeader' class='edit_header'>Edit text block property</div>
<div class='propertylist'>
<?php printEditorInnerCode('deco',$DECO_EDITABLE_VALUES);?>
</div>
<div class='edit_footer'>
<a class='edit_button' href='javascript:saveDecoEdit()'>Save</a>
<a class='edit_button' href='javascript:cancelDecoEdit()'>Cancel</a>
</div>
<a href='javascript:sideswitch("decoeditor");'>
<div class='sideswitcher' id='decoeditorSideSwitcher' style='right:0px'  >
&#8633;
</div></a></div>


<div id='mainMenuContainer' style='position:absolute;top:10px;left:10px;z-index:100000'>
<ul id='MainMenu' name="UICtrl">
<li class='mainMenuItem' id='MainMenu0'><a>New..</a>
<ul class='subMenu'>
<li><a href='javascript:NewGraphWidget(null)'>Chart</a></li>
<li><a href='javascript:NewDecoWidget(null)'>Text block</a></li>
</ul>
</li>
<li class='mainMenuItem' id='MainMenu0'><a>Edit..</a>
<ul class='subMenu' id='editSubMenu'>
<li><a href='javascript:openBgSettings()'>Background Settings</a></li>
</ul>
</li>
<li class='mainMenuItem' id='MainMenu1' ><a >Feed</a>
<ul class='subMenu'>
<li><a href='javascript:opencallBackFreqSettings()'>Callback Frequency</a></li>
<li><a href='javascript:openwakeUpSettings()'>Wake Up Settings</a></li>
<li><a href='javascript:openRawDataWindow()'>Raw Data</a></li>
<li><a href='javascript:openCleanupWindow()'>Data Clean-up</a></li>
<li><a href='javascript:openLogWindow()'>Logs</a></li>
</ul>
<li class='mainMenuItem' id='MainMenu1' ><a href='javascript:switchMainMenuSide()'>&#8633; </a></li>
</li>
</ul>
</div>

<div id='bgSettingsWindow' class='settingsWindow' style='position:absolute;left:35%;top:20%;display:none;'>
<div class='edit_header'>Background Settings</div>
<div class='edit-contents'>
Define the background properties and click save.<br><br>
<table width='100%'>
<tr><td colspan=2><b>Background color</b></td></tr>
<tr><td style='padding-left:25px'>Color :</td><td><input id='BgSolidColor'  value='<?php print($background["BgSolidColor"]);?>' onchange='setBackground()'  size="7" maxlength="7"></td></tr>
<tr><td colspan=2><br><b>Background image</b></br></td></tr>
<tr><td colspan=2><input type="radio" name="BgImgType" value="none" onchange='setBackground()' <?php print($background["BgImgType"]=='none'?"checked":"");?> >None</td></tr>
<tr><td colspan=2><input type="radio" name="BgImgType" value="gradient" onchange='setBackground()' <?php print($background["BgImgType"]=='gradient'?"checked":"");?>>Gradient</td></tr>
<tr><td style='padding-left:25px'>Color 1 :</td><td><input id='BgGradientColor1'  value='<?php print($background["BgGradientColor1"]);?>' onchange='setBackground()'  size="7" maxlength="7"></td></tr>
<tr><td style='padding-left:25px'>Color 2 :</td><td><input id='BgGradientColor2'  value='<?php print($background["BgGradientColor2"]);?>' onchange='setBackground()'  size="7" maxlength="7"></td></tr>
<tr><td style='padding-left:25px'>Angle:</td><td><input id='BgGradientAngle'  value='<?php print($background["BgGradientAngle"]);?>' onchange='setBackground()' size="3" maxlength="3"> deg</td></tr>
<tr><td colspan=2><input type="radio" name="BgImgType" value="image" onchange='setBackground()' <?php print($background["BgImgType"]=='image'?"checked":"");?>>Image</td></tr>
<tr><td style='padding-left:25px'>Image Url :</td><td><input id='BgImageUrl'  value='<?php print($background["BgImageUrl"]);?>'  onchange='setBackground()' ></td></tr>
<tr><td style='padding-left:25px'>Repeat :</td><td><select id='BgImageRepeat' onchange='setBackground()' >
              <option value='no-repeat'<?php print($background["BgImageRepeat"]=='no-repeat'?"selected":"");?> >No repeat</option>
	      <option value='repeat'   <?php print($background["BgImageRepeat"]=='repeat'?"selected":"");?>>Repeat X and Y</option>
	      <option value='repeat-x' <?php print($background["BgImageRepeat"]=='repeat-x'?"selected":"");?>>Repeat X</option>
	      <option value='repeat-y' <?php print($background["BgImageRepeat"]=='repeat-y'?"selected":"");?>>Repeat Y</option>
	      <option value='cover'    <?php print($background["BgImageRepeat"]=='cover'?"selected":"");?>>Cover</option>
	      <option value='contain'  <?php print($background["BgImageRepeat"]=='contain'?"selected":"");?>>Contain</option>
	      </select></td></tr>
</table>
<br>
<br>
<br>
</div>
<div class='edit_footer'>
<a class='edit_button' href='javascript:saveBgEdit()'>Save</a>
<a class='edit_button' href='javascript:cancelBgSettings()'>Cancel</a>
</div>
</div>


<div id='logWindow' class='settingsWindow' style='position:absolute;left:20%;top:10%;width:60%;bottom:10%;display:none;'>
<div class='edit_header'>Log file</div>
<div class='edit-contents' id='logdata' style='white-space: pre-wrap;position:absolute;font-size:0.8em;font-family:courier;overflow:scroll;left:0px;right:0px;top:26px;bottom:25px;'>
</div>
<br>
<br>
<br>
<div class='edit_footer'>


<a class='edit_button' href='javascript:refreshLogWindow()'>Refresh</a>
<a class='edit_button' href='javascript:closeLogWindow()'>Close</a>
</div>
<iframe id='logFrame' style='display:none'></iframe>
</div>


<div id='rawDataWindow' class='settingsWindow' style='position:absolute;left:5%;top:5%;width:90%;bottom:5%;display:none;'>
<div class='edit_header'><?php printf("Raw data: %d last records for feed %s.",RAWDATACLUSTERSIZE,$FEED);?></div>

<div class='edit-contents' id='rawdata' style='white-space: pre-wrap;position:absolute;font-size:0.8em;font-family:courier;overflow:scroll;left:0px;right:0px;top:26px;bottom:25px;'>

</div>
<br>
<br>
<br>
<div class='edit_footer'>
<a class='edit_button' href='javascript:getCsvData()'>CSV file</a>
<a class='edit_button' href='javascript:refreshRawDataWindow()'>Refresh</a>
<a class='edit_button' href='javascript:closeRawDataWindow()'>Close</a>
</div>

</div>
<iframe id='rawDataFrame' style='display:none'></iframe>


<div id='callBackFreqSettings' class='settingsWindow' style='position:absolute;left:35%;top:20%;width:40%;display:none;'>
<div class='edit_header'>Frequency settings</div>
<div class='edit-contents'>
You can modify the callback frequency settings for any hub connecting through this feed.
Just click on checkbox below if you want the system to overide the YoctoHub settings.  <br><br>
<table width='100%'>
<tr><td colspan=2><input type='checkbox' id='freqEnabled' onclick='freqEnableChange()'  <?php printf($callBackFreq['freqEnabled']?'checked':'');?> > Manage callback frequency</td></tr>
<tr><td colspan=2><b>Desired frequency of notifications:</b></td></tr>
<tr><td style='text-align:right'>No less than:</td><Td> <input id='freqMin' value='<?php printf($callBackFreq['freqMin']);?>' <?php printf($callBackFreq['freqEnabled']?'':'DISABLED');?> size=3 > seconds between two notifications</td></tr>
<tr><td style='text-align:right'>But notify after:</td><Td> <input id='freqMax'  value='<?php printf($callBackFreq['freqMax']);?>' <?php printf($callBackFreq['freqEnabled']?'':'DISABLED');?> size=3  > seconds in any case</td></tr>
<tr><td style='text-align:right'>At startup, wait for:</td><Td> <input id='freqWait' value='<?php printf($callBackFreq['freqWait']);?>' <?php printf($callBackFreq['freqEnabled']?'':'DISABLED');?> size=3  >seconds before the first callback</td></tr>
</table>
</div>
<br>
<br>
<br>
<div class='edit_footer'>
<a class='edit_button' href='javascript:saveCallBackFreqSettings()'>Save</a>
<a class='edit_button' href='javascript:cancelCallBackFreqSettings()'>Cancel</a>
</div>
</div>


<div id='cleanUpWindow' class='settingsWindow' style='position:absolute;left:20%;top:10%;bottom:10%;width:60%;display:none;'>
<div class='edit_header'>Data Clean Up</div>

<input type='checkbox' id='cleanUpEnabled' <?php printf($cleanUpSettings['cleanUpEnabled']?'checked':'');?> >
Automaticaly trim data file contents to
<input size=5 id='dataTrimSize' value='<?php printf($cleanUpSettings['dataTrimSize']);?>'>
records when file size is greater than
<input size=7 id='dataMaxSize' value='<?php printf($cleanUpSettings['dataMaxSize']);?>'> records.
<div id='filelist' style='position:absolute;top:50px;bottom:1px;left:1px;bottom:30px;right:10px;overflow-Y:scroll'></div>
<div class='edit_footer'>
<a class='edit_button' href='javascript:saveCleanUpSettings()'>Save</a>
<a class='edit_button' href='javascript:cancelCleanUpSettings()'>Cancel</a>
</div>
</div>

<iframe id='cleanupFrame' style='display:none'></iframe>




<?php
function PrintWakeUpChooser($suffix, $values, $value)
{
    $inputname = "wakeUp$suffix";
    if (sizeof($values) <= 32) {
        Printf("<input id='wakeUp%s' type='hidden' value='%d'>", $suffix, $value["wakeUp$suffix"]);
    } else {
        Printf("<input id='wakeUp%sA' type='hidden' value='%d'>", $suffix, $value["wakeUp{$suffix}A"]);
        Printf("<input id='wakeUp%sB' type='hidden' value='%d'>", $suffix, $value["wakeUp{$suffix}B"]);
    }
    for ($i = 0; $i < sizeof($values); $i++) {
        $index = $i;
        $key   = "wakeUp$suffix";
        if (sizeof($values) > 32) {
            $key = "wakeUp$suffix" . "A";
            if ($i > 29) {
                $index -= 30;
                $key = "wakeUp$suffix" . "B";
            }
        }
        $set = $value[$key] & (1 << $index) ? 1 : 0;
        printf("<a  class='calendarBtn' id='%s%d'  data-key='%s' data-ofset='%d' href='javascript:setCalendar(\"%s\",%d,%s)'> %s </a> ", $inputname, $i, $key, $index, $inputname, $i, sizeof($values) > 32 ? 'true' : 'false', $values[$i]);
    }
}
function printPresetButtons($name, $btns, $count)
{
    printf("<a  class='calendarCtl'    href='javascript:setAll(\"wakeUp%s\",%d,0)'> Clear All </a> ", $name, $count);
    for ($i = 0; $i < sizeof($btns); $i++)
        printf(" <a  class='calendarCtl'   href='javascript:setAll(\"wakeUp%s\",%d,%s)'> Every %d </a> ", $name, $count, $btns[$i], $btns[$i]);
    printf(" <a  class='calendarCtl'    href='javascript:setAll(\"wakeUp%s\",%d,1)'> Set All </a> ", $name, $count);
}
?>


<div id='wakeUpSettings' class='settingsWindow' style='position:absolute;left:25%;top:5%;width:50%;display:none;'>
<div class='edit_header'>Wake up settings</div>
<div class='edit-contents'>
You can modify the wakeup parameters for any hub connecting through this feed. This
will only apply on hubs featuring a Wake Up function such as YoctoHub-Wireless and
YoctoHub-GSM. Just click on check-box below if you want the system to override the
YoctoHub wake up settings.  <br><br>
<table width='100%' >
<tr><td colspan=2><input type='checkbox' id='wakeUpEnabled' onclick='wakeUpEnableChange()'  <?php printf($wakeUpSettings['wakeUpEnabled']?'checked':'');?> > Manage wakeup seetings</td></tr>
<tr><td colspan=2><b>Sleep:</b></td></tr>
<tr><td colspan=2><input type='checkbox' id='wakeUpAutoSleep'   <?php printf($wakeUpSettings['wakeUpAutoSleep']?'checked':'');?> > Send the hub to sleep right after the first callback.</td></tr>
<tr><td colspan=2>No matter what, send the hub to sleep after <input id='wakeUpSleepAfter' value='<?php printf($wakeUpSettings['wakeUpSleepAfter']);?>' <?php printf($wakeUpSettings['wakeUpSleepAfter']?'':'DISABLED');?> size=3 > seconds.</td></tr>

<tr><td colspan=2><b>Wake up:</b></td></tr>
<tr><td colspan=2 style='text-align:justify'>
Define wake up times: each button toggles a condition. A wake-up will occur when at least one condition per section is true. Sections without any condition defined are ignored.
</td></tr>
<tr><td colspan=2 style='background-color:#e0e0e0'>Days in the week</td></tr>
<tr><td colspan=2 style='padding-left:20px;'>
<?php PrintWakeUpChooser('DaysWeek',Array('Mon','Tue','Wed','Thu','Fri','Sat','Sun'),$wakeUpSettings ); ?>
</td></tr>
<tr ><td colspan=2 style='background-color:#e0e0e0' >Days in the Month</td></tr>
<tr><td colspan=2 style='padding-left:20px;'>
<?php
$a = array();
for ($i = 0; $i < 31; $i++)
    $a[$i] = ($i + 1);
 PrintWakeUpChooser('DaysMonth',$a,$wakeUpSettings ); ?>
</td></tr>
<td colspan=2 style='text-align:right'><?php printPresetButtons('DaysMonth',array(2,3),31);?> </td>
<tr><td colspan=2 style='background-color:#e0e0e0'>Months</td></tr>
<tr><td colspan=2 style='padding-left:20px;'>
<?php PrintWakeUpChooser('Months',Array('Jan','Feb','Mar','Avr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'),$wakeUpSettings ); ?>
</td></tr>

<tr><td colspan=2 style='background-color:#e0e0e0'>Hours</td></tr>
<tr><td colspan=2 style='padding-left:20px;'>
<?php   $a=array(); for ($i=0;$i<24;$i++) $a[$i]=$i;
 PrintWakeUpChooser('Hours',$a,$wakeUpSettings ); ?>
</td></tr>
<td colspan=2 style='text-align:right'><?php printPresetButtons('Hours',array(2,3,4,6),24);?> </td>

<tr><td colspan=2 style='background-color:#e0e0e0'>Minutes</td></tr>
<tr><td colspan=2 style='padding-left:20px;'>
<?php  $a=array(); for ($i=0;$i<60;$i++) $a[$i]=$i;
 PrintWakeUpChooser('Minutes',$a,$wakeUpSettings ); ?>
</td></tr>
<td colspan=2 style='text-align:right'><?php printPresetButtons('Minutes',array(2,3,4,5,10,15),60);?> </td>


</table>
</div>
<br>
<br>
<br>
<div class='edit_footer'>
<a class='edit_button' href='javascript:saveWakeUpSettings()'>Save</a>
<a class='edit_button' href='javascript:cancelWakeUpSettings()'>Cancel</a>
</div>
</div>


<?php editPartEnd(); ?>



<iframe id='serviceFrame' style='position:absolute;left:0px;bottom:0px;margin-left:5px;margin-bottom:5px;border: 1px solid grey;display:none;' ></iframe>


<?php
openNoticeMessage();
// So, you really want to remove that annoying pop-up notice, right?
// Just create an empty file named YesIKnowThatHighStockLibraryIsNotFree.txt
// next to this file and the pop-up will disappear. That being said,
// if you plan to use that application for anything else but personal
// or non-profit project, you should buy a HighStock license. These
// guys at HighSoft made an amazing work, they deserve their money.
?>
<div id='notice1' style='position:absolute;top:0px;height:0px;width:100%;height:100%;background-color:#e0e0e0;z-index:100001;opacity: 0.9;text-align:center;'></div>
<div id='notice2' style='position:absolute;top:0px;height:0px;width:100%;height:100%;z-index:100002;'>
<table style='width:100%;height:100%' >
<tr><td colspan=3></td></tr>
<tr><td width="30%"></td><td>
<div id='notice' style='margin-left:25px;margin-right:25px;margin-top:10px;margin-bottom:25px;border: 3px solid red; border-radius:10px;padding: 5px;background-color : #f8f8f8;text-align:justify;'>
<?php printNoticeMessage(); ?>
</div></td><td width="30%"></td></tr><tr><td colspan=3></td></tr>
</table></div>
<script>
it =  document.getElementById('gi'+'bt');
if (it)
{ it.addEventListener('click',hideNotice);
  it.addEventListener('touchstart',hideNotice);
}
</SCRIPT>
<?php closeNoticeMessage();?>


</BODY>
<?php
if (!is_null($inidata)) {
    $jsode    = 'function startApp() {';
    $nullable = Array();
    $type     = array();
    for ($j = 0; $j < sizeof($GRAPH_EDITABLE_VALUES); $j++)
        if (array_key_exists('nullable', $GRAPH_EDITABLE_VALUES[$j])) {
            $name   = $GRAPH_EDITABLE_VALUES[$j]['name'];
            $prefix = $GRAPH_EDITABLE_VALUES[$j]['prefix'];
            if ($prefix != '')
                $name = $prefix . '_' . $name;
            if (!is_null($GRAPH_EDITABLE_VALUES[$j]['index']))
                $name .= '_' . $GRAPH_EDITABLE_VALUES[$j]['index'];
            $nullable[$name] = $GRAPH_EDITABLE_VALUES[$j]['nullable'];
            $type[$name]     = $GRAPH_EDITABLE_VALUES[$j]['type'];
        }
    if ($inidata === false)
        $jsode = "actionFailed('Cannot parse $configfile');";
    else
        foreach ($inidata as $key => $section) {
            if (array_key_exists('type', $section)) {
                $jsode .= "var index      = widgets.length;\n";
                $jsode .= "widgets[index] = DefaultWidget({$section['type']},index);\n";
                foreach ($section as $subkey => $value) {
                    $value = json_encode($section[$subkey]);
                    if (array_key_exists($subkey, $type)) {
                        if ($type[$subkey] == TYPE_BOOL)
                            $value = ($value == '"true"') ? 'true' : 'false';
                        if ($type[$subkey] == TYPE_INT)
                            $value = IntVal(trim($value, '"'));
                    }
                    if (array_key_exists($subkey, $nullable) && ($nullable[$subkey] && ($section[$subkey] == '' || $section[$subkey] == 'null')))
                        $value = "null";
                    $jsode .= sprintf("widgets[index]['$subkey'] = %s;\n", $value);
                }
                $jsode .= "CreateWidget(widgets.length-1,{$section['type']});\n";
            }
        }
    if (editMode())
        $jsode .= "refreshEditSubMenu();\n";
    $jsode .= "startDecoRefresh(); }\n";
    if (($appReady & 0xf2) != 0)
        $jsode .= "if (typeof startApp === 'function') startApp();\n";
    addJsCode($jsode);
}
Print("<!--\n");
RunTrimProcess();
Print("-->\n");
?>
</HTML>
