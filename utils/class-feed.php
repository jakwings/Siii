<?php
/******************************************************************************\
 * @Version:    1.0.0
 * @Name:       Solog RSS Feed Generator
 * @Info:       一个简单的 RSS 2.0 Feed 生成工具
 * @Date:       2014-03-02 16:36:24 +08:00
 * @File:       rss.class.php
 * @Author:     Jak Wings
 * @ModifiedBy: Jak Wings (2014-07-12T20:51:14+0800)
 * @License:    MIT Licensed
 * @Website:    https://github.com/jakwings/Solog-RSS-Feed-Generator
 * @Compatible: PHP/5.2.x,5.3.x,5.4.x,5.5.x
\******************************************************************************/
 

/**
* @info     For more information about RSS/2.0.x, please see:
*           http://www.rssboard.org/rss-specification
* @supports RSS/2.0.11
*/
class RssFeed
{
  private $_generator = 'Solog RSS Feed Generator v1.0.0';
  private $_specification = 'http://www.rssboard.org/rss-2-0-11';
  private $_items = array();
  private $_channel = array();

  /**
  * @info   初始化函数
  * @param  {String}  $title: Feed 标题
  * @param  {String}  $link: Feed 来源网站
  * @param  {String}  $description: Feed 简介
  * @param  {String}  $pubDate: Feed 初次发布日期（默认为现在）
  * @param  {String}  $upDate: Feed 上一次更新日期（默认为现在）
  * @return void
  */
  public function __construct($title, $link, $description, $pubDate = NULL, $upDate = NULL)
  {
    if ( !is_integer($pubDate) ) {
      $pubDate = strtotime($pubDate);
      $pubDate = FALSE === $pubDate ? time() : $pubDate;
    }
    if ( !is_integer($upDate) ) {
      $upDate = strtotime($upDate);
      $upDate = FALSE === $upDate ? time() : $upDate;
    }
    $info = explode('/', $_SERVER['SERVER_PROTOCOL']);
    $scheme = strtolower($info[0]);

    // 必须的子元素
    $this->_channel['title'] = $title ?: '';
    $this->_channel['link'] = $link ?: $scheme . '://' . $_SERVER['SERVER_NAME'];
    $this->_channel['description'] = $description ?: 'N/A';

    // 可选的子元素
    $this->_channel['pubDate'] = date(DATE_RSS, $pubDate);
    $this->_channel['lastBuildDate'] = date(DATE_RSS, $upDate);
    $other_elements = array(
      'language', 'copyright', 'managingEditor', 'webMaster', 'category',
      'cloud', 'ttl', 'image', 'rating', 'textInput', 'skipHours', 'skipDays'
    );
    foreach ( $other_elements as $element ) {
      $this->_channel[$element] = NULL;
    }
  }

  /**
  * @info   设定或返回 channel 的配置信息
  *         <name attr="..." attr="...">value</name>
  * @param  {String}  $name: 元素名称
  * @param  {String}  $value: 内容
  *         {Array}   $config:
  *                   {Array}  attrs: 属性（键值对）
  *                   {String|Array} value: 内容，或子元素
  * @return void
  */
  public function Config($name, $config)
  {
    if ( !array_key_exists($name, $this->_channel) ) {
      return FALSE;
    }
    if ( func_num_args() === 1 ) {
      return $this->_channel[$name];
    }
    $element = $this->_GenerateElement($name, $config);
    foreach ( $this->_channel as $index => $value ) {
      if ( strpos($index, $name . ' ') === 0 ) {
        unset($this->_channel[$index]);
      }
    }
    unset($this->_channel[$name]);
    $this->_channel[$element['name']] = $element['value'];
  }

