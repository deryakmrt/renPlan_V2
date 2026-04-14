<?php
// login_wrapper.php — Orijinal login formuna CAPTCHA eklemek için (include ile)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
function captcha_new(){ $_SESSION['c_a']=random_int(1,9); $_SESSION['c_b']=random_int(1,9); $_SESSION['c_ans']=$_SESSION['c_a']+$_SESSION['c_b']; $_SESSION['c_tok']=bin2hex(random_bytes(16)); }
if(!isset($_SESSION['c_ans'],$_SESSION['c_tok'])) captcha_new();
$captcha_block = '<div style="margin-top:12px;padding:12px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc">'
  . '<label style="display:block;margin:0 0 6px 0;color:#475569">Robot değil misiniz? Çözün: <strong style="color:#0e7490">'.(($_SESSION['c_a']??2).' + '.($_SESSION['c_b']??3)).'</strong></label>'
  . '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">'
  . '<input type="text" name="c_ans" inputmode="numeric" pattern="\\d+" required placeholder="Cevap" style="min-width:120px;padding:10px;border:1px solid #cbd5e1;border-radius:10px">'
  . '<input type="hidden" name="c_tok" value="'.htmlspecialchars($_SESSION['c_tok']??'',ENT_QUOTES).'">'
  . '</div></div>';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $ok = isset($_POST['c_tok'],$_POST['c_ans']) && hash_equals($_SESSION['c_tok'], (string)$_POST['c_tok']) && preg_match('/^\\d+$/', $_POST['c_ans'] ?? '') && (int)$_POST['c_ans'] === (int)$_SESSION['c_ans'];
  if(!$ok){ http_response_code(403); echo 'CAPTCHA hatalı.'; exit; }
  $_SESSION['c_tok']=bin2hex(random_bytes(16)); // rotate
}
// Kullanım: Formun kapanışından önce echo $captcha_block; yazın.
