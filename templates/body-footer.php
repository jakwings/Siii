<?php
$author = $blog->GetMetadata('author', TRUE);
$license = $blog->GetMetadata('license');
$license_text = $blog->EscapeHtml($license['text']);
$license_link = $blog->EscapeHtml($license['link']);

echo <<<"EOT"
<div class="license">
  <a href="{$license_link}">{$license_text} © 2014 {$author}</a>
</div>
EOT;
?>

