<nav class="navigator" role="navigation">
  <ul>
<?php
$timelines = $blog->GetTimelineNames();
$hiddens = $blog->GetTimelineNames(TRUE);
$timelines = array_merge($timelines, $hiddens);
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
echo <<<"EOT"
<li><a title="首頁" href="{$root}/"><span class="icon-home"></span></a></li>
EOT;
foreach ($timelines as $timeline) {
    $text = $blog->EscapeHtml($timeline);
    $slug = $blog->EscapeHtml(rawurlencode($timeline));
    echo <<<"EOT"
<li><a href="{$root}/category/{$slug}/">{$text}</a></li>
EOT;
}
echo <<<"EOT"
<li><a title="訂閱" href="{$root}/feed.xml"><span class="icon-feed"></span></a></li>
EOT;

?>
  </ul>
</nav>

