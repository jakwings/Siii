<?php
$homepage = $blog->EscapeHtml(rtrim($this->GetMetadata('homepage'), '/'));
$title = $blog->GetMetadata('title', TRUE);
$subtitle = $blog->GetMetadata('subtitle', TRUE);

echo <<<"EOT"
<div class="title"><a href="{$homepage}/">{$title}</a></div>
<div class="subtitle">{$subtitle}</div>
EOT;
?>

