<?php
/******************************************************************************\
 * @Version:    1.0.0
 * @Name:       Solog Sitemap Generator
 * @Info:       一个简单的 Sitemap 生成工具
 * @Date:       2013-09-21 02:51:10 +08:00
 * @File:       sitemap.class.php
 * @Author:     Jak Wings
 * @License:    GPLv3
 * @Compatible: PHP/5.2.x,5.3.x,5.4.x,5.5.x
\******************************************************************************/
 

/**
* @info     For more information about sitemap, please see:
*           http://www.sitemaps.org/protocol.html
* @supports Sitemap/0.9
*/
class Sitemap
{
  private $_items = array();

  /**
  * @info   初始化函数
  * @param  {String}  $loc: 页面 URL
  * @param  {String}  $changefreq: 更新频率
  * @param  {String}  $priority: 权重
  * @param  {String}  $lastmod: 页面上一次更新日期（默认为现在）
  * @return void
  */
  public function __construct($loc, $changefreq, $priority, $lastmod = NULL)
  {
    $this->AddUrl($loc, $changefreq, $priority, $lastmod);
  }

  /**
  * @info   添加 url 节点
  * @param  {String}  $loc: 页面 URL
  * @param  {String}  $changefreq: 更新频率
  * @param  {String}  $priority: 权重
  * @param  {String}  $lastmod: 页面上一次更新日期（默认为现在）
  * @return void
  */
  public function AddUrl($loc, $changefreq, $priority, $lastmod = NULL)
  {
    if ( !is_integer($lastmod) ) {
      $lastmod = strtotime($lastmod);
      $lastmod = FALSE === $lastmod ? time() : $lastmod;
    }
    $this->_items[] = array(
      'loc' => $loc,
      'lastmod' => strftime('%F', $lastmod),
      'changefreq' => $changefreq,
      'priority' => $priority,
    );
  }

  /**
  * @info   输出 sitemap 的内容
  * @param  void
  * @return void
  */
  public function Publish()
  {
    @ob_clean();
    @header('Content-Type: text/xml; charset="utf-8"');
    echo $this->_Generate();
  }

  /**
  * @info   获取 sitemap 的内容
  * @param  void
  * @return {String}
  */
  public function Fetch()
  {
    return $this->_Generate();
  }

  /**
  * @info   返回兼容 XML/1.0 后的源代码
  * @param  {String}  $str: HTML 源代码
  * @param  {String}  $encoding: 字符编码类型
  * @return {String}
  */
  private function _EscapeEntities($str, $encoding = 'UTF-8')
  {
    $patterns = array('&', '<', '>', '"', '\''); 
    $replacement = array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;'); 
    if ( function_exists('mb_ereg_replace') ) {
      mb_regex_set_options('pz');
      mb_regex_encoding($encoding); 
      foreach ( $patterns as $i => $pattern ) {
        $str = mb_ereg_replace($pattern, $replacement[$i], $str); 
      } 
    } else {
      $str = str_replace($patterns, $replacement, $str);
    }
    return $str; 
  }

  /**
  * @info   生成并返回 sitemap 的内容
  * @param  void
  * @return {String}
  */
  private function _Generate()
  {
    $rss = array();
    $rss[] = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>';
    $rss[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ( $this->_items as $item ) {
      $rss[] = '  <url>';
      foreach ( $item as $name => $value ) {
        if ( is_null($value) ) {
          continue;
        }
        $name = $this->_EscapeEntities($name, 'UTF-8');
        $value = $this->_EscapeEntities($value, 'UTF-8');
        $rss[] = "    <{$name}>{$value}</{$name}>";
      }
      $rss[] = '  </url>';
    }
    $rss[] = '</urlset>';
    return implode("\n", $rss);
  }
}
?>
