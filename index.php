<?php

$version = "1.2";
$txta = "Route converter";
$txtb = "GS-Sport Training Gym Pro";

function getdistance($gpx){
	$totaldist = 0;
	$earth_radius = 6371;
		$lat1 = $gpx[0]['lat'];
		$lon1 = $gpx[0]['lon'];
	foreach ($gpx as $k=>$v){
		if ( $k>0 ) {
			$lat2 = $v['lat'];
			$lon2 = $v['lon'];
			if (( $lat1 != $lat2 ) && ( $lon1 != $lon2 ))
			{
				/*
					Haversine formula
					https://www.movable-type.co.uk/scripts/latlong.html
				*/
		    	$dLat = deg2rad($lat2 - $lat1);
		    	$dLon = deg2rad($lon2 - $lon1);
		    	$a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
				$c = 2 * asin(sqrt($a));
				$d = $earth_radius * $c;
				
				$totaldist = $totaldist + ( $d * 1000);
				$lat1 = $lat2;
				$lon1 = $lon2;
			}
		}
	}
	// precision to 1 meter
	$totaldist = normf($totaldist);
	return $totaldist;
}
function normf($t){
	return number_format($t, 0, ',', '');
}

/*
	START
*/
if(isset($_POST['action']) and $_POST['action'] == 'upload'){

    if(isset($_FILES['user_file']))
    {
        $files = $_FILES['user_file'];
		$url = $_FILES["user_file"]["tmp_name"]; 
    	$file_name = $_FILES["user_file"]["name"];
	}
	
	$totaldist=0;
	$aupheight=0;
	$adownheight=0;
	$gpx=array();

if (strpos($file_name,'.js')!==false) {
	$gpx=json_decode(@file_get_contents($url),true);
	if (!empty($gpx)) {
		$gpx=$gpx['data']['trackData'][0];
	}
}elseif (strpos($file_name,'.gpx')!==false){
	$gpxxml=simplexml_load_file($url);
	if (!empty($gpxxml)) {
		$totaldist=substr(str_replace('.',',',(string)$gpxxml->metadata->extensions->children('gpsies',true)->trackLengthMeter ),0,5);
		$aupheight=(int)$gpxxml->metadata->extensions->children('gpsies',true)->totalAscentMeter;
		$adownheight=(int)$gpxxml->metadata->extensions->children('gpsies',true)->totalDescentMeter;
		$t=0;
		foreach ($gpxxml->trk->trkseg->trkpt as $v) {
			$gpx[$t]['lat']=(float)$v->attributes()->{'lat'};
			$gpx[$t]['lon']=(float)$v->attributes()->{'lon'};
			$gpx[$t]['ele']=(int)$v->ele;
			$t++;
		}
	}
}

if (!empty($gpx)) {
	/*
		Calc: min & max altitude, ascend and descend, total distance
	*/
	$alt=array();
	$au = 0;
	$ad = 0;
	$l = 0;
	foreach ($gpx as $k=>$v){
		$a = $v['ele'];
		$alt[]= $a;
		if ($k>0) {
			$e = $a - $l;
			if ($e>0) {
				$au = $au + $e;
			}elseif ($e<0){
				$ad = $ad - $e;
			}
		}
		$l = $a;
	}
	$altmin = (int)min($alt);
	$altmax = (int)max($alt);
	if (!$aupheight) { $aupheight = normf($altmin + $au/2); }
	if (!$adownheight) { $adownheight = normf($altmin + $ad/2); }
	if (!$totaldist) { $totaldist = getdistance($gpx); }
	/*
		Construct XML
	*/
	$xml=new SimpleXMLElement("<GB-580_Dataform></GB-580_Dataform>");
	$ftime=filemtime($url);
	$trackid=date("njHis",$ftime);
	$trackname=date("Y-n-j",$ftime);
	$starttime=date("H:i:s",$ftime);
	$noofpoints=count($gpx);
	$file_xml=date("y",$ftime).$trackid;
	
	$header=$xml->addChild( 'trackHeader' );
	$lapmaster=$xml->addChild( 'trackLapMaster' );
	
	$header->addChild( 'TrackID', $trackid );
	$header->addChild( 'TrackName', $trackname );
	$header->addChild( 'StartTime', $starttime );
	$header->addChild( 'During', 0 );
	$header->addChild( 'TotalDist', $totaldist );
	$header->addChild( 'Calories', 0 );
	$header->addChild( 'MaxSpeed', 0 );
	$header->addChild( 'MaxHearRate', 0 );
	$header->addChild( 'AvgHeartRate', 0 );
	$header->addChild( 'NoOfPoints', $noofpoints );
	$header->addChild( 'NoOfLaps', 1 );
	$header->addChild( 'AUpheight', $aupheight );
	$header->addChild( 'ADownheight', $adownheight );
	$header->addChild( 'AvgCadence', 0 );
	$header->addChild( 'MaxCadence', 0 );
	$header->addChild( 'AvgPower', 0 );
	$header->addChild( 'MaxPower', 0 );
	$header->addChild( 'MinAltitude', $altmin );
	$header->addChild( 'MaxAltitude', $altmax );
	$header->addChild( 'MultiSport', 0 );
	$header->addChild( 'Sport1', 4 );
	$header->addChild( 'Sport2', 0 );
	$header->addChild( 'Sport3', 0 );
	$header->addChild( 'Sport4', 0 );
	$header->addChild( 'Sport5', 0 );
	
	$lapmaster->addChild( 'TrackMasterID', $trackid );
	$lapmaster->addChild( 'LapNo', 1 );
	$lapmaster->addChild( 'AccruedTime', 0 );
	$lapmaster->addChild( 'TotalTime', 0 );
	$lapmaster->addChild( 'totalDistance', $totaldist );
	$lapmaster->addChild( 'calory', 0 );
	$lapmaster->addChild( 'MaxSpeed', 0 );
	$lapmaster->addChild( 'MaxHR', 0 );
	$lapmaster->addChild( 'AvgHR', 0 );
	$lapmaster->addChild( 'startIndex', 0 );
	$lapmaster->addChild( 'endIndex', $noofpoints-1 );
	$lapmaster->addChild( 'AvgCadence', 0 );
	$lapmaster->addChild( 'BestCadence', 0 );
	$lapmaster->addChild( 'AvgPower', 0 );
	$lapmaster->addChild( 'MaxPower', 0 );
	$lapmaster->addChild( 'MinAltitude', $altmin );
	$lapmaster->addChild( 'MaxAltitude', $altmax );
	$lapmaster->addChild( 'MultiSportIndex', 4 );
	
	foreach ($gpx as $k=>$v){
		$tp = $xml->addChild('trackLapPoints');
		$tp->addChild('Sl_x0020_No', $k+1);
		$tp->addChild('Latitude', str_replace('.',',',$v['lat']));
		$tp->addChild('Longitude', str_replace('.',',',$v['lon']));
		$tp->addChild('Altitude', (int)$v['ele'] );
		$tp->addChild('Speed', 0);
		$tp->addChild('Heart_x0020_Rate', 0);
		$tp->addChild('Interval_x0020_Time', ($k?1:0) );
		$tp->addChild('Index', $trackid);
		$tp->addChild('Cadence', 0);
		$tp->addChild('PwrCadence', 0);
		$tp->addChild('Power', 0);
	}

}else{
	echo "ERROR!";
}

$dom = dom_import_simplexml($xml)->ownerDocument;
$dom->formatOutput = true;
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename='.$file_xml.'.xml' );
	echo $dom->saveXML();
exit();
}
?>

