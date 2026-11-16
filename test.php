<!doctype html>
<head>
<meta charset = 'utf-8'?>
<title>Jonas Bojanovsky — Gallery</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="stylesheet" href="style.css">


<?php


function getfiles($path){
	$files = scandir($path, SCANDIR_SORT_DESCENDING);
	// this function outputs the array
	print '[';
	foreach ($files as $value) {
		if ($value[0] != '.'){
			print '"' . $path . $value . '", ';
		}
	}
	print ']';
}


?>

<script>
//var fs = require('fs');
//var ims = fs.readdirSync('photos/');

const ims = <?php getfiles('./gallery/photos/'); ?>
var i = 0;
var out = '';

var numcols = 4;	
var size = '25vw';

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
	ims.forEach(maketable);
	writetable(out);

}

window.onload = function(){
	ims.forEach(maketable);
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
</table>

</div>
</body>