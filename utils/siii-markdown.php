<?php
require_once 'class-parsedown.php';

class Markdown extends Parsedown
{
    protected $root;

    public function __construct($root)
    {
        $this->root = rtrim($root, '/');
    }

    public function parse($text)
    {
        $src = str_replace('^/', $this->root . '/', $text);
        return $markup = $this->text($src);
    }
}
?>
