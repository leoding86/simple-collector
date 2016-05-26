<?php
namespace leoding86\SimpleCollector;

class FinderSelector
{
    public $query;
    public $attr;
    public $wildcard;
    public $wildcard_mark;
    public $offset;

    public function __construct()
    {
        $query = null;
        $attr = null;
        $wildcard = null;
        $wildcard_mark = '[[*]]';
        $offset = null;
    }

    public function setQuery($query)
    {
        $this->query = $query;
    }

    public function setAttr($attr)
    {
        $this->attr = $attr;
    }

    public function setWildcard($wildcard)
    {
        $this->wildcard = $wildcard;
    }

    public function setWildcardMark($wildcard_mark)
    {
        $this->wildcardMark = $wildcard_mark;
    }

    public function setOffset($offset)
    {
        $this->offset = $offset;
    }

    public function setQuerySelector($query, $attr)
    {
        $this->setQuery($query);
        $this->setAttr($attr);
    }

    public function setWildcardSelector($wildcard, $wildcard_mark = '[[*]]')
    {
        $this->setWildcard($wildcard);
        $this->setWildcardMark($wildcard_mark);
    }
}