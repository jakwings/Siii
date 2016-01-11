<div class="clearfix" role="article">
<?php
$slug = $data['slug'];
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
$homepage = $blog->EscapeHtml(rtrim($blog->GetMetadata('homepage'), '/'));

$link = $blog->EscapeHtml("{$homepage}/" . $blog->EncodeUrl($slug, TRUE) . ".html");
$event = $blog->GetEventBySlug($slug);
$title = $blog->EscapeHtml($event['title']);
$source = $blog->GetEventContent($slug);
$content = $blog->ParseMarkup($source);
echo <<<"EOT"
  <div class="title">
    <a href="{$link}">{$title}</a>
  </div>
  <div class="content">
    <article>{$content}</article>
  </div>
EOT;

$date = strftime('%Y-%m-%d %H:%M%z', $event['time']);
if ($event['mode'] !== 'hidden') {
    $timeline = $blog->EscapeHtml($event['timeline']);
    $link_timeline = $blog->EscapeHtml("{$root}/category/" . $blog->EncodeUrl($blog->GetCategorySlugByName($event['timeline'], TRUE)) . '/');
    echo <<<"EOT"
  <div class="date"><span class="icon-feather"></span><span class="invisible">寫</span>於「<a href="{$link_timeline}">{$timeline}</a>」 {$date}</div>
EOT;
} else {
    echo <<<"EOT"
  <div class="date"><span class="icon-feather"></span><span class="invisible">寫</span>於 {$date}</div>
EOT;
}
?>
</div>

