<?php
// pdf_minimal_alt.php — stricter minimal PDF with text, for Adobe plugin
if (headers_sent($f,$l)){header_remove();header('Content-Type:text/plain');echo "Headers already sent at $f:$l";exit;}
@ini_set('zlib.output_compression',0);
@ini_set('output_buffering',0);
while(ob_get_level()){ob_end_clean();}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="pdf_minimal_alt.pdf"');
$base64 = 'JVBERi0xLjQKNCAwIG9iago8PCAvVHlwZSAvRm9udCAvU3VidHlwZSAvVHlwZTEgL0Jhc2VGb250IC9IZWx2ZXRpY2EgPj4KZW5kb2JqCjUgMCBvYmoKPDwgL0xlbmd0aCAzOCA+PgpzdHJlYW0KQlQgL0YxIDI0IFRmIDEwMCA3MDAgVGQgKFBERiBPSykgVGogRVQKZW5kc3RyZWFtCmVuZG9iagozIDAgb2JqCjw8IC9UeXBlIC9QYWdlIC9QYXJlbnQgMiAwIFIgL01lZGlhQm94IFswIDAgNTk1IDg0Ml0gL1Jlc291cmNlcyA8PCAvRm9udCA8PCAvRjEgNCAwIFIgPj4gPj4gL0NvbnRlbnRzIDUgMCBSID4+CmVuZG9iagoyIDAgb2JqCjw8IC9UeXBlIC9QYWdlcyAvS2lkcyBbMyAwIFJdIC9Db3VudCAxID4+CmVuZG9iagoxIDAgb2JqCjw8IC9UeXBlIC9DYXRhbG9nIC9QYWdlcyAyIDAgUiA+PgplbmRvYmoKeHJlZgowIDYKMDAwMDAwMDAwMCA2NTUzNSBmIAowMDAwMDAwMDA5IDAwMDAwIG4gCjAwMDAwMDAwNzkgMDAwMDAgbiAKMDAwMDAwMDE2NyAwMDAwMCBuIAowMDAwMDAwMjkzIDAwMDAwIG4gCjAwMDAwMDAzNTAgMDAwMDAgbiAKdHJhaWxlcgo8PCAvU2l6ZSA2IC9Sb290IDEgMCBSID4+CnN0YXJ0eHJlZgozOTkKJSVFT0Y=';
$bin = base64_decode($base64);
header('Content-Length: '.strlen($bin));
echo $bin; exit;
?>