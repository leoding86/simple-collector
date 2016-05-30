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

    public function setQuerySelector($query, $attr, $offset = null)
    {
        $this->setQuery($query);
        $this->setAttr($attr);
        if ($offset !== null) $this->setOffset((int)$offset);
    }

    public function setWildcardSelector($wildcard, $offset = null, $wildcard_mark = '[[*]]')
    {
        $this->setWildcard($wildcard);
        if ($offset !== null) $this->setOffset((int)$offset);
        $this->setWildcardMark($wildcard_mark);
    }
}