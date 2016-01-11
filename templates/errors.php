<?php
switch ($data['status']) {
  case 404:
    header('HTTP/1.1 404 Not Found');
    echo '404 Not Found';
    break;
  case 500:
    header('HTTP/1.1 500 Internal Server Error');
    echo '500 Internal Server Error';
    break;
  case 503:
    header('HTTP/1.1 503 Service Unavailable');
    echo '503 Service Unavailable';
    break;
  default: break;
}
?>
