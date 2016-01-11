<?php
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
$author = $blog->GetMetadata('author', TRUE);
$license = $blog->GetMetadata('license');
$license_text = $blog->EscapeHtml($license['text']);
$license_link = $blog->EscapeHtml($license['link']);

echo <<<"EOT"
<div class="footer">
  <div class="license">
    <a rel="license" href="{$license_link}">{$license_text}</a> © 2016
    <a rel="author" href="{$root}/about.html">{$author}</a>
  </div>
  <div class="about">
    <a href="https://github.com/jakwings/Siii">Powered by Siii</a>.
  </div>
</div>
EOT;
?>

