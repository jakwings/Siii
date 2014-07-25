<div class="pager clearfix" role="navigation">
  <ul>
<?php
$root = $blog->EscapeHtml(rtrim($blog->GetMetadata('path'), '/'));
$category_escaped = $blog->EscapeHtml($blog->GetCategoryName() ?
        ('category/' . $blog->EncodeUrl($blog->GetCategoryName(), TRUE)) : 'page');
$pagenum = $data['pagenum'];
$pagemax = $data['pagemax'];
if ($pagenum < $pagemax) {
    $pn_prev = $pagenum + 1;
    $link_prev = "{$root}/{$category_escaped}/{$pn_prev}/";
    echo <<<"EOT"
<li class="previous"><a href="{$link_prev}" rel="prev"><span class="icon-arrow-left3"></span></a></li>
EOT;
}
if ($pagenum > 1 and $pagenum <= $pagemax) {
    $pn_next = $pagenum - 1;
    if ($pn_next > 1) {
        $link_next = "{$root}/{$category_escaped}/{$pn_next}/";
    } else {
        $category = $blog->GetCategoryName() ?
                ('category/' . $blog->GetCategoryName()) : 'page';
        if ($category === 'page') {
            $link_next = "{$root}/";
        } else {
            $link_next = "{$root}/{$category_escaped}/";
        }
    }
    echo <<<"EOT"
<li class="next"><a href="{$link_next}" rel="next"><span class="icon-arrow-right3"></span></a></li>
EOT;
}
?>
  </ul>
</div>

