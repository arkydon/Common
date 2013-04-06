<?php
    /* INITIALLY FORKED FROM: https://raw.github.com/olamedia/kanon/master/src/parse/nokogiri.php */
    class DOM {
        public $html = null;
        private $_dom = null;
        private $_xpath = null;
        public function __construct($html = '') {
            self::init($html, $this);
        }
        public static function init($html, $pointer = null) {
            if(!$html or !is_string($html)) return null;
            if(is_null($pointer)) $pointer = new self();
            $pointer -> html = $html;
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom -> preserveWhiteSpace = false;
            libxml_use_internal_errors(true);
            $dom -> loadHTML('<?xml encoding="utf-8" ?>' . $html);
            libxml_clear_errors();
            $pointer -> _dom = $dom;
            $pointer -> _xpath = new DOMXpath($pointer -> _dom);
            return $pointer;
        }
        public function find($xpath, $index = null) {
            $xpath = preg_replace(
                '~class\((?P<class>.*?)\)~i',
                'contains(concat(" ",normalize-space(@class)," ")," $1 ")',
                $xpath
            );
            $list = $this -> _xpath -> query($xpath);
            if($list === false) trigger_error('NODELIST IS FALSE', E_USER_WARNING);
            $array = $this -> toArray($list);
            if(is_null($index)) return $array;
            return @ $array[$index];
        }
        private function attr($attr, $value) {
            $value = trim($value);
            if(strpos($value, '"') === false) return "{$attr}=\"{$value}\"";
            return "{$attr}='{$value}'";
        }
        public function __invoke($q) { return $this -> find($q); }
        private function toArray($node) {
            $array = array();
            if(!$node) return array();
            if($node instanceof DOMAttr) return $node -> value;
            if($node instanceof DOMNodeList) {
                foreach($node as $n) $array[] = $this -> toArray($n);
                return $array;
            }
            if($node -> nodeType == XML_TEXT_NODE) return $node -> nodeValue;
            if($node -> nodeType == XML_COMMENT_NODE) return '<!--' . $node -> nodeValue . '-->';
            $collector = "<{$node->tagName}%s>%s</{$node->tagName}>";
            $attr = array();
            $inner = array();
            if($node -> hasAttributes())
                foreach($node -> attributes as $a)
                    $attr[] = $this -> attr($a -> nodeName, $a -> nodeValue);
            if($node -> hasChildNodes())
                foreach($node -> childNodes as $childNode)
                    $inner[] = $this -> toArray($childNode);
            return sprintf($collector, ($attr ? ' ' . implode(' ', $attr): ''), implode('', $inner));
        }
    }
