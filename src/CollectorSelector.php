<?php
namespace leoding86\SimpleCollector;

class CollectorSelector extends FinderSelector
{
    const REPLACE_STRING = '__REPLACE_STRING__';
    const REPLACE_PATTERN = '__REPLACE_PATTERN__';
    private $replacements;

    public function __construct()
    {
        parent::__construct();
        $this->replacements = [];
    }

    /**
     * 创建一个替换实例
     * @return stdClass
     */
    private function createReplacement($search, $replace, $mode)
    {
        $replacement = new \stdClass();
        $replacement->search = $search;
        $replacement->replace = $replace;
        $replacement->mode = $mode;
        return $replacement;
    }

    /**
     * 添加替换模式
     * @param string $search  查找的内容
     * @param string $replace 替换的内容
     * @param string $mode    替换模式
     * @return void
     */
    public function addReplacement($search, $replace, $mode = null)
    {
        $mode = in_array($mode, [self::REPLACE_STRING, self::REPLACE_PATTERN]) ? $mode : self::REPLACE_STRING;
        $this->replacements[] = $this->createReplacement($search, $replace, $mode);
    }

    /**
     * 返回所有的匹配查找
     * @return array
     */
    public function getReplacements()
    {
        return $this->replacements;
    }
}