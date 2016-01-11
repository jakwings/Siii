<div class="pager clearfix" role="navigation">
  <ul>
<?php
$slug = $data['slug'];
$event = $blog->GetEventBySlug($slug);
if ($event['mode'] !== 'hidden') {
    $timeline = $event['mode'] === '' ? NULL : $event['timeline'];
    $prev = $blog->GetPrevEventBySlug($slug, NULL, $timeline);
    $next = $blog->GetNextEventBySlug($slug, NULL, $timeline);
    $root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));

    if ($prev !== NULL) {
        $title_prev = $blog->EscapeHtml($prev['title']);
        $slug_prev = $blog->EscapeHtml($blog->EncodeUrl($prev['slug'], TRUE));
        $link_prev = "{$root}/{$slug_prev}.html";
        echo <<<"EOT"
<li class="previous"><a href="{$link_prev}" rel="prev"><span class="icon-arrow-left2"></span>{$title_prev}</a></li>
EOT;
    }
    if ($next !== NULL) {
        $title_next = $blog->EscapeHtml($next['title']);
        $slug_next = $blog->EscapeHtml($blog->EncodeUrl($next['slug'], TRUE));
        $link_next = "{$root}/{$slug_next}.html";
        echo <<<"EOT"
<li class="next"><a href="{$link_next}" rel="next">{$title_next}<span class="icon-arrow-right2"></span></a></li>
EOT;
    }
}
?>
  </ul>
</div>