<html>
<head>
	<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />
	<script type="text/javascript" src="https://code.jquery.com/jquery-1.12.4.min.js"></script>

	<style type="text/css">
	body{width: 100%;height: 100%}
	.row{display: flex;justify-content: center; align-items: center;height:100%}
	.row>div{text-align:center}
	h3{color:#3cd}
	span.label{line-height:2;position: relative;top: -.2em}
	.inputfile{width:.1px;height:.1px;opacity:0;overflow:hidden;position:absolute;z-index:-1}
	.inputfile-1 + label{color:#fff;background-color:#3cd}
	.inputfile + label{max-width:80%;font-size:1.25rem;font-weight:700;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;display:inline-block;overflow:hidden;padding:.625rem 1.25rem;margin-bottom:1.5rem}
	.inputfile + label svg{width:1em;height:1em;vertical-align:middle;fill:currentColor;margin-top:-.25em;margin-right:.25em}
	.txt-normal{font-weight:normal}
	.btn-default.btn-on.active, .btn-default.btn-off.active{background-color: #3cd;color: white}	
	.btn-switch .btn-default.btn-off.active{background-color: #777}
	#footer{margin:auto}
	</style>
	<script type="text/javascript">
	var ff = {
		labelval : 'Choose a file...',
		input:"file-1",
		labelchange : function(label){
			$('label[for="'+ff.input+'"] span').html(label);
		}
	};
	
	$(function(){
		ff.labelchange(ff.labelval);
			$('#'+ff.input).on('change', function(){
				var file = document.forms['form'][ff.input].files[0];
				//file.name == "photo.png"
				//file.type == "image/png"
				//file.size == 300821
				ff.labelchange(file.name);
				$('input[type="submit"]').removeClass('hidden');
				$('input[type="reset"]').removeClass('hidden');
			});
			$('input[type="reset"]').on('click', function(){
				$('input[type="submit"]').addClass('hidden');
				$('input[type="reset"]').addClass('hidden');
				ff.labelchange(ff.labelval);
			});
	});
	</script>
	<title><?=$txta?> for <?=$txtb?></title>
</head>
<body>
	<div class="container">
	<div class="row">
	<div class="col-xs-12">
	<h3><?=$txta?> <span class="badge"><?=$version?></span><br /><small>for <?=$txtb?></small></h3>
	<p class="small">GPX/JSON Track <span class="label label-default">gpsies.com</span><br>convert to XML <span class="label label-default">GlobalSite</span></p>
	<hr />
		<form id="form" method="post" action="index.php" enctype="multipart/form-data">
			<input type="hidden" name="action" value="upload" />
			<input id="file-1" class="inputfile inputfile-1" type="file" name="user_file" />
				<label for="file-1" class="btn btn-default"><svg xmlns="#" width="20" height="17" viewBox="0 0 20 17">
					<path d="M10 0l-5.2 4.9h3.3v5.1h3.8v-5.1h3.3l-5.2-4.9zm9.3 11.5l-3.2-2.1h-2l3.4 2.6h-3.5c-.1 0-.2.1-.2.1l-.8 2.3h-6l-.8-2.2c-.1-.1-.1-.2-.2-.2h-3.6l3.4-2.6h-2l-3.2 2.1c-.4.3-.7 1-.6 1.5l.6 3.1c.1.5.7.9 1.2.9h16.3c.6 0 1.1-.4 1.3-.9l.6-3.1c.1-.5-.2-1.2-.7-1.5z"></path>
					</svg>&nbsp;<span></span></label>
					<br />
			<input class="btn btn-default hidden" type="reset" value="Clear" />
			<input class="btn btn-success hidden" type="submit" value="Convert" />
		</form>
		<hr />
		<div id="footer" class="small">powered by <a class="btn btn-default btn-xs" href="https://github.com/exa18/gpsies2xml">GitHub</a></div>
	</div>
	</div>
	</div>
</body>
</html>
