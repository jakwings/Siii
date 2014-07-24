<?php
$slug = $data['slug'];
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
$homepage = $blog->EscapeHtml(rtrim($blog->GetMetadata('homepage'), '/'));
$link = $blog->EscapeHtml("{$homepage}/" . rawurlencode($slug) . ".html");
$event = $blog->GetEventBySlug($slug);
$date = strftime('%Y-%m-%d %H:%M%z', $event['time']);
$title = $blog->EscapeHtml($event['title']);
$timeline = $blog->EscapeHtml($event['timeline']);
$link_timeline = $blog->EscapeHtml("{$root}/category/" . rawurlencode($event['timeline']) . '/');
$source = $blog->GetEventContent($slug);
$content = $blog->ParseMarkup($source);
echo <<<"EOT"
<div class="content clearfix" role="article">
  <div class="title">
    <a href="{$link}">{$title}</a>
  </div>
  <article>{$content}</article>
  <div class="date"><span class="icon-feather"></span> 於「<a href="{$link_timeline}">{$timeline}</a>」 {$date}</div>
</div>
EOT;
?>

