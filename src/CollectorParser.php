<?php
namespace leoding86\SimpleCollector;

class CollectorParser extends Finder
{
    private $domain;
    private $protocol;
    private $path;
    private $script;

    /**
     * 简化分析来源url
     * @param  string $url 有效的url
     * @return void
     */
    final public function simplifyUrl($url)
    {
        $parsed = parse_url($url);
        $this->protocol = $parsed['scheme'] . '://';
        $this->domain = $parsed['host'] . (isset($parsed['port']) ? (':' . $parsed['port']) : '');

        if (substr($parsed['path'], -1) === '/') {
            $this->path = $parsed['path'];
            $this->script = '';
        }
        else {
            $last_slash_pos = strrpos($parsed['path'], '/');
            $this->path = substr($parsed['path'], 0, $last_slash_pos + 1);
            $this->script = substr($parsed['path'], $last_slash_pos + 1);
        }
    }

    /**
     * 修改链接的方法
     * @return [type] [description]
     */
    public function changeUrl($url)
    {
        return $this->completeUrl($url);
    }

    /**
     * 格式化链接
     * @param  string $url    需要格式化的链接
     * @param  string $base   basename
     * @param  string $domain 主域名
     * @return string
     */
    protected function completeUrl($url)
    {
        /* 绝对地址 */
        if (strpos($url, '/') === 0) {
            $url = $this->protocol . $this->domain . '/' . $url;
        }
        /* 相对地址 */
        else if (strpos($url, 'http') !== 0) {
            $url = $this->protocol . $this->domain . $this->path . '/' . $url;
        }

        return $this->normalize($url);
    }

    /**
     * 将链接常规化
     * @param  string $url 有效的链接
     * @return string      常规化后的链接
     */
    protected function normalize($url)
    {
        $info = parse_url($url);
        $parts = explode('/', $info['path']);

        $new_parts = array();
        foreach ($parts as $key => $part) {
            if (!in_array($part, ['', '.'])) {
                $new_parts[] = $part;
            }
        }

        foreach ($new_parts as $key => $part) {
            if ($part === '..') {
                if ($key > 0 && isset($new_parts[$key - 1])) {
                    unset($new_parts[$key - 1]);
                }
                unset($new_parts[$key]);
            }
        }
        
        return $info['scheme'] . '://' . $info['host'] . '/' . implode('/', $new_parts);
    }

    /**
     * 获得内联页面分页链接
     * @param  string          $html     [description]
     * @param  simple_html_dom $html_dom [description]
     * @return array                     [description]
     */
    public function getContentInlinePages($html, $html_dom, FinderSelector $selector)
    {
        $urls = [];
        $page_html = $this->findBySelector($html, $htmlDom, $selector);

        if ($page_html) {
            $pattern = '/<a[^\/]+href=(?:"|\')(.+?)(?:"|\')[^\/]>/';
            $match = [];
            preg_match_all($pattern, $page_html, $match, PREG_SET_ORDER);
            foreach ($match as $val) {
                $urls[] = $val[1];
            }
        }

        return $urls;
    }

    /**
     * 查找内容页下一页分页链接
     * @param  string          $html    html字符串
     * @param  simple_html_dom $htmlDom simple_html_dom 对象
     * @return string
     */
    public function getContentContextPage($html, $html_dom, FinderSelector $selector)
    {
        throw new \Exception("CollectorParser::getContentContextPage is not implements", 1);
    }

    /**
     * 查找内容页分页链接
     * @param  string          $html    html字符串
     * @param  simple_html_dom $htmlDom simple_html_dom 对象
     * @return array
     */
    public function getContentPages($html, $html_dom)
    {
        throw new \Exception("CollectorParser::getContentPages is not implements", 1);
    }
}
