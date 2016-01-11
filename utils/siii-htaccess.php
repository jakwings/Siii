<?php
$base = rtrim($this->GetMetadata('path'), '/');

$htaccess = <<<"EOT"
Options FollowSymLinks SymLinksIfOwnerMatch
Order Deny,Allow

AddDefaultCharset UTF-8
<IfModule mod_php5.c>
  php_value magic_quotes_gpc off
  php_value default_charset utf-8
</IfModule>

<FilesMatch "^\.">
    Deny from all
</FilesMatch>

#<IfModule mod_dir.c>
    DirectoryIndex index.php
    #DirectorySlash Off
#</IfModule>

#<IfModule mod_headers.c>
#    Header always set X-Frame-Options SAMEORIGIN
#</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresDefault "access plus 12 hours"
  ExpiresByType "text/html" "access plus 60 minutes"
  ExpiresByType "text/css" "access plus 7 days"
  ExpiresByType "application/rss+xml" "access plus 30 minutes"
  ExpiresByType "application/javascript" "access plus 7 days"
  ExpiresByType "application/x-font-woff" "access plus 30 days"
  ExpiresByType "image/png" "access plus 7 days"
  ExpiresByType "image/jpg" "access plus 7 days"
  ExpiresByType "image/gif" "access plus 7 days"
  ExpiresByType "image/bmp" "access plus 7 days"
  ExpiresByType "image/svg+xml" "access plus 7 days"
</IfModule>

#<IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteBase {$base}/
    RewriteRule ^(.+)\.html$ index.php?view=$1 [NS,L]
    RewriteRule ^page/(\d+)/$ index.php?page=$1 [NS,L]
    RewriteRule ^category/(.+)/(\d+)/$ index.php?page=$2&category=$1 [NS,L]
    RewriteRule ^category/(.+?)/$ index.php?category=$1 [NS,L]
#</IfModule>
EOT;

file_put_contents('./.htaccess', $htaccess, LOCK_EX);
?>
