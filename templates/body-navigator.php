<nav class="navigator" role="navigation">
  <div>
<?php
$selected_category = $blog->GetCategoryName();
$timelines = $blog->GetTimelineNames();
$collections = $blog->GetTimelineNames('collection');
$archives = $blog->GetTimelineNames('archive');
$timelines = array_merge($timelines, $collections, $archives);
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
echo <<<"EOT"
<span class="item"><a title="首頁" href="{$root}/"><span class="icon-home"></span><span class="invisible">首頁</span></a></span>
EOT;
foreach ($timelines as $timeline) {
    $text = $blog->EscapeHtml($timeline);
    $slug = $blog->EscapeHtml(
            $blog->EncodeUrl($blog->GetCategorySlugByName($timeline), TRUE));
    $extra_class = ($selected_category === $text) ? ' class="active"' : '';
    echo <<<"EOT"
<span class="item"><a href="{$root}/category/{$slug}/"{$extra_class}>{$text}</a></span>
EOT;
}
echo <<<"EOT"
<span class="item"><a href="{$root}/links.html">友站</a></span>
<span class="item"><a title="關於本站" href="{$root}/about.html"><span class="invisible">關於</span><span class="icon-feed"></span></a></span>
EOT;

?>
  </div>
</nav>