  /**
  * @info   添加一篇文章
  *         <item>
  *           <name attr="..." attr="...">value</name>
  *           <name attr="..." attr="...">value</name>
  *           ...
  *           <name attr="..." attr="...">value</name>
  *         </item>
  * @param  {Array}   $config: 文章属性集
  *                   {String}  title: 文章标题
  *                   {String}  link: 文章链接
  *                   {String}  description: 文章简介
  *                   {String}  category: 文章分类目录
  *                   {String}  pubDate: 文章初次发布日期
  *                   {String}  author: 文章作者的电子邮箱
  *                   {String}  comments: 文章评论页面的链接
  *                   {String}  guid: 文章的唯一标识符
  *                   {Array}   enclosure: 附加的多媒体信息
  *                             {Array}  attrs: 属性（键值对）
  *                                      {String} url
  *                                      {String} length
  *                                      {String} type
  *                   {Array}   source: 文章的来源 channel 的属性及名称
  *                             {Array}  attrs: 属性（键值对）
  *                                      {String} url
  *                             {String} value: 内容
  * @return void
  */
  public function AddItem($config)
  {
    $item = array();
    if ( is_array($config) ) {
      $attrs = array(
        'title', 'link', 'description', 'category', 'pubDate',
        'comments', 'author', 'enclosure', 'guid', 'source'
      );
      foreach ( $attrs as $attr ) {
        if ( !isset($config[$attr]) ) {
          continue;
        }
        $item[$attr] = $config[$attr];
        if ( is_array($item[$attr]) ) {
          $element = $this->_GenerateElement($attr, $item[$attr]);
          unset($item[$attr]);
          $item[$element['name']] = $element['value'];
        }
      }
      if ( isset($item['pubDate']) ) {
        if ( !is_integer($item['pubDate']) ) {
          $item['pubDate'] = strtotime($item['pubDate']);
          $item['pubDate'] = FALSE === $item['pubDate'] ? time() : $item['pubDate'];
        }
        $item['pubDate'] = date(DATE_RSS, $item['pubDate']);
      }
    }
    $this->_items[] = $item;
  }

  /**
  * @info   输出 Feed 的内容
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
  * @info   获取 Feed 的内容
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
  * @info   生成并返回 item 的一个子元素
  * @param  {Array}   $config: 属性，值 
  * @return {Array}
  */
  private function _GenerateElement($type, $config = NULL)
  {
    $name = $type;
    $value = $config;
    if ( is_array($config) ) {
      if ( isset($config['attrs']) ) {
        foreach ( $config['attrs'] as $attr => $val ) {
          $val = $this->_EscapeEntities($val, 'UTF-8');
          $name .= " {$attr}=\"{$val}\"";
        }
      }
      if ( isset($config['value']) ) {
        if ( !is_array($config['value']) ) {
          $value = $config['value'];
        } else {
          $new_value = array();
          foreach ( $config['value'] as $t => $c ) {
            $new_value[] = $this->_GenerateElement($t, $c);
          }
          $value = $new_value;
        }
      } else if ( isset($config['attrs']) ) {
        $value = '';
      } else {
        $value = NULL;
      }
    }
    return array(
      'name' => $name,
      'value' => $value,
    );
  }

  /**
  * @info   生成并返回简单的 XML 内容
  * @param  {String}  $name: 节点名称
  * @param  {Mixed}   $value: 节点值
  * @param  {Integer} $indent: 缩进空格的长度（可选）
  * @param  {Integer} $level: 当前缩进的层数（可选）
  * @return {String}
  */
  private function _GenerateXML($name, $value, $indent = 0, $level = 0)
  {
    if ( is_null($value) ) {
      return '';
    }
    $space = str_repeat(' ', $indent) ?: '';
    $padding = str_repeat($space, $level) ?: '';
    $element = $this->_GenerateElement($name, $value);
    $tag_open = $element['name'];
    $info = explode(' ', $element['name'], 2);
    $tag_close = $info[0];
    if ( !is_array($value) ) {
      $value = $this->_EscapeEntities($element['value'], 'UTF-8');
    } else {
      $xml = array();
      foreach ( $value as $elem ) {
        $fragment = $this->_GenerateXML($elem['name'], $elem['value'], $indent, $level + 1);
        if ( !empty($fragment) ) {
          $xml[] = $fragment;
        }
      }
      $value = "\n" . implode("\n", $xml) . "\n" . $padding;
    }
    return $padding . "<{$tag_open}>{$value}</{$tag_close}>";
  }

  /**
  * @info   生成并返回 Feed 的内容
  * @param  void
  * @return {String}
  */
  private function _Generate()
  {
    $rss = array();
    $rss[] = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>';
    $rss[] = '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
    $rss[] = '  <channel>';
    $rss[] = '    <docs>' . $this->_specification . '</docs>';
    $rss[] = '    <generator>' . $this->_generator . '</generator>';
    $rss[] = '    <atom:link href="' . $this->_EscapeEntities(rtrim($this->_channel['link'], '/'), 'UTF-8') . '/feed.xml" rel="self" type="application/rss+xml" />';
    foreach ( $this->_channel as $name => $value ) {
      if ( is_null($value) ) {
        continue;
      }
      $rss[] = $this->_GenerateXML($name, $value, 2, 2);
    }
    foreach ( $this->_items as $item ) {
      $rss[] = '    <item>';
      foreach ( $item as $name => $value ) {
        $rss[] = $this->_GenerateXML($name, $value, 2, 3);
      }
      $rss[] = '    </item>';
    }
    $rss[] = '  </channel>';
    $rss[] = '</rss>';
    return implode("\n", $rss);
  }
}
?>
