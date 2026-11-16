<!doctype html>
<head>
<meta charset = 'utf-8'?>
<title>Jonas Bojanovsky — Gallery</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="stylesheet" href="style.css">


<?php

$path = './photos/';
$files = scandir($path, SCANDIR_SORT_DESCENDING);
$ims = array();

$j = 0;


foreach ($files as $value){
	if (substr($value, -3) == 'jpg'){
		$ims[$j] = $path . $value;
		$j = $j + 1;
	}
}


function getPhotos($files, $path){
	// this function outputs the array
	print '[';
	foreach ($files as $value) {
		if (substr($value, -3) == 'jpg'){
			print '"' . $path . $value . '", ';
		}
	}
	print ']';
}

function getDescriptions($files, $path){
	//$files = scandir($path, SCANDIR_SORT_DESCENDING);
	// this function outputs the array
	
	print '[';
	foreach ($files as $value) {
		if (substr($value, -3) == 'txt'){
			$file = $path . $value;
			$descr = file_get_contents($file);
			print '"' . $descr . '", ';
			
		}
	}
	print ']';
}

function thisDescription($namepath){
	$namepath = substr($namepath, 0,-3);
	$namepath = $namepath . 'txt';
	$descr = file_get_contents($namepath);
	return $descr;
}

$i = 2;

?>

<script>
//var fs = require('fs');
//var ims = fs.readdirSync('photos/');

const ims = <?php getPhotos($files, $path); ?>;
const descrs = <?php getDescriptions($files, $path); ?>;
var i = 0;
var out = '';

var numcols = 4;	
var size = '25vw';
 /*
function maketable(filename) {
	if (i==0){
		out += '<tr>';
	}
	out += '<td style = "width: '+size+'; height: '+size+'"><a href = "'+filename+'"><img src = "'+filename+'" class = "tn"></a></td>';
	i++;
	if (i == numcols){
		out += '</tr>';
		i = 0;
	}
}
*/


function maketable(ims, descrs){
	var n = ims.length;
	
	for (let j = 0; j < n; j++){
		if (i==0){
			out += '<tr>';
		}

		image = ims[j];
		description = descrs[j];
		
		
		out += '<td style = "width: '+size+'; height: '+size+'"><a href = "'+image+'"><img src = "'+image+'" class = "tn"><div class = "des">'+description+'</div></a></td>';
		i++;
		if (i == numcols){
			out += '</tr>';
			i = 0;
		}
	}
}


function writetable(table){
	document.getElementById('maintable').innerHTML = table;
}

function changecols() {

	if (window.innerWidth < window.innerHeight){
		numcols = 2;
	} else {
		numcols = 4;
	}
	
	//var td = document.querySelector('table#maintable tr td');
	
	if (numcols == 2){
		size = '50vw'
	} else {
		size = '25vw';
	}
	
	out = '';
	i = 0;
	maketable(ims, descrs);
	writetable(out);

}

window.onload = function(){
	maketable(ims, descrs);
	writetable(out);
	changecols();
}
</script> 

</head>

<body onresize = 'changecols();'>

<div class = 'container'>
<h1>Gallery</h1>
<table class = 'thumbnails' id = 'maintable'>
<!-- here are the photos by the script -->
<? //print_r($ims); ?>
</table>

</div>
</body>