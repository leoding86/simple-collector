<?php
namespace leoding86\SimpleCollector;

use Sunra\PhpSimple\HtmlDomParser;

class Finder
{
    private $url;
    private $response;
    private $selectors;
    private $html;
    private $htmlDom;
    private $error;

    /**
     * 构造函数，初始化变量
     */
    public function __construct()
    {
        $this->url = null;
        $this->error = null;
        $this->response = array();
        $this->selectors = array();
    }
    
    public function getHtml()
    {
        return $this->html;
    }

    public function getHtmlDom()
    {
        return $this->htmlDom;
    }

    /**
     * 获得最近的错误
     * @return string 错误信息
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 设置错误信息
     * @param string $error 错误信息
     * @return void
     */
    protected function setError($error)
    {
        $this->error = $error;
    }

    /**
     * 设置需要采集的链接
     * @param string $url 有效的链接
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * 添加查找器
     * @param string $id 查找器id
     * @param FinderSelector $selector 查找器对象
     */
    public function addSelector($id, FinderSelector $selector)
    {
        $this->selectors[$id] = $selector;
    }

    public function getSelectors()
    {
        return $this->selectors;
    }

    public function getSelector($id)
    {
        return isset($this->selectors[$id]) ? $this->selectors[$id] : null;
    }

    /**
     * 通过通配符查找字符串
     * @param  string &$string       源文本
     * @param  string $wildcard      通配符表达式，只能包含一个通配标识
     * @param  string $wildcard_mark 通配标识
     * @return array                 查找的结果集合
     */
    final public function findByWildcard(&$string, $wildcard, $wildcard_mark = '[[*]]')
    {
        $result = array();
        /* 转化成小写 */
        $lower_case_string = strtolower($string);

        $pos = strpos($wildcard, $wildcard_mark);
        $start_str = strtolower(substr($wildcard, 0, $pos));
        $end_str = strtolower(substr($wildcard, $pos + strlen($wildcard_mark)));

        $begin_pos = 0;

        do {
            $start_pos = strpos($lower_case_string, $start_str, $begin_pos);

            if ($start_pos !== false) {
                $begin_pos = $start_pos;
                $end_pos = strpos($lower_case_string, $end_str, $begin_pos);
                if ($end_pos !== false) {
                    $begin_pos = $end_pos;
                    $result[] = substr($lower_case_string, $start_pos + strlen($start_str), $end_pos - $start_pos - strlen($start_str));
                }
            }
        }
        while ($start_pos !== false && $end_pos !== false);
        
        return $result;
    }

    /**
     * 通过query类型查找字符串结果
     * @param  simple_html_dom &$htmlDom simplehtmldom对象
     * @param  string $query    查询条件
     * @param  string $attr     查询属性
     * @return array            查找的结果集合
     */
    final public function findByQuery(&$htmlDom, $query, $attr)
    {
        $elements = $htmlDom->find($query);
        $result = array();

        foreach ($elements as $e) {
            if ($string = $e->getAttribute($attr)) {
                $result[] = $string;
            }
        }

        return $result;
    }

    final public function findBySelector(&$html, &$htmlDom, FinderSelector $selector)
    {
        $result = null;
        if ($selector->query) {
            $result = $this->findByQuery($htmlDom, $selector->query, $selector->attr);
        }
        else if ($selector->wildcard) {
            $result = $this->findByWildcard($html, $selector->wildcard, $selector->wildcardMark);
        }

        if (is_int($selector->offset)) {
            if (!empty($result)) {
                $result = $result[$selector->offset];
            }
            else {
                $result = null;
            }
        }

        return $result;
    }

    /**
     * 获得采集的结果
     * @param  string $url 有效的链接
     * @return array       查找的结果集合
     */
    public function getResult($url = null)
    {
        if ($url !== null) {
            $this->setUrl($url);
        }

        if (!$this->sendRequest($this->url)) {
            return false;
        }

        if ($this->htmlDom) {
            $this->htmlDom->clear();
        }

        unset($this->htmlDom, $this->html);

        $this->html = $this->getResponse('body');
        $this->htmlDom = HtmlDomParser::str_get_html($this->html);

        $result = array();
        foreach ($this->selectors as $name => $selector) {
            /* 初始化采集变量 */
            $result[$name] = $this->findBySelector($this->html, $this->htmlDom, $selector);
        }

        return $result;
    }

    /**
     * 发送请求
     * @param  string $url 有效的链接
     * @return boolean
     */
    private function sendRequest($url = null)
    {
        if ($url !== null)
            $this->setUrl($url);

        /* 构造请求 */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);

        /* 发送请求 */
        $response = curl_exec($ch);
        if (curl_errno($ch) > 0) {
            $this->setError(curl_error($ch));
        }
        else {
            $info = curl_getinfo($ch);

            /* 分析响应 */
            return $this->parseResponse($info, $response);
        }
        curl_close($ch);

        return false;
    }

    /**
     * 分析响应内容
     * @param  array  $info     响应信息
     * @param  string $response 响应文本
     * @return boolean
     */
    public function parseResponse($info, $response)
    {
        if ($info['http_code'] != 200) {
            if ($info['http_code'] == 301 || $info['http_code'] == 302) {
                $this->setError('Redirect is too deep');
            }
            else {
                $this->setError('Something happened');
            }
            return false;
        }

        $this->response['info'] = $info;
        $this->response['body'] = substr($response, $info['header_size']);
        return true;
    }

    /**
     * 获得响应信息
     * @param  string $part info|body
     * @return mixed
     */
    public function getResponse($part = null)
    {
        return $part ? $this->response[$part] : $this->response;
    }
}