<?php
require './figma_converter.php';

$figma = new figmaConverter();
$data = $figma->generateHtml('data.json');

echo $data['html'][0];

echo "<script>
var style = [" . $data['style'] . "];
for (var i = 0; i < style.length; i++){
    var obj = style[i];
    for (var key in obj){
        if(document.querySelector(\"[class='\" + key + \"']\") !== null){
            const items = document.querySelectorAll(\"[class='\" + key + \"']\");
            items.forEach((item) => {
                Object.assign((item).style, obj[key]);
            });
        }
        
    }
  }
</script>";